### Current Behavior

`QubitRoute::matchesParameters()` guards a string-offset read with a `catch` that PHP 8 no longer matches, so the error escapes the guard and leaves the request uncaught.

`lib/routing/QubitRoute.class.php`, around line 119:

```php
if (isset($params[0])) {
    if ($params[0] instanceof sfOutputEscaper) {
        $params[0] = sfOutputEscaper::unescape($params[0]);
    }

    foreach (array_diff_key($this->params + $this->variables, $params) as $key => $ignore) {
        try {
            $params[$key] = $params[0][$key];
        } catch (sfException $e) {
        }
    }

    unset($params[0]);
```

The intent is clear and correct: if `$params[0]` cannot yield `$key`, skip it. That worked when `$params[0]` was an object whose offset access threw `sfException`, and when a string offset read with a string key merely raised a warning and evaluated to `''`.

Under PHP 8, `"a string"["slug"]` throws:

```
TypeError: Cannot access offset of type string on string
```

`TypeError` does not extend `Exception`, let alone `sfException`, so the existing `catch` does not apply and the throw propagates out of routing.

`$params[0]` is a string whenever `url_for()` is given a path rather than a resource — for example `url_for('user/'.$slug)` — which is a common enough idiom in themes and plugins.

**Steps to reproduce**

On AtoM 2.10.x running PHP 8:

1. `POST` to any path that does not resolve to a route — `/admin`, `/login`, `/register`, `/dashboard`, `/account` will all do.
2. The request ends in an uncaught `TypeError` rather than a 404.

Observed on 2.10.1 under PHP 8.3 / nginx / Ubuntu 22.04: a single automated scanner sweeping common admin paths produced 31 uncaught `TypeError`s in about two and a half minutes, all from this line. No authentication was involved.

The same line is reachable from ordinary application code whenever `url_for()` is passed a string path, so an authenticated user can meet it too; the unauthenticated POST is simply the easiest way to demonstrate it.

### Expected Behavior

A request to an unroutable path returns a 404. A key that cannot be read from `$params[0]` is skipped, exactly as the existing `catch` intends — regardless of which exception class the PHP version throws to say so.

### Possible Solution

Add the PHP 8 class to the existing guard. The intent, and the behaviour on every path that already worked, are unchanged:

```php
foreach (array_diff_key($this->params + $this->variables, $params) as $key => $ignore) {
    try {
        $params[$key] = $params[0][$key];
    } catch (sfException $e) {
    } catch (TypeError $e) {
    }
}
```

A stricter alternative is to test before reading, which avoids relying on exceptions for control flow:

```php
if (is_array($params[0]) || $params[0] instanceof ArrayAccess) {
    // existing loop
}
```

Either closes it. The first is the smaller change and preserves the current structure.

This looked like it might be one instance of a wider pattern, since it is a PHP 8 exception-class change rather than anything specific to routing. We checked, and it is not: of the 84 `catch (sfException ...)` sites in 2.10.1 (`lib/`, `apps/`, and the bundled `sf*`/`ar*`/`qb*` plugins), **exactly one wraps an offset read — this one.** The rest guard method dispatch, 28 of them around `call_user_func_array`, where `sfException` is the correct and only class that can arrive.

So this is a single-line fix, not a sweep.

### Context and Notes

AtoM 2.10.1 and 2.10.2 (`lib/routing/QubitRoute.class.php`, unchanged between the two tags). PHP 8.3, MySQL 8, nginx, Ubuntu 22.04.

Found while investigating a burst of uncaught errors in an application error log; the source turned out to be an external scanner POSTing to common paths rather than anything a user did.

Verified rather than inferred, since it is a small claim and easy to check:

```php
$s = "some/path";
$s["slug"];   // PHP 8.3: TypeError: Cannot access offset of type string on string
```

and that a `catch (TypeError $e)` alongside the existing `catch (sfException $e)` catches it. After adding it locally, replaying the same six POST paths produced zero uncaught errors where they had previously produced one each.

We have not tested against 2.9 or earlier.

### Version used

2.10.1

### Operating System and version

Ubuntu 22.04

### Default installation culture

en

### PHP version

8.3

### Contact details

johan@theahg.co.za
