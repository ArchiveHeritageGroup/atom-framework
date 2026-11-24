<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomExtensions\Services\Search\SearchAccessFilterService;

/**
 * Base's description set, without descriptions hidden from a signed-out visitor.
 * A sitemap is public, and base knows none of the AHG access rules.
 * If the hidden list cannot be built this throws, so no sitemap is written.
 */
class VisibleSitemapInformationObjectSet extends \SitemapInformationObjectSet
{
    public function init()
    {
        $hidden = array_map('intval', SearchAccessFilterService::getInstance()->getRestrictedObjectIds(null));
        $notHidden = [] === $hidden ? '' : ' AND IO.id NOT IN ('.implode(',', $hidden).')';

        $query = 'SELECT IO.level_of_description_id, IO.parent_id, S.slug, O.created_at, O.updated_at
FROM information_object IO
LEFT JOIN object O ON (IO.id = O.id)
LEFT JOIN slug S ON (IO.id = S.object_id)
LEFT JOIN status SS ON (IO.id = SS.object_id)
WHERE SS.status_id = ? AND IO.id != ?'.$notHidden;

        $this->rec = $this->conn->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
        $this->rec->setFetchMode(\PDO::FETCH_INTO, new \SitemapInformationObjectUrl($this->config));
        $this->rec->execute([
            \QubitTerm::PUBLICATION_STATUS_PUBLISHED_ID,
            \QubitInformationObject::ROOT_ID,
        ]);
    }
}
