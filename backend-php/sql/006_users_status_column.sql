-- ============================================================
-- MediConnect – Add status column to users table
-- Supports activate/deactivate/block from admin bulk actions
-- ============================================================

ALTER TABLE users
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'
    AFTER password;
