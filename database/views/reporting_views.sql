-- ============================================================
-- AtoM Heratio — Reporting Views
-- Denormalized SQL views for BI tool consumption
-- (Power BI, Tableau, Metabase, etc.)
--
-- Usage: SELECT * FROM v_report_descriptions LIMIT 100;
-- These views are read-only and safe to query at any time.
-- ============================================================

-- ============================================================
-- 1. v_report_descriptions
-- Flattened archival descriptions with related data
-- ============================================================
CREATE OR REPLACE VIEW `v_report_descriptions` AS
SELECT
    io.id,
    io.identifier AS reference_code,
    ioi.title,
    ioi.scope_and_content,
    ioi.extent_and_medium,
    ioi.archival_history,
    ioi.access_conditions,
    ioi.arrangement,
    lod.name AS level_of_description,
    ri.authorized_form_of_name AS repository_name,
    io.repository_id,
    io.parent_id,
    ds.name AS description_status,
    ei.date AS event_date,
    e.start_date AS event_start_date,
    e.end_date AS event_end_date,
    pub_status.name AS publication_status,
    io.source_culture,
    ioi.culture,
    io.lft,
    io.rgt
FROM
    `information_object` io
    LEFT JOIN `information_object_i18n` ioi
        ON ioi.id = io.id AND ioi.culture = io.source_culture
    LEFT JOIN `term_i18n` lod
        ON lod.id = io.level_of_description_id AND lod.culture = io.source_culture
    LEFT JOIN `repository` r
        ON r.id = io.repository_id
    LEFT JOIN `actor_i18n` ri
        ON ri.id = r.id AND ri.culture = io.source_culture
    LEFT JOIN `term_i18n` ds
        ON ds.id = io.description_status_id AND ds.culture = io.source_culture
    LEFT JOIN (
        SELECT e1.object_id, e1.id, e1.start_date, e1.end_date
        FROM `event` e1
        INNER JOIN (
            SELECT object_id, MIN(id) AS min_id
            FROM `event` WHERE type_id = 111
            GROUP BY object_id
        ) e2 ON e1.id = e2.min_id
    ) e ON e.object_id = io.id
    LEFT JOIN `event_i18n` ei
        ON ei.id = e.id AND ei.culture = io.source_culture
    LEFT JOIN `status` st
        ON st.object_id = io.id AND st.type_id = 158
    LEFT JOIN `term_i18n` pub_status
        ON pub_status.id = st.status_id AND pub_status.culture = io.source_culture
WHERE
    io.id != 1;

-- ============================================================
-- 2. v_report_authorities
-- Flattened authority records (actors)
-- ============================================================
CREATE OR REPLACE VIEW `v_report_authorities` AS
SELECT
    a.id,
    ai.authorized_form_of_name,
    et.name AS entity_type,
    ds.name AS description_status,
    ai.dates_of_existence,
    ai.history,
    ai.places,
    ai.legal_status,
    ai.functions,
    ai.mandates,
    ai.general_context,
    a.source_culture,
    ai.culture
FROM
    `actor` a
    LEFT JOIN `actor_i18n` ai
        ON ai.id = a.id AND ai.culture = a.source_culture
    LEFT JOIN `term_i18n` et
        ON et.id = a.entity_type_id AND et.culture = a.source_culture
    LEFT JOIN `term_i18n` ds
        ON ds.id = a.description_status_id AND ds.culture = a.source_culture
WHERE
    a.id != 1;

-- ============================================================
-- 3. v_report_accessions
-- Flattened accession records
-- ============================================================
CREATE OR REPLACE VIEW `v_report_accessions` AS
SELECT
    ac.id,
    ac.identifier,
    aci.title,
    ac.date AS accession_date,
    aci.source_of_acquisition,
    aci.scope_and_content,
    aci.archival_history,
    aci.appraisal,
    aci.received_extent_units,
    aci.processing_notes,
    aci.location_information,
    at.name AS acquisition_type,
    ps.name AS processing_status,
    pp.name AS processing_priority,
    ac.source_culture,
    aci.culture,
    ac.created_at,
    ac.updated_at
FROM
    `accession` ac
    LEFT JOIN `accession_i18n` aci
        ON aci.id = ac.id AND aci.culture = ac.source_culture
    LEFT JOIN `term_i18n` at
        ON at.id = ac.acquisition_type_id AND at.culture = ac.source_culture
    LEFT JOIN `term_i18n` ps
        ON ps.id = ac.processing_status_id AND ps.culture = ac.source_culture
    LEFT JOIN `term_i18n` pp
        ON pp.id = ac.processing_priority_id AND pp.culture = ac.source_culture;
