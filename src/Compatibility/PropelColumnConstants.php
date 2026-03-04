<?php

/**
 * Propel Column Constants Compatibility Stubs.
 *
 * Defines Propel-style column constant classes for i18n tables
 * that don't have their own full entity stubs. Each constant is
 * a 'table_name.column_name' string used by Criteria::add().
 *
 * Constants for main entity tables (actor, term, etc.) are added
 * directly to their existing stub files.
 *
 * Only loaded if the real Propel classes are not available.
 */

// Actor i18n column constants
if (!class_exists('QubitActorI18n', false)) {
    class QubitActorI18n
    {
        public const ID = 'actor_i18n.id';
        public const CULTURE = 'actor_i18n.culture';
        public const AUTHORIZED_FORM_OF_NAME = 'actor_i18n.authorized_form_of_name';
        public const DATES_OF_EXISTENCE = 'actor_i18n.dates_of_existence';
        public const HISTORY = 'actor_i18n.history';
        public const PLACES = 'actor_i18n.places';
        public const LEGAL_STATUS = 'actor_i18n.legal_status';
        public const FUNCTIONS = 'actor_i18n.functions';
        public const MANDATES = 'actor_i18n.mandates';
        public const INTERNAL_STRUCTURES = 'actor_i18n.internal_structures';
        public const GENERAL_CONTEXT = 'actor_i18n.general_context';
    }
}

// Physical object i18n column constants
if (!class_exists('QubitPhysicalObjectI18n', false)) {
    class QubitPhysicalObjectI18n
    {
        public const ID = 'physical_object_i18n.id';
        public const CULTURE = 'physical_object_i18n.culture';
        public const NAME = 'physical_object_i18n.name';
        public const DESCRIPTION = 'physical_object_i18n.description';
        public const LOCATION = 'physical_object_i18n.location';
    }
}

// Information object i18n column constants
if (!class_exists('QubitInformationObjectI18n', false)) {
    class QubitInformationObjectI18n
    {
        public const ID = 'information_object_i18n.id';
        public const CULTURE = 'information_object_i18n.culture';
        public const TITLE = 'information_object_i18n.title';
        public const ALTERNATE_TITLE = 'information_object_i18n.alternate_title';
        public const EDITION = 'information_object_i18n.edition';
        public const EXTENT_AND_MEDIUM = 'information_object_i18n.extent_and_medium';
        public const ARCHIVAL_HISTORY = 'information_object_i18n.archival_history';
        public const ACQUISITION = 'information_object_i18n.acquisition';
        public const SCOPE_AND_CONTENT = 'information_object_i18n.scope_and_content';
        public const APPRAISAL = 'information_object_i18n.appraisal';
        public const ACCRUALS = 'information_object_i18n.accruals';
        public const ARRANGEMENT = 'information_object_i18n.arrangement';
        public const ACCESS_CONDITIONS = 'information_object_i18n.access_conditions';
        public const REPRODUCTION_CONDITIONS = 'information_object_i18n.reproduction_conditions';
        public const PHYSICAL_CHARACTERISTICS = 'information_object_i18n.physical_characteristics';
        public const FINDING_AIDS = 'information_object_i18n.finding_aids';
        public const LOCATION_OF_ORIGINALS = 'information_object_i18n.location_of_originals';
        public const LOCATION_OF_COPIES = 'information_object_i18n.location_of_copies';
        public const RELATED_UNITS_OF_DESCRIPTION = 'information_object_i18n.related_units_of_description';
        public const RULES = 'information_object_i18n.rules';
    }
}

