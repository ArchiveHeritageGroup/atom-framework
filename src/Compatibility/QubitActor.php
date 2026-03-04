<?php

/**
 * QubitActor Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitActor.php.
 */

if (!class_exists('QubitActor', false)) {
    class QubitActor
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'actor';
        protected static string $i18nTableName = 'actor_i18n';

        public const ROOT_ID = 3;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }

        /**
         * Find actor by authorized form of name.
         *
         * @param string $name    Authorized form of name
         * @param array  $options Options (culture)
         *
         * @return static|null
         */
        public static function getByAuthorizedFormOfName($name, $options = [])
        {
            $culture = self::resolveCulture($options);

            try {
                $row = \Illuminate\Database\Capsule\Manager::table('actor as a')
                    ->join('actor_i18n as ai', 'a.id', '=', 'ai.id')
                    ->where('ai.authorized_form_of_name', $name)
                    ->where('ai.culture', $culture)
                    ->select('a.*')
                    ->first();

                return $row ? self::hydrate($row) : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Get all contact information records for this actor.
         *
         * @return array Array of hydrated objects
         */
        public function getContactInformation()
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('contact_information')
                    ->where('actor_id', $this->id)
                    ->orderByDesc('primary_contact')
                    ->get();

                $results = [];
                foreach ($rows as $row) {
                    $obj = new \QubitContactInformation();
                    foreach ($row as $k => $v) {
                        $obj->{$k} = $v;
                    }
                    $results[] = $obj;
                }

                return $results;
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get primary contact, or first contact as fallback.
         *
         * @return object|null
         */
        public function getPrimaryContact()
        {
            try {
                $row = \Illuminate\Database\Capsule\Manager::table('contact_information')
                    ->where('actor_id', $this->id)
                    ->orderByDesc('primary_contact')
                    ->first();

                if (!$row) {
                    return null;
                }

                $obj = new \QubitContactInformation();
                foreach ($row as $k => $v) {
                    $obj->{$k} = $v;
                }

                return $obj;
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Get actor-to-actor relations.
         *
         * @return array Array of relation objects
         */
        public function getActorRelations()
        {
            try {
                $db = \Illuminate\Database\Capsule\Manager::connection();

                return $db->table('relation as r')
                    ->join('term as t', 'r.type_id', '=', 't.id')
                    ->where('t.taxonomy_id', \QubitTaxonomy::ACTOR_RELATION_TYPE_ID)
                    ->where(function ($q) {
                        $q->where('r.object_id', $this->id)
                          ->orWhere('r.subject_id', $this->id);
                    })
                    ->select('r.*')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get actor occupations (term relations via ACTOR_OCCUPATION_ID taxonomy).
         *
         * @return array
         */
        public function getOccupations()
        {
            try {
                $culture = self::resolveCulture([]);

                return \Illuminate\Database\Capsule\Manager::table('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) use ($culture) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                    })
                    ->where('otr.object_id', $this->id)
                    ->where('t.taxonomy_id', \QubitTaxonomy::ACTOR_OCCUPATION_ID)
                    ->select('t.id', 'ti.name', 't.taxonomy_id', 'otr.id as relation_id')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get maintaining repository for this actor.
         *
         * @return \QubitRepository|null
         */
        public function getMaintainingRepository()
        {
            try {
                $relation = \Illuminate\Database\Capsule\Manager::table('relation')
                    ->where('object_id', $this->id)
                    ->where('type_id', \QubitTerm::MAINTAINING_REPOSITORY_RELATION_ID)
                    ->first();

                if (!$relation) {
                    return null;
                }

                return \QubitRepository::getById($relation->subject_id);
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Get actor-specific notes.
         *
         * @return array
         */
        public function getActorNotes()
        {
            try {
                $culture = self::resolveCulture([]);

                return \Illuminate\Database\Capsule\Manager::table('note as n')
                    ->join('note_i18n as ni', function ($j) use ($culture) {
                        $j->on('n.id', '=', 'ni.id')->where('ni.culture', '=', $culture);
                    })
                    ->where('n.object_id', $this->id)
                    ->select('n.id', 'n.type_id', 'ni.content', 'n.scope')
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
            try {
                $culture = self::resolveCulture([]);

                return \Illuminate\Database\Capsule\Manager::table('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) use ($culture) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                    })
                    ->where('otr.object_id', $this->id)
                    ->where('t.taxonomy_id', \QubitTaxonomy::SUBJECT_ID)
                    ->select('t.id', 'ti.name', 't.taxonomy_id', 'otr.id as relation_id')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get place access points.
         *
         * @return array
         */
        public function getPlaceAccessPoints()
        {
            try {
                $culture = self::resolveCulture([]);

                return \Illuminate\Database\Capsule\Manager::table('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) use ($culture) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                    })
                    ->where('otr.object_id', $this->id)
                    ->where('t.taxonomy_id', \QubitTaxonomy::PLACE_ID)
                    ->select('t.id', 'ti.name', 't.taxonomy_id', 'otr.id as relation_id')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }

        /**
         * Get resource relations (events linking actor to information objects).
         *
         * @return array
         */
        public function getResourceRelations()
        {
            try {
                $culture = self::resolveCulture([]);

                return \Illuminate\Database\Capsule\Manager::table('event as e')
                    ->join('information_object as io', 'e.object_id', '=', 'io.id')
                    ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                        $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                    })
                    ->where('e.actor_id', $this->id)
                    ->select('e.*', 'ioi.title', 'io.slug')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }
    }
}
