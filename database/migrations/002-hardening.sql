ALTER TABLE users MODIFY role ENUM('admin','user') NOT NULL DEFAULT 'admin';
ALTER TABLE social_accounts MODIFY status ENUM('disconnected','connected','expired','revoked','error') NOT NULL DEFAULT 'disconnected';
ALTER TABLE legal_settings MODIFY imprint MEDIUMTEXT NOT NULL, MODIFY privacy MEDIUMTEXT NOT NULL;
SET @has_uq = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='social_accounts' AND INDEX_NAME='uq_social_account_provider_external');
SET @sql = IF(@has_uq=0,'ALTER TABLE social_accounts ADD UNIQUE KEY uq_social_account_provider_external (provider_id,external_account_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_ix = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='social_accounts' AND INDEX_NAME='ix_social_accounts_provider_status');
SET @sql = IF(@has_ix=0,'ALTER TABLE social_accounts ADD KEY ix_social_accounts_provider_status (provider_id,status)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
