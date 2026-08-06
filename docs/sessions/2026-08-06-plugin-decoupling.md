# Decoupling the plugins from each other and from the theme

Date: 2026-08-06
Commits: atom-framework e007871, atom-ahg-plugins 12df076b (feature/per-plugin-schema)

## What the clean-VM method found

Wiping atom210 to stock AtoM 2.10.1 and enabling one plugin at a time found four
defects in about an hour. None was visible on archaeology, where 59 enabled
plugins cover for each other.

- ahgCorePlugin asked a repository for ->title. Repositories are actors and carry
  authorizedFormOfName, so the home page 500s wherever a repository appears in
  the popular list.
- ahgThemeB5Plugin called ActorVisibilityService, owned by ahgActorManagePlugin.
  Without it the authority record form rendered 1,228 bytes of nothing.
- ahgThemeB5Plugin called url_for('@glam_browse'), a route owned by
  ahgDisplayPlugin. url_for throws on an unknown route, so every page carrying a
  search box returned 500.
- RAD, DACS, DC, MODS and RiC each referenced seven routes and IoFormHelper from
  ahgInformationObjectManagePlugin while declaring only ahgCorePlugin.

All four are the same shape: a plugin reaching sideways into a sibling with
nothing declaring the relationship.

## What changed

Shared edit-form machinery moved into ahgCorePlugin, which every standard plugin
already declares: IoFormHelper, the autocomplete and term-create endpoints, the
edit dispatcher, and a new shared io-form.js. Six templates dropped from 6,735 to
6,058 lines and two drifted variants became one implementation.

AhgNav added to ahgCorePlugin. It resolves routes without throwing and lets a
plugin contribute its own navigation entries, counts and visibility rules, so the
theme no longer queries other plugins' tables. Core plus theme alone now renders
every page with no PHP errors.

## Verification

Stock AtoM 2.10.1, all plugins removed: git reports zero modified base files and
ProjectConfiguration at its pristine 90 lines. Core plus theme only: home,
description browse, authority browse and login all 200, no errors.

## Not finished

The user menu still names plugin routes and runs their queries. It is guarded so
it degrades rather than breaks, but it is the same coupling. A scripted rewrite
cut across a three-deep conditional and was reverted; it needs hand-editing, and
the plugin nav registrations are dead code until then. Whoever does it must
remove the hardcoded blocks in the same change or every item appears twice.

The moved edit dispatcher has been proved to degrade without the standard
plugins, not to still work with them. That is the next test.

## Also fixed

Missing root repository row on archaeology, which meant institution creation had
never worked there. Unchecked route-parse dereferences in base AtoM, 53 across 13
modules, guarded from ahgCorePlugin. Repository autocomplete route shadowed by a
catch-all, broken on PSIS too. Repository logos 404 since the uploads security
fix, on every instance. ICIP consent panel showing raw database values. Add-child
passing a slug to a helper that cast it to int.

Relicensed to AGPL-3.0-or-later across 116 plugins; i18n catalogues extracted for
90 plugins, 19,030 strings.
