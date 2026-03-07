-- =====================================================
-- Migration: Profile & Confirmation Email System
-- Date: 2026-03-04
-- Description: 
--   1. Add 'address' column to patients table
--   2. Add confirmation_email_sent fields to appointments
-- =====================================================

-- 1. Add address field to patients table
ALTER TABLE `patients` 
ADD COLUMN `address` VARCHAR(500) NULL DEFAULT NULL AFTER `city`;

-- 2. Add confirmation email tracking fields to appointments table
ALTER TABLE `appointments`
ADD COLUMN `confirmation_email_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `confirmed_at`,
ADD COLUMN `confirmation_email_sent_at` DATETIME NULL DEFAULT NULL AFTER `confirmation_email_sent`;

-- 3. Add index for confirmation email tracking
CREATE INDEX `idx_appointments_confirmation_email` ON `appointments` (`confirmation_email_sent`);
