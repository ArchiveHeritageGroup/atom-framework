<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for feedback write operations.
 *
 * Covers: creating feedback records on information objects.
 * The feedback entity uses the AtoM inheritance chain:
 *   object -> feedback -> feedback_i18n
 */
interface FeedbackWriteServiceInterface
{
    /**
     * Create a new feedback record.
     *
     * @param array  $data    Feedback data including:
     *                        - feed_name (string)
     *                        - feed_surname (string)
     *                        - feed_phone (string)
     *                        - feed_email (string)
     *                        - feed_relationship (string)
     *                        - feed_type_id (int)
     *                        - parent_id (string) unique identifier
     *                        - object_id (int) information object ID
     *                        - status_id (int)
     *                        - name (string) i18n
     *                        - remarks (string) i18n
     * @param string $culture Culture code (e.g., 'en')
     *
     * @return int The new feedback ID
     */
    public function createFeedback(array $data, string $culture = 'en'): int;
}
