# Three create-path failures on archaeology, fixed live

Date: 2026-08-06
Instance: archaeology.theahg.co.za

Three forms failed during a training session. They looked like one bug and were three.

## 1. Base AtoM dereferences route parses without checking (53 places, 13 modules)

    $params = $this->context->routing->parse(Qubit::pathInfo($value));
    $resource = $params['_sf_route']->resource;   // never checked
    $value[$resource->id] = ...                    // fatal on null

Counted 53 occurrences across term (14), informationobject (10), actor (6), user and
repository (4 each), relation, object and event (3 each), right (2), plus four more.
Any value that does not resolve is a fatal and loses the entire form. Reachable by any
user; a donor name typed rather than picked from the autocomplete is the common case.

apps/ and qtAccessionPlugin are base AtoM and are not modified, so the values are
removed before base sees them: guardPostedResourceValues() in ahgCorePlugin, on
controller.change_action, against a RESOURCE_FIELDS allowlist, recursing into nested
structures.

Four attempts failed first, each checkable in advance:

- request.filter_parameters never reaches form binding. sfWebRequest::initialize()
  copies $_POST into postParameters and only the parameter holder is filtered; forms
  bind with getPostParameters().
- Both stores must be written. Forms read postParameters; relation components read
  $this->request['relatedDonor'], which is the parameter holder.
- Slug existence is the wrong test. A slug can exist while routing->parse() still
  returns a route with a null resource.
- The values are nested: relatedDonor[resource], or relatedDonors[0][resource] once
  the dialog JavaScript has run.

## 2. Error capture, which was the actual blocker

ErrorNotificationService installs set_exception_handler, which only fires for
exceptions reaching PHP uncaught. Symfony catches most first, so the only record was a
bare "HTTP 500" row with no file, line or trace. Listening to
application.throw_exception now writes the full trace to ahg_error_log. Diagnosis took
one step afterwards and five before.

## 3. Missing root repository row

/repository/add had never worked on this instance. QubitRepository::ROOT_ID is 6 and
earlyExecute() sets parentId to it on every create, but object/actor id=6 did not
exist, so every insert violated actor_FK_5. Four additive inserts, matched against
PSIS, fixed it.

Check SELECT COUNT(*) FROM actor WHERE id=6 on every new instance. An instance looks
healthy for months and fails the first time someone adds an institution.

## Also done

- Level of description: 8 archaeology terms (Research project, Permit, Fieldwork
  Season, Site, Trench, Context, Find, Sample) were absent from
  level_of_description_sector so the sector filter hid them. Mapped to archive; the
  form now offers 17.
- Training workbook produced in stuff/, md and docx, built against this instance.

## Open

- Donor form has no primary-contact field in any screen, so getPrimaryContact() returns
  null forever and donor details never appear on an accession.
- Accession save lands on /edit rather than the view page: route generation picks the
  prepended add-override because the plain RouteLoader routes cannot convert an object
  to a slug.
- PHP runs in UTC while MySQL and the host are SAST, and accession created_at matches
  neither.
