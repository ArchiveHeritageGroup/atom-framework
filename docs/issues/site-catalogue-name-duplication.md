## Problem

Of 3,964 named sites in the archaeological site catalogue behind one of our instances, only **3,006 names are distinct**. **1,243 rows — 31% — share a name with another site.**

The distribution of the worst offenders (names withheld - they are real site names):

```
1 farm name shared by 78 sites      1 shared by 24      3 shared by 19
1 river name shared by 33 sites     1 shared by 20
1 shared by 25
```

Each is a separate site with its own map sheet, coordinates and holdings. Seventy-eight authority records under one name do not identify anything.

This is worse than it looks in an interface, because it is invisible: in an autocomplete all 78 render as a correct, plausible choice, and a cataloguer picking one has no way to tell which site they have attached a holding to. Nothing errors.

## How it was found

Surfaced while investigating an unrelated report that the authority-record autocomplete showed "a drop-down full of question marks". That turned out not to be a bug — the widget opens before you type and shows page 1 of 2,623 records alphabetically, and the head of that order is punctuation, so the names really are `? Part 1` and `# 28 field camp`. Profiling the source catalogue to explain it turned up the duplication instead.

## Which key actually disambiguates

Tested against every combination present in the source file (4,708 rows):

| Key | Distinct | Rows still ambiguous |
|---|---:|---:|
| Site No. alone | 2,309 | 3,023 |
| Name + Map No. | 3,176 | 1,835 |
| Farm + Site No. | 3,236 | 1,858 |
| **Map No. + Site No.** | **4,682** | **49** |
| Name + Map No. + Site No. | 4,705 | 5 |

**Map No. + Site No. resolves all but 49 rows**, and 4,682 is the same figure the original import produced, which confirms it keyed on the same pair.

⚠️ **Site No. alone is useless** — 3,023 rows share one. Anything that joins on it will silently attach one site's name to another. This is the same trap already recorded against the source's phase sheets, which key on Site No. alone.

## The rest of the field, by class

| Class | Rows | Example | Bulk-safe? |
|---|---:|---|---|
| Blank name | 744 | - | Already handled: titled from farm + site no at import |
| Duplicate name | 1,243 | one name x78 | **Yes** - append map + site no |
| Starts with punctuation/digit | 120 | `? Part 1`, `# 14`, `(Site 43) <Name>` | Yes - this is what surfaces first everywhere |
| Uncertainty marker `?` | 67 | `<Hill name>?` | Yes - strip, keep uncertainty in a note |
| Parenthetical | 161 | `<Farm> Kraal A(2)` vs `T40 (height marker 1917), <country>` | **Partly** |
| Slash | 74 | `<NameA>/ <NameB>` | Partly - several are alternate names |
| ALL CAPS | 152 | `TP 1`, `ATTEN 99` | **No** - these look like trench labels, not site names |

**Parentheticals are not one thing.** `<Farm> Kraal A(2)` is a name; `T40 (height marker 1917), <country>` is a name plus a locational note; `(Site 43) <Name>` is a name with a cross-reference bolted on. Only the last two want moving, and telling them apart needs an archaeologist, not a regex.

**Slashes are often alternate names.** `<NameA>/ <NameB>` is one site with two accepted names — which is what ISAAR's parallel form of name is for, not something to discard.

## Proposed

Safe in bulk:

1. Disambiguate the 1,243 shared-name rows by appending Map No. + Site No., in the form the import already uses for unnamed sites. Leaves 49 for a person.
2. Strip trailing `?` from the 67 uncertain names, putting the uncertainty in a note. Precedent: `bin/import-holding-periods` did exactly this and is the model to follow — additive, reversible, and it refused to expand abbreviations because renaming a term later is cheap and re-mapping records is not.
3. Normalise whitespace (41 rows carry double spaces).

Needs a cataloguer, not a script: the 19 `?` names, the 66 `#`-prefixed ones, and the parenthetical/slash cases above.

Do **not**: rename from the phase sheets; expand abbreviations; touch the 152 ALL CAPS labels until somebody decides what they are.

## Scope note

This profiles the **source spreadsheet**, not a live instance. The instance reports 2,623 authority records against the source's 4,708 rows and 3,964 named sites; those numbers do not reconcile, so per-class counts there will differ. Confirming them needs a query against that instance, which is not currently reachable from the AHG network (443, 80 and 22 all refused).

The full analysis, with the per-class examples, is held internally. Kept out of this issue deliberately: the fix does not depend on whose catalogue it is.
