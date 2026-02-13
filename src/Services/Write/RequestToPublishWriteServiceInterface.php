<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for request-to-publish write operations.
 *
 * Covers: creating and updating request-to-publish records on information objects.
 * The request_to_publish entity uses the AtoM inheritance chain:
 *   object -> request_to_publish -> request_to_publish_i18n
 */
interface RequestToPublishWriteServiceInterface
{
    /**
     * Create a new request-to-publish record.
     *
     * @param array  $data    Request data including:
     *                        - rtp_name (string)
     *                        - rtp_surname (string)
     *                        - rtp_phone (string)
     *                        - rtp_email (string)
     *                        - rtp_institution (string)
     *                        - rtp_motivation (string)
     *                        - rtp_planned_use (string)
     *                        - rtp_need_image_by (string|null)
     *                        - parent_id (string) unique identifier
     *                        - unique_identifier (string)
     *                        - object_id (int) information object ID
     *                        - status_id (int)
     * @param string $culture Culture code (e.g., 'en')
     *
     * @return int The new request-to-publish ID
     */
    public function createRequest(array $data, string $culture = 'en'): int;

    /**
     * Update an existing request-to-publish record.
     *
     * @param int   $id   The request-to-publish ID
     * @param array $data Column => value pairs to update
     */
    public function updateRequest(int $id, array $data): void;
}
