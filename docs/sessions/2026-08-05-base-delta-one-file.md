# Reducing the AtoM base delta to one file

**Date:** 2026-08-05
**Repos:** atom-framework, atom-ahg-plugins (branch `feature/per-plugin-schema`)
**Tested on:** atom210 (192.168.0.131), clean AtoM 2.10 git checkout

## Question

For a plugins-only distribution, how many AtoM files must a client actually change?

## Method

The VM's AtoM is a git checkout, so the patched files are visible as modifications.
All 28 tracked base modifications were reverted, then restored one at a time, with
twelve pages and every injected plugin surface measured at each step.

## Result: one file

`config/ProjectConfiguration.class.php`. With only that restored, every page returned
the same status as the fully-patched baseline and every plugin surface rendered:
Collections Management block, record action bar, provenance panel, favourite and
feedback buttons, the contributed Manage menu rows, and a redaction save returning
`{"success":true}`.

The other 27 patches are not required for the plugins to function. They are base-AtoM
fixes — ACL, CSV import, finding aids, security.yml — and belong upstream, not in a
distribution. Two of them are whole-file copies of 1,200 and 2,900 lines, which cannot
survive an upstream release.

Caveat: this proves the plugins load, render, and save. It does not exercise every
write path in every plugin, so treat the 27 as "removable, verify per plugin".

## Why one file is unavoidable

`sfProjectConfiguration::__construct()` runs `setup()` → `loadPlugins()` →
`setupPlugins()`. Plugin configuration objects do not exist until after the plugin list
is fixed, so no plugin can enable a plugin. `index.php` requires
`config/ProjectConfiguration.class.php` by absolute path, so it cannot be shadowed.
Every file loaded before `setup()` is a tracked base AtoM file. `config/config.php` is
site-owned but Propel reads it only at connection time.

## The fork is now 20 lines

The logic moved to `AtomFramework\Config\PluginLoader::resolve()`; AtoM keeps a guarded
hand-off that degrades to a stock AtoM when the framework is absent or throws.

| | before | now |
|---|---|---|
| lines changed vs pristine | 261 | 20 |
| AtoM's AGPL header | deleted | restored |
| framework missing | broken | stock AtoM |

Two faults surfaced while porting. Moving the methods into a namespace made their
unqualified `sfConfig` and `Exception` references resolve as
`AtomFramework\Config\sfConfig`; because `resolve()` catches `Throwable`, the site came
up as a stock AtoM with nothing explaining why. And the routing preloads are
load-bearing — `RouteLoader` instantiates `AhgMetadataRoute`, `AddActionRoute` and
`SafeRequestRoute` by name outside any autoloader, so dropping the requires produced
0-byte responses site-wide.

## Can a file in the project root replace it?

Not with stock AtoM. AtoM does activate a plugin from a file — `activate-oidc-plugin`
in the project root adds `arOidcPlugin`, and that check is upstream code — but it is
hardcoded to that one plugin name.

That is the precedent for the upstream ask: generalise it to a `config/plugins.d`
directory, about ten lines, no behaviour change for installs that do not use it. If
accepted, the base delta goes to zero. Drafted at
`docs/upstream/atom-plugin-hook-proposal.md`.

## Follow-up

- `bin/install` still copies `patches/` over base AtoM; for a plugins-only release that
  step must go or become opt-in.
- Decide the licensing position before publishing: 91 plugins declare GPL-3.0, 5
  proprietary and 4 none, against AtoM's AGPL-3.0.
