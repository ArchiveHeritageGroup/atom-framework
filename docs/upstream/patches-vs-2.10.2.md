# The 41 patches against AtoM 2.10.2

Date: 2026-08-25
Method: every file in `atom-framework/patches/` compared with `git show v2.10.2:<path>`
on .131's clone of `github.com/artefactual/atom`. Not from release notes.

`bin/install` copies this tree over base AtoM, so each file here is a standing
fork of an upstream file. 2.10.2 moved some of them underneath us.

## ⚠️ Retirement is gated on the version we are ON, not on 2.10.2

"Upstream matches ours" is only a reason to delete a patch once the instance is
running that upstream. We are on **2.10.1**. A patch that matches 2.10.2 and
differs from 2.10.1 is still the only thing applying that fix here, and deleting
it today is a silent regression on every instance until the upgrade happens.

Checked against **both** tags rather than assuming:

| Patch | vs 2.10.1 | vs 2.10.2 | Verdict |
|---|---|---|---|
| `apps/qubit/modules/menu/templates/_userMenu.php` | same | same | **DELETED 2026-08-25** - patched nothing, ever |
| `apps/qubit/modules/menu/templates/_userMenu.mod_standard.php` | same | same | **DELETED 2026-08-25** |
| `apps/qubit/modules/menu/templates/_userMenu.mod_ext_auth.php` | same | same | **DELETED 2026-08-25** |
| `lib/QubitFindingAid.class.php` | differs | same | retire **at upgrade**, not before - ATOM-9 |
| `.../generateFindingAidAction.class.php` | differs | same but for our comment | retire **at upgrade** - ATOM-3 |
| `.../deleteFindingAidAction.class.php` | differs | same but for our comment | retire **at upgrade** - ATOM-4 |

Three files were dead weight in both directions and are gone. The other three
stay until the tree they patch is 2.10.2.

## ⛔ Delete at upgrade, do not copy (1)

| Patch | Why |
|---|---|
| `apps/qubit/modules/digitalobject/actions/updateAction.class.php` | **Upstream deleted this action in 2.10.2** (ATOM-5, 51 lines removed). Copying our patch over a 2.10.2 tree resurrects an action they removed on purpose. Same gate as above though: on 2.10.1 the action still exists and our patch is the CSRF guard on it, so it stays until the upgrade and is then deleted rather than retired. |

## Keep - upstream did not fix it (6)

Still vulnerable in 2.10.2. Our patch is the only thing protecting these
instances; losing it in an upgrade is a silent regression.

| Patch | Finding |
|---|---|
| `lib/QubitXmlImport.class.php` | **ATOM-1 XXE, High.** Upstream still sets `resolveExternals`/`substituteEntities = true`, no `LIBXML_NONET` |
| `apps/qubit/modules/informationobject/actions/exportCsvAction.class.php` | ATOM-6, drafts exportable by non-editors |
| `lib/model/QubitActor.php` | ATOM-7 `unserialize` |
| `lib/QubitCsvTransform.class.php` | ATOM-7 `unserialize` |
| `lib/filter/QubitSettingsFilter.class.php` | ATOM-7 `unserialize` |
| `apps/qubit/modules/settings/actions/inventoryAction.class.php` | ATOM-7 `unserialize` |

## Keep - deliberate divergence, decide consciously (1)

| Patch | Difference |
|---|---|
| `arRestApiPlugin/.../physicalobjectsCreateAction.class.php` | Upstream allows `administrator, editor, contributor`; **ours allows editor only.** Ours is stricter. Keeping it means contributors cannot create physical objects over the API - which is what we intended, but it is now a divergence from upstream rather than a fix to a hole. |

## Keep - our own features, and they need rebasing onto 2.10.2 (17)

These are not disclosure fixes; they are our functionality living in base files.
Upstream changed some of the same files in 2.10.2, so these are the ones that
need a real merge rather than a copy.

| Patch | Lines differing | What it carries |
|---|---:|---|
| `lib/model/QubitInformationObject.php` | 150 | largest fork in the tree |
| `apps/qubit/modules/user/actions/loginAction.class.php` | 55 | login scheme |
| `lib/routing/QubitMetadataRoute.class.php` | 55 | GLAM template codes - see AhgSafeMetadataRoute, which makes this one retirable |
| `lib/QubitPhysicalObjectCsvHoldingsReport.class.php` | 44 | holdings report |
| `apps/qubit/modules/user/actions/passwordEditAction.class.php` | 43 | password edit |
| `qbAclPlugin/lib/QubitInformationObjectAcl.class.php` | 32 | ACL |
| `lib/model/QubitMenu.php` | 23 | menu model |
| `qbAclPlugin/lib/QubitActorAcl.class.php` | 20 | ACL |
| `qbAclPlugin/lib/QubitAcl.class.php` | 15 | ACL |
| `lib/model/QubitUser.php` | 13 | **mixed**: upstream's salt fix is now identical to ours, but our file also carries the Argon2id `PasswordService::verify` migration (2026-06-15), which upstream does not have. Rebase: take their salt line, re-apply our verify block |
| `apps/qubit/config/security.yml` | 4 | July advisory |
| `apps/qubit/modules/user/config/security.yml` | 3 | July advisory |
| `apps/qubit/modules/search/config/security.yml` | 3 | July advisory |
| `apps/qubit/modules/taxonomy/config/security.yml` | 2 | July advisory |
| `apps/qubit/i18n/af/messages.xml` | 2322 | Afrikaans - upstream ships this file empty |
| `apps/qubit/i18n/tn/messages.xml` | 90 | Setswana - upstream has a thinner version |
| `README.md`, `zend-acl-duplicate-role.php` | - | not AtoM files, unaffected |

## Keep - pure additions, no upstream counterpart (9)

Nothing to merge; upstream has no such file.

- `apps/qubit/i18n/{nr,nso,ss,st,ts,ve,xh,zu}/messages.xml` - 8 SA languages
- `apps/qubit/modules/actor/config/security.yml` - the file the July advisory
  asks installers to create

## Summary

| Action | Files |
|---|---:|
| **Deleted 2026-08-25** - matched 2.10.1 and 2.10.2 both | **3** |
| Retire at upgrade - matches 2.10.2 only | 3 |
| Delete at upgrade - action removed upstream | 1 |
| Keep - upstream still vulnerable | 6 |
| Keep - deliberate divergence | 1 |
| Keep - our features, need rebasing | 17 |
| Keep - pure additions | 9 |

41 patches, now 38.

Only three could go today, and the reason matters: they matched 2.10.1 as well,
so they were never patching anything. The four that match 2.10.2 alone are still
load-bearing on the version we run. An earlier draft of this table said "retire
6" without that distinction, which would have removed the ATOM-3, ATOM-4, ATOM-5
and ATOM-9 fixes from a 2.10.1 tree.

The upgrade itself is not a formality: seventeen files carry our own
functionality and need merging, and six carry protection upstream has not yet
written.