// Repository i18n column constants
if (!class_exists('QubitRepositoryI18n', false)) {
    class QubitRepositoryI18n
    {
        public const ID = 'repository_i18n.id';
        public const CULTURE = 'repository_i18n.culture';
        public const GEOCULTURAL_CONTEXT = 'repository_i18n.geocultural_context';
        public const COLLECTING_POLICIES = 'repository_i18n.collecting_policies';
        public const BUILDINGS = 'repository_i18n.buildings';
        public const HOLDINGS = 'repository_i18n.holdings';
        public const FINDING_AIDS = 'repository_i18n.finding_aids';
        public const OPENING_TIMES = 'repository_i18n.opening_times';
        public const ACCESS_CONDITIONS = 'repository_i18n.access_conditions';
        public const DISABLED_ACCESS = 'repository_i18n.disabled_access';
        public const RESEARCH_SERVICES = 'repository_i18n.research_services';
        public const REPRODUCTION_SERVICES = 'repository_i18n.reproduction_services';
        public const PUBLIC_FACILITIES = 'repository_i18n.public_facilities';
        public const DESC_INSTITUTION_IDENTIFIER = 'repository_i18n.desc_institution_identifier';
        public const DESC_RULES = 'repository_i18n.desc_rules';
        public const DESC_SOURCES = 'repository_i18n.desc_sources';
        public const DESC_REVISION_HISTORY = 'repository_i18n.desc_revision_history';
    }
}

// Term i18n column constants
if (!class_exists('QubitTermI18n', false)) {
    class QubitTermI18n
    {
        public const ID = 'term_i18n.id';
        public const CULTURE = 'term_i18n.culture';
        public const NAME = 'term_i18n.name';
    }
}

// Event i18n column constants
if (!class_exists('QubitEventI18n', false)) {
    class QubitEventI18n
    {
        public const ID = 'event_i18n.id';
        public const CULTURE = 'event_i18n.culture';
        public const DATE = 'event_i18n.date';
        public const DESCRIPTION = 'event_i18n.description';
    }
}

// Accession i18n column constants
if (!class_exists('QubitAccessionI18n', false)) {
    class QubitAccessionI18n
    {
        public const ID = 'accession_i18n.id';
        public const CULTURE = 'accession_i18n.culture';
        public const APPRAISAL = 'accession_i18n.appraisal';
        public const ARCHIVAL_HISTORY = 'accession_i18n.archival_history';
        public const LOCATION_INFORMATION = 'accession_i18n.location_information';
        public const PHYSICAL_CHARACTERISTICS = 'accession_i18n.physical_characteristics';
        public const PROCESSING_NOTES = 'accession_i18n.processing_notes';
        public const RECEIVED_EXTENT_UNITS = 'accession_i18n.received_extent_units';
        public const SCOPE_AND_CONTENT = 'accession_i18n.scope_and_content';
        public const SOURCE_OF_ACQUISITION = 'accession_i18n.source_of_acquisition';
        public const TITLE = 'accession_i18n.title';
    }
}

// Note i18n column constants
if (!class_exists('QubitNoteI18n', false)) {
    class QubitNoteI18n
    {
        public const ID = 'note_i18n.id';
        public const CULTURE = 'note_i18n.culture';
        public const CONTENT = 'note_i18n.content';
    }
}

// Contact information column constants (no i18n — flat table)
if (!class_exists('QubitContactInformationI18n', false)) {
    class QubitContactInformationI18n
    {
        public const ID = 'contact_information_i18n.id';
        public const CULTURE = 'contact_information_i18n.culture';
        public const CONTACT_PERSON = 'contact_information_i18n.contact_person';
        public const STREET_ADDRESS = 'contact_information_i18n.street_address';
        public const CITY = 'contact_information_i18n.city';
        public const REGION = 'contact_information_i18n.region';
        public const NOTE = 'contact_information_i18n.note';
    }
}

// Event column constants (main table)
if (!class_exists('QubitEvent', false)) {
    // QubitEvent stub should be loaded separately — only define if it isn't
} elseif (!defined('QubitEvent::OBJECT_ID')) {
    // Constants will be added to the QubitEvent stub file instead
}

// Slug column constants
if (!class_exists('QubitSlugPeer', false)) {
    class QubitSlugPeer
    {
        public const ID = 'slug.id';
        public const OBJECT_ID = 'slug.object_id';
        public const SLUG = 'slug.slug';
    }
}
