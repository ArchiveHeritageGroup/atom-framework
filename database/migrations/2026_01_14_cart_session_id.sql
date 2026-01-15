-- Migration: Add session_id to cart table for guest checkout
-- Date: 2026-01-14

ALTER TABLE cart ADD COLUMN IF NOT EXISTS session_id VARCHAR(255) DEFAULT NULL AFTER user_id;

-- Add index for session lookups
CREATE INDEX IF NOT EXISTS idx_cart_session ON cart(session_id);
