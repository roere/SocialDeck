-- Copy encrypted credentials without decrypting them. Re-runnable after partial DDL.
CREATE TABLE IF NOT EXISTS provider_apps (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id VARCHAR(50) NOT NULL,
 name VARCHAR(190) NOT NULL,
 client_id VARCHAR(255) NULL,
 client_secret_encrypted TEXT NULL,
 redirect_uri VARCHAR(2048) NULL,
 configured_scopes TEXT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 0 CHECK(enabled IN (0,1)),
 purpose VARCHAR(50) NULL,
 legacy_config_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_provider_app_legacy (legacy_config_id),
 UNIQUE KEY uq_provider_app_provider (id,provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE provider_configs ADD COLUMN IF NOT EXISTS apps_migrated TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE social_accounts ADD COLUMN IF NOT EXISTS provider_app_id BIGINT UNSIGNED NULL AFTER provider_id;
INSERT INTO provider_configs(provider_id,enabled,created_at,updated_at)
 SELECT DISTINCT a.provider_id,0,NOW(),NOW() FROM social_accounts a
 WHERE NOT EXISTS(SELECT 1 FROM provider_configs p WHERE p.provider_id=a.provider_id);
INSERT INTO provider_apps(provider_id,name,client_id,client_secret_encrypted,redirect_uri,configured_scopes,enabled,legacy_config_id,created_at,updated_at)
 SELECT provider_id,CONCAT(IF(provider_id='linkedin','LinkedIn',provider_id),' Standard'),client_id,client_secret_encrypted,redirect_uri,scopes,enabled,id,created_at,updated_at
 FROM provider_configs WHERE apps_migrated=0
 ON DUPLICATE KEY UPDATE legacy_config_id=VALUES(legacy_config_id);
UPDATE social_accounts a JOIN provider_apps p ON p.provider_id=a.provider_id AND p.legacy_config_id IS NOT NULL
 SET a.provider_app_id=p.id WHERE a.provider_app_id IS NULL;
UPDATE provider_configs p JOIN provider_apps a ON a.legacy_config_id=p.id
 SET p.client_id=NULL,p.client_secret_encrypted=NULL,p.redirect_uri=NULL,p.scopes=NULL,p.apps_migrated=1;
ALTER TABLE social_accounts MODIFY provider_app_id BIGINT UNSIGNED NOT NULL;
SET @sql=IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='social_accounts' AND CONSTRAINT_NAME='fk_account_provider_app')=0,
 'ALTER TABLE social_accounts ADD CONSTRAINT fk_account_provider_app FOREIGN KEY(provider_app_id,provider_id) REFERENCES provider_apps(id,provider_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE social_accounts DROP INDEX IF EXISTS uq_social_account_provider_external;
CREATE UNIQUE INDEX IF NOT EXISTS uq_social_account_app_external ON social_accounts(provider_app_id,external_account_id);
