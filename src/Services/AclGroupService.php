<?php
declare(strict_types=1);
namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use AtomExtensions\Constants\AclConstants;

/**
 * ACL Group Service - Replaces QubitAclGroup
 */
class AclGroupService
{
    public const ADMINISTRATOR_ID = AclConstants::ADMINISTRATOR_ID;
    public const EDITOR_ID = AclConstants::EDITOR_ID;
    public const CONTRIBUTOR_ID = AclConstants::CONTRIBUTOR_ID;
    public const TRANSLATOR_ID = AclConstants::TRANSLATOR_ID;

    public static function getById(int $id, ?string $culture = 'en'): ?object
    {
        return DB::table('acl_group as g')
            ->leftJoin('acl_group_i18n as gi', fn($j) => $j->on('g.id', '=', 'gi.id')->where('gi.culture', $culture))
            ->where('g.id', $id)
            ->select('g.*', 'gi.name', 'gi.description')
            ->first();
    }

    public static function get($criteria = null): Collection
    {
        $query = DB::table('acl_group as g')
            ->leftJoin('acl_group_i18n as gi', fn($j) => $j->on('g.id', '=', 'gi.id')->where('gi.culture', CultureHelper::getCulture()))
            ->select('g.*', 'gi.name', 'gi.description');
        
        if ($criteria && isset($criteria['min_id'])) {
            $query->where('g.id', '>', $criteria['min_id']);
        }
        
        return $query->orderBy('gi.name')->get();
    }
}
