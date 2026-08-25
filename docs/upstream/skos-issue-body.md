### Current Behavior

`sfSkosPlugin::importGraph()` runs two passes that disagree about which concepts exist, and the mismatch is fatal.

**Pass one** imports concepts and stamps each with `atom:id`:

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

**Pass two** walks *every* concept in the graph and dereferences that stamp on both ends of every `skos:related`, unguarded (line 113):

```php
foreach ($this->graph->allOfType('skos:Concept') as $c) {
    foreach ($c->allResources('skos:related') as $r) {
        $relations->insert($c->get('atom:id')->getValue(), $r->get('atom:id')->getValue());
    }
}
```

Pass one visits only what is reachable from a root via `skos:narrower`; pass two visits everything. Any concept in the second set but not the first has no `atom:id`, `get()` returns null, and `getValue()` is a fatal.

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

A file with no `ConceptScheme` imports every concept and cannot fail. A file declaring a scheme with `skos:hasTopConcept` narrows pass one to those top concepts and their `skos:narrower` descendants — so **adding well-formed scheme metadata to a working file is enough to break the import**.

Two distinct triggers:

1. **Target not imported** — `$r->get('atom:id')` is null.
2. **Subject not imported** — `$c->get('atom:id')` is null, because pass two iterates concepts pass one never saw. A `skos:related` on an unimported concept fatals even when its target imported cleanly.

**Steps to reproduce**

1. Save this as `sample.ttl`:

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

2. Import it into any taxonomy.
3. `ex:a` is created, then the job ends in Error.

`ex:b` is a `skos:Concept`, so pass two sees it; it is neither a top concept nor `skos:narrower` of one, so pass one never stamps it.

**Job report from the original report (a 10-concept Places file):**

```
[info] Importing SKOS file: sample_skos.ttl
[info] Type of scheme: file
[info] Taxonomy: Places
[info] Term ID: 110
[info] The graph contains 10 concepts.
[err]  Exception: Call to a member function getValue() on null
[err]  File: plugins/sfSkosPlugin/lib/sfSkosPlugin.class.php
[err]  Line: 113
```

### Expected Behavior

The import completes, creating the concepts it can and skipping only the relation it cannot express — with a line in the job log naming the concepts involved, so the cataloguer can see what was dropped and why.

A `skos:related` naming a concept outside the imported set is ordinary, valid SKOS. It should not be able to end the run.

Two things make the current behaviour worse than a simple failure:

- **The partial import persists.** Terms created before the fatal are already saved, so a re-run duplicates them and the file cannot simply be re-imported after a fix.
- **The error names no concept.** It reports a file and a line, so there is nothing to point the cataloguer at in a 10-concept file, let alone a large vocabulary.

### Possible Solution

Skip pairs where either end was not imported, and log it. The class already has a logger and already reports skipped input this way in the same method (`'Unexpected concept, type received: %1%.'`):

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

That keeps the terms and loses only the relation that could not be expressed.

A fuller fix would make pass one's reachability match pass two's — import every `skos:Concept` in the graph, and use `hasTopConcept` for hierarchy placement rather than for membership — but the guard above is the minimal change and stops a valid SKOS file destroying an import run.

### Context and Notes

AtoM 2.10.1 and 2.10.2 (`plugins/sfSkosPlugin/lib/sfSkosPlugin.class.php:113`, unchanged between the two). PHP 8.3, MySQL 8, nginx, Ubuntu 22.04.

Reported to us by a cataloguer importing a 10-concept SKOS file into the Places taxonomy. The blocking part in practice is not the crash itself but the cleanup: the partial import has to be undone by hand before the file can be tried again, and the error gives no clue which concept is at fault.

We confirmed `sfSkosPlugin` is byte-identical between the `v2.10.1` and `v2.10.2` tags, so 2.10.2 does not change this.

On what we verified rather than inferred, since it affects how much weight to give the above: we replayed both passes over the sample file using EasyRdf alone — the same graph calls the class makes, no database and no AtoM — and the disagreement is measured:

```
concepts in graph : 2
imported by pass 1: 1  (http://example.org/a)
skos:related a -> b : subject stamped, object NULL
```

The fatal itself we have from the reporter's job report and from reading the code path; we did not run the import end to end in a throwaway instance, because doing so writes terms into a live taxonomy.

Happy to test a fix against the original file if that would help.

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
