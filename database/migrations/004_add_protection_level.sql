-- Migration: 004_add_protection_level
-- Add protection level column to atom_extension
ALTER TABLE atom_extension ADD COLUMN protection_level ENUM('core','system','theme','extension') DEFAULT 'extension' AFTER status;
