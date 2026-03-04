<?php

/**
 * QubitInformationObject Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitInformationObject.php.
 */

if (!class_exists('QubitInformationObject', false)) {
    class QubitInformationObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'information_object';
        protected static string $i18nTableName = 'information_object_i18n';

        public const ROOT_ID = 1;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }

        /**
         * Get associated repository.
         *
         * @param array $options Options: 'inherit' => true to walk parent chain
         *
         * @return \QubitRepository|null
         */
        public function getRepository($options = [])
        {
            try {
                $repositoryId = $this->repository_id ?? null;

                // If no direct repo and inherit requested, walk parent chain
                if (!$repositoryId && !empty($options['inherit'])) {
                    $ancestors = $this->getAncestors();
                    if ($ancestors) {
                        // Walk from immediate parent upward
                        $ancestorList = is_array($ancestors) ? $ancestors : $ancestors->all();
                        $ancestorList = array_reverse($ancestorList);

                        foreach ($ancestorList as $ancestor) {
                            $repoId = $ancestor->repository_id ?? null;
                            if ($repoId) {
                                $repositoryId = $repoId;

                                break;
                            }
                        }
                    }
                }

                if (!$repositoryId) {
                    return null;
                }

                return \QubitRepository::getById($repositoryId);
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Get creators (actors with CREATION event type).
         *
         * @param array $options Options
         *
         * @return array Array of QubitActor instances
         */
        public function getCreators($options = [])
        {
            $options['eventTypeId'] = \QubitTerm::CREATION_ID;

            return $this->getActors($options);
        }

        /**
         * Get actors related via events.
         *
         * @param array $options Options: 'eventTypeId' to filter by event type
         *
         * @return array Array of QubitActor instances
         */
        public function getActors($options = [])
        {
            try {
                $culture = self::resolveCulture($options);
                $db = \Illuminate\Database\Capsule\Manager::connection();

                $query = $db->table('event as e')
                    ->join('actor as a', 'e.actor_id', '=', 'a.id')
                    ->join('actor_i18n as ai', function ($j) use ($culture) {
                        $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                    })
                    ->where('e.object_id', $this->id)
                    ->whereNotNull('e.actor_id');

                if (isset($options['eventTypeId'])) {
                    $query->where('e.type_id', $options['eventTypeId']);
                }

                $rows = $query->select('a.*', 'ai.authorized_form_of_name')
                    ->distinct()
                    ->get();

                $actors = [];
                foreach ($rows as $row) {
                    $actors[] = \QubitActor::hydrate($row);
                }

                return $actors;
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get date-related events.
         *
         * @param array $options Options
         *
         * @return array Array of QubitEvent instances
         */
        public function getDates($options = [])
        {
            try {
                $culture = self::resolveCulture($options);

                $query = \Illuminate\Database\Capsule\Manager::table('event as e')
                    ->leftJoin('event_i18n as ei', function ($j) use ($culture) {
                        $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', $culture);
                    })
                    ->where('e.object_id', $this->id)
                    ->where(function ($q) {
                        $q->whereNotNull('e.start_date')
                          ->orWhereNotNull('e.end_date')
                          ->orWhereNotNull('ei.date');
                    });

                $rows = $query->select('e.*', 'ei.date', 'ei.description as event_description')
                    ->get();

                $events = [];
                foreach ($rows as $row) {
                    $events[] = \QubitEvent::hydrate($row);
                }

                return $events;
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get actor-related events (events with an actor_id).
         *
         * @param array $options Options
         *
         * @return array Array of QubitEvent instances
         */
        public function getActorEvents($options = [])
        {
            try {
                $culture = self::resolveCulture($options);

                $rows = \Illuminate\Database\Capsule\Manager::table('event as e')
                    ->leftJoin('event_i18n as ei', function ($j) use ($culture) {
                        $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', $culture);
                    })
                    ->where('e.object_id', $this->id)
                    ->whereNotNull('e.actor_id')
                    ->select('e.*', 'ei.date', 'ei.description as event_description')
                    ->get();

                $events = [];
                foreach ($rows as $row) {
                    $events[] = \QubitEvent::hydrate($row);
                }

                return $events;
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get creation events.
         *
         * @return array Array of QubitEvent instances
         */
        public function getCreationEvents()
        {
            try {
                $culture = self::resolveCulture([]);

                $rows = \Illuminate\Database\Capsule\Manager::table('event as e')
                    ->leftJoin('event_i18n as ei', function ($j) use ($culture) {
                        $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', $culture);
                    })
                    ->where('e.object_id', $this->id)
                    ->where('e.type_id', \QubitTerm::CREATION_ID)
                    ->select('e.*', 'ei.date', 'ei.description as event_description')
                    ->get();

                $events = [];
                foreach ($rows as $row) {
                    $events[] = \QubitEvent::hydrate($row);
                }

                return $events;
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get term relations for a given taxonomy.
         *
         * @param int $taxonomyId Taxonomy ID to filter by
         *
         * @return array
         */
        public function getTermRelations($taxonomyId = null)
        {
            try {
                $culture = self::resolveCulture([]);
                $db = \Illuminate\Database\Capsule\Manager::connection();

                $query = $db->table('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) use ($culture) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                    })
                    ->where('otr.object_id', $this->id);

                if (null !== $taxonomyId && 'all' !== $taxonomyId) {
                    $query->where('t.taxonomy_id', $taxonomyId);
                }

                return $query->select(
                    'otr.id as relation_id',
                    't.id',
                    'ti.name',
                    't.taxonomy_id',
                    'otr.term_id'
                )
                    ->orderBy('ti.name')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get subject access points.
         *
         * @return array
         */
        public function getSubjectAccessPoints()
        {
            return $this->getTermRelations(\QubitTaxonomy::SUBJECT_ID);
        }

        /**
         * Get genre access points.
         *
         * @return array
         */
        public function getGenreAccessPoints()
        {
            return $this->getTermRelations(\QubitTaxonomy::GENRE_ID);
        }

        /**
         * Get place access points.
         *
         * @param array $options Options
         *
         * @return array
         */
        public function getPlaceAccessPoints($options = [])
        {
            return $this->getTermRelations(\QubitTaxonomy::PLACE_ID);
        }

        /**
         * Get material types.
         *
         * @return array
         */
        public function getMaterialTypes()
        {
            return $this->getTermRelations(\QubitTaxonomy::MATERIAL_TYPE_ID);
        }

        /**
         * Get name access points (actor relations of type NAME_ACCESS_POINT_ID).
         *
         * @return array
         */
        public function getNameAccessPoints()
        {
            try {
                $culture = self::resolveCulture([]);

                return \Illuminate\Database\Capsule\Manager::table('relation as r')
                    ->join('actor as a', 'r.object_id', '=', 'a.id')
                    ->join('actor_i18n as ai', function ($j) use ($culture) {
                        $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                    })
                    ->where('r.subject_id', $this->id)
                    ->where('r.type_id', \QubitTerm::NAME_ACCESS_POINT_ID)
                    ->select('r.id as relation_id', 'a.id', 'ai.authorized_form_of_name')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get properties by name and/or scope.
         *
         * @param string|null $name  Property name
         * @param string|null $scope Property scope
         *
         * @return array
         */
        public function getProperties($name = null, $scope = null)
        {
            try {
                $culture = self::resolveCulture([]);

                $query = \Illuminate\Database\Capsule\Manager::table('property as p')
                    ->leftJoin('property_i18n as pi', function ($j) use ($culture) {
                        $j->on('p.id', '=', 'pi.id')->where('pi.culture', '=', $culture);
                    })
                    ->where('p.object_id', $this->id);

                if (null !== $name) {
                    $query->where('p.name', $name);
                }
                if (null !== $scope) {
                    $query->where('p.scope', $scope);
                }

                return $query->select('p.id', 'p.name', 'p.scope', 'pi.value')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get publication status.
         *
         * @return object|null Status object with status_id property
         */
        public function getPublicationStatus()
        {
            try {
                return \Illuminate\Database\Capsule\Manager::table('status')
                    ->where('object_id', $this->id)
                    ->where('type_id', \QubitTerm::STATUS_TYPE_PUBLICATION_ID)
                    ->first();
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Get inherited reference code by walking the parent chain.
         *
         * @param bool $includeRepoAndCountry Prepend repository identifier and country code
         *
         * @return string
         */
        public function getInheritedReferenceCode($includeRepoAndCountry = false)
        {
            try {
                $parts = [];

                // Collect identifiers from current node up to (but not including) root
                $current = $this;
                while ($current && ($current->id ?? 0) != self::ROOT_ID) {
                    $identifier = $current->identifier ?? null;
                    if ($identifier) {
                        array_unshift($parts, $identifier);
                    }
                    // Get parent
                    $parentId = $current->parent_id ?? null;
                    if (!$parentId || $parentId == self::ROOT_ID) {
                        break;
                    }
                    $current = self::getById($parentId);
                }

                // Prepend repository + country if requested
                if ($includeRepoAndCountry) {
                    $repo = $this->getRepository(['inherit' => true]);
                    if ($repo) {
                        $repoIdentifier = $repo->identifier ?? null;
                        if ($repoIdentifier) {
                            array_unshift($parts, $repoIdentifier);
                        }
                        $countryCode = $repo->getCountryCode();
                        if ($countryCode) {
                            array_unshift($parts, $countryCode);
                        }
                    }
                }

                $separator = \sfConfig::get('app_separator_character', '-');

                return implode($separator, $parts);
            } catch (\Throwable $e) {
                return $this->identifier ?? '';
            }
        }

        /**
         * Get the collection root (highest non-root ancestor).
         *
         * @return static|null
         */
        public function getCollectionRoot()
        {
            try {
                $ancestors = $this->getAncestors();
                if (!$ancestors) {
                    return $this;
                }

                $ancestorList = is_array($ancestors) ? $ancestors : $ancestors->all();

                // Find the first ancestor whose parent is ROOT_ID
                foreach ($ancestorList as $ancestor) {
                    $parentId = $ancestor->parent_id ?? null;
                    if ($parentId == self::ROOT_ID) {
                        return $ancestor;
                    }
                }

                return $this;
            } catch (\Throwable $e) {
                return $this;
            }
        }
    }
}
