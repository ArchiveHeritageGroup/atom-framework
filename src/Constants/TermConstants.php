<?php
declare(strict_types=1);
namespace AtomExtensions\Constants;

/**
 * Term Constants - Replaces QubitTerm constants
 */
final class TermConstants
{
    // Root
    public const ROOT_ID = 110;
    
    // Publication Status
    public const PUBLICATION_STATUS_DRAFT_ID = 159;
    public const PUBLICATION_STATUS_PUBLISHED_ID = 160;
    
    // Digital Object Usage
    public const MASTER_ID = 169;
    public const REFERENCE_ID = 170;
    public const THUMBNAIL_ID = 171;
    public const COMPOUND_ID = 172;
    public const EXTERNAL_URI_ID = 173;
    public const OFFLINE_ID = 174;
    public const CHAPTERS_ID = 361;
    public const SUBTITLES_ID = 362;
    
    // Level of Description
    public const COLLECTION_ID = 376;
    public const FONDS_ID = 377;
    public const SUBFONDS_ID = 378;
    public const SERIES_ID = 379;
    public const SUBSERIES_ID = 380;
    public const FILE_ID = 381;
    public const ITEM_ID = 382;
    public const PART_ID = 384;
    
    // Media Types
    public const AUDIO_ID = 136;
    public const IMAGE_ID = 137;
    public const TEXT_ID = 138;
    public const VIDEO_ID = 139;
    public const OTHER_ID = 140;
    
    // Event Types
    public const CREATION_ID = 111;
    public const ACCUMULATION_ID = 114;
    public const CONTRIBUTION_ID = 117;
    
    // Entity Types
    public const CORPORATE_BODY_ID = 131;
    public const PERSON_ID = 132;
    public const FAMILY_ID = 133;
    
    // Name Types
    public const PARALLEL_FORM_OF_NAME_ID = 176;
    public const OTHER_FORM_OF_NAME_ID = 177;
    public const STANDARDIZED_FORM_OF_NAME_ID = 178;
    
    // Note Types
    public const GENERAL_NOTE_ID = 119;
    public const LANGUAGE_NOTE_ID = 120;
    public const PUBLICATION_NOTE_ID = 121;
    public const ARCHIVIST_NOTE_ID = 122;
    public const MAINTENANCE_NOTE_ID = 123;
    public const SCOPE_NOTE_ID = 124;
    public const ALTERNATIVE_LABEL_ID = 125;
    public const ACTOR_OCCUPATION_NOTE_ID = 126;
    
    // Relation Types
    public const NAME_ACCESS_POINT_ID = 161;
    public const DONOR_ID = 162;
    public const RIGHT_ID = 163;
    public const ACCESSION_ID = 164;
    public const ACCRUAL_ID = 165;
    public const AIP_RELATION_ID = 166;
    public const CONVERSE_TERM_ID = 167;
    public const RELATED_MATERIAL_DESCRIPTIONS_ID = 168;
    public const MAINTAINING_REPOSITORY_RELATION_ID = 169;
    
    // Rights Basis
    public const RIGHT_BASIS_COPYRIGHT_ID = 386;
    public const RIGHT_BASIS_LICENSE_ID = 387;
    public const RIGHT_BASIS_STATUTE_ID = 388;
    public const RIGHT_BASIS_POLICY_ID = 389;
    public const RIGHT_BASIS_DONOR_ID = 390;
}
