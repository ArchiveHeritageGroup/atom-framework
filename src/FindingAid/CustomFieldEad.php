<?php

namespace AtomFramework\FindingAid;

use AtomFramework\Services\CustomFieldValues;

/**
 * Custom fields in EAD 2002, and so in the PDF finding aid made from it (#202).
 *
 * Base AtoM's EAD template knows nothing of custom fields and cannot be changed,
 * so its output is augmented afterwards: each record that has custom field
 * values gets a <note label="Custom fields" altrender="customFields"> in its
 * <did>. Base's PDF stylesheets print every did/note at every level, labelled
 * from @label; they print an <odd> only when they know its @type, so an <odd>
 * would vanish from the PDF. @type is left off because base prints it raw.
 *
 * Base EAD gives components no record ids, so a <c> is matched to its record by
 * position: the template walks getDescendantsForExport($options) in order, and
 * so does this. Every match is confirmed by title; if any title disagrees, only
 * the top level (<archdesc>) is augmented, so a value never lands on the wrong
 * record.
 */
class CustomFieldEad
{
    /**
     * @param array     $options    the options base passed to its EAD template (they decide which descendants appear)
     * @param null|bool $publicOnly only public custom fields; defaults to $options['public']
     */
    public static function augment(string $xml, \QubitInformationObject $resource, array $options = [], ?bool $publicOnly = null): string
    {
        if (!CustomFieldValues::available() || '' === trim($xml)) {
            return $xml;
        }
        $publicOnly = $publicOnly ?? !empty($options['public']);

        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = true;
        if (!@$doc->loadXML($xml)) {
            return $xml;
        }
        $xp = new \DOMXPath($doc);

        $targets = [];
        $archdesc = $xp->query("//*[local-name()='archdesc']")->item(0);
        if (null === $archdesc) {
            return $xml;
        }
        $targets[(int) $resource->id] = $archdesc;

        if (empty($options['current-level-only'])) {
            $components = iterator_to_array($xp->query("//*[local-name()='dsc']//*[local-name()='c']"));
            $descendants = [];
            foreach ($resource->getDescendantsForExport($options) as $d) {
                $descendants[] = $d;
            }
            if (count($components) === count($descendants) && self::titlesAgree($xp, $components, $descendants)) {
                foreach ($descendants as $i => $d) {
                    $targets[(int) $d->id] = $components[$i];
                }
            }
        }

        $values = CustomFieldValues::forObjects(array_keys($targets), 'informationobject', $publicOnly);
        if (!$values) {
            return $xml;
        }

        foreach ($values as $id => $fields) {
            $node = $targets[$id];
            $ns = $node->namespaceURI;
            $note = $ns ? $doc->createElementNS($ns, 'note') : $doc->createElement('note');
            $note->setAttribute('label', 'Custom fields');
            $note->setAttribute('altrender', 'customFields');
            // No <head>: HTML output filters (ahgConditionPlugin's stylesheet
            // injector) treat any "</head>" in a response as the HTML head.
            foreach ($fields as $field) {
                $p = $ns ? $doc->createElementNS($ns, 'p') : $doc->createElement('p');
                $p->appendChild($doc->createTextNode($field['label'].': '.implode('; ', $field['values'])));
                $note->appendChild($p);
            }
            $did = null;
            foreach ($node->childNodes as $child) {
                if (XML_ELEMENT_NODE === $child->nodeType && 'did' === $child->localName) {
                    $did = $child;

                    break;
                }
            }
            if (null === $did) {
                $did = $ns ? $doc->createElementNS($ns, 'did') : $doc->createElement('did');
                $node->insertBefore($did, $node->firstChild);
            }
            $did->appendChild($note);
        }

        return $doc->saveXML();
    }

    /** Every component's unittitle matches its record's title, ignoring spacing and case. */
    private static function titlesAgree(\DOMXPath $xp, array $components, array $descendants): bool
    {
        $norm = static fn ($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $s)))));
        foreach ($components as $i => $c) {
            $unittitle = $xp->query("./*[local-name()='did']/*[local-name()='unittitle']", $c)->item(0);
            $expected = $norm($descendants[$i]->getTitle(['cultureFallback' => true]));
            if (null === $unittitle ? '' !== $expected : $norm($unittitle->textContent) !== $expected) {
                return false;
            }
        }

        return true;
    }
}
