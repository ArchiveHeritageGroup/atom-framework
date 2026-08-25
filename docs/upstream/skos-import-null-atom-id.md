# Bug: SKOS import fatals on a `skos:related` whose target was not imported

AtoM 2.10, `plugins/sfSkosPlugin/lib/sfSkosPlugin.class.php:113`. Stock file,
unmodified.

## Symptom

A SKOS import job ends in `Error` with no terms usable and no indication of which
concept caused it:

```
Exception: Call to a member function getValue() on null
File: plugins/sfSkosPlugin/lib/sfSkosPlugin.class.php
Line: 113
```

The job log shows the import getting as far as reading the graph:

```
[info] Importing SKOS file: sample_skos.ttl
[info] Type of scheme: file
[info] Taxonomy: Places
[info] Term ID: 110
[info] The graph contains 10 concepts.
[err]  Exception: Call to a member function getValue() on null
```

Terms created before the fatal are already saved, so a re-run duplicates them.

## Mechanism

`importGraph()` runs in two passes.

**Pass one** imports concepts and stamps each one with the id AtoM gave it:

```php
$concepts = $this->getRootConcepts();
foreach ($concepts as $item) {
    ...
    $this->addConcept($item, $this->parent->id);
}
```

`addConcept()` sets the stamp and recurses through `skos:narrower` only:

```php
$concept->set('atom:id', (int) $term->id);

foreach ($concept->allResources('skos:narrower') as $item) {
    $this->addConcept($item, $term->id);
}
```

**Pass two** walks *every* concept in the graph and dereferences that stamp on
both ends of every `skos:related`, unguarded:

```php
foreach ($this->graph->allOfType('skos:Concept') as $c) {
    foreach ($c->allResources('skos:related') as $r) {
        $relations->insert($c->get('atom:id')->getValue(), $r->get('atom:id')->getValue());
    }
}
```

The two passes disagree about which concepts exist. Pass one visits only what is
reachable from a root via `skos:narrower`; pass two visits everything. Any
concept in the second set but not the first has no `atom:id`, `get()` returns
null, and `getValue()` is a fatal.

`getRootConcepts()` is what makes this easy to hit:

```php
$conceptScheme = $this->graph->get('skos:ConceptScheme', '^rdf:type');
if (null !== $conceptScheme) {
    $topConcepts = $conceptScheme->allResources('skos:hasTopConcept');
    if (0 < count($topConcepts)) {
        return $topConcepts;
    }
}

return $this->graph->allOfType('skos:Concept');
```

A file that declares a `ConceptScheme` with `skos:hasTopConcept` narrows pass one
to those top concepts and their `skos:narrower` descendants. A file that declares
no scheme imports every concept and cannot hit the bug. **Adding a well-formed
`ConceptScheme` to a working file is enough to break the import** - the more
correct the SKOS, the more likely the fatal.

Two distinct triggers:

1. **Target not imported.** `$r->get('atom:id')` is null - a concept related to
   one that is not reachable from a root.
2. **Subject not imported.** `$c->get('atom:id')` is null - pass two iterates
   concepts pass one never saw, so a `skos:related` on an unimported concept
   fatals even when its target imported cleanly.

Trigger 2 also means a concept reachable only by `skos:broader`, or one left
outside the scheme by an authoring tool, is enough on its own.

## Minimal reproduction

```turtle
@prefix skos: <http://www.w3.org/2004/02/skos/core#> .
@prefix ex:   <http://example.org/> .

ex:scheme a skos:ConceptScheme ;
    skos:hasTopConcept ex:a .

ex:a a skos:Concept ;
    skos:prefLabel "Imported"@en ;
    skos:related ex:b .

ex:b a skos:Concept ;
    skos:prefLabel "Never imported"@en .
```

`ex:b` is a `skos:Concept`, so pass two sees it; it is neither a top concept nor
`skos:narrower` of one, so pass one never stamps it. Import into any taxonomy:
`ex:a` is created, then the job fatals.

Replaying both passes over that file with EasyRdf alone - the same graph calls
the class makes, no database and no AtoM:

```
concepts in graph : 2
imported by pass 1: 1  (http://example.org/a)
skos:related http://example.org/a -> http://example.org/b :
    subject stamped, object NULL   <-- getValue() on null here
```

To be exact about what that shows: the disagreement between the two passes is
measured, and it lands on the dereference at line 113. The fatal itself is read
from the code path and from the reporter's job log, not executed here - running
it end to end would write terms into a live taxonomy.

## Suggested fix

Skip pairs where either end was not imported, and say so rather than failing the
whole import. The class already has a logger and already reports skipped input in
`importGraph()`:

```php
foreach ($this->graph->allOfType('skos:Concept') as $c) {
    foreach ($c->allResources('skos:related') as $r) {
        $subject = $c->get('atom:id');
        $object = $r->get('atom:id');

        if (null === $subject || null === $object) {
            $this->logger->info($this->i18n->__(
                'Skipping skos:related between %1% and %2%: one of them was not imported.',
                ['%1%' => $c->getUri(), '%2%' => $r->getUri()]
            ));

            continue;
        }

        $relations->insert($subject->getValue(), $object->getValue());
    }
}
```

That keeps the terms and loses only the relation that could not be expressed,
which matches how the same method already handles an unexpected concept type.

A fuller fix would make pass one's reachability match pass two's - importing
every `skos:Concept` in the graph and using `hasTopConcept` for hierarchy
placement rather than for membership - but the guard above is the minimal change
and stops a valid SKOS file destroying an import run.

## Notes

- Reported by a cataloguer importing a 10-concept Places file; the failure gives
  no indication which concept is at fault, and the partial import has to be
  cleaned up by hand before retrying.
- Same shape as other unguarded dereferences in the codebase: the null is a
  legitimate state, not a corrupt one.
