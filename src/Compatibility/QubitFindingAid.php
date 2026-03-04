<?php

/**
 * QubitFindingAid — Compatibility stub.
 *
 * Status constants for finding aid generation.
 * Used by ahgDisplayPlugin findingAidComponent.
 */
if (!class_exists('QubitFindingAid', false)) {
    class QubitFindingAid
    {
        public const GENERATED_STATUS = 1;
        public const UPLOADED_STATUS = 2;
        public const ERROR_STATUS = 3;
    }
}
