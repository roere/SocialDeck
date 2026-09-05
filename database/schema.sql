CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_configs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id VARCHAR(50) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0 CHECK (enabled IN (0,1)),
  client_id VARCHAR(255) NULL,
  client_secret_encrypted TEXT NULL,
  redirect_uri VARCHAR(2048) NULL,
  scopes TEXT NULL,
  settings_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_provider_configs_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id VARCHAR(50) NOT NULL,
  external_account_id VARCHAR(255) NULL,
  display_name VARCHAR(255) NULL,
  account_type VARCHAR(100) NULL,
  access_token_encrypted TEXT NULL,
  refresh_token_encrypted TEXT NULL,
  token_expires_at DATETIME NULL,
  scopes TEXT NULL,
  status ENUM('disconnected','connected','expired','revoked','error') NOT NULL DEFAULT 'disconnected',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_social_account_provider_external (provider_id,external_account_id),
  KEY ix_social_accounts_provider_status (provider_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_channels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  social_account_id BIGINT UNSIGNED NOT NULL,
  provider_id VARCHAR(50) NOT NULL,
  external_channel_id VARCHAR(255) NOT NULL,
  channel_type ENUM('personal','organization') NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  external_urn VARCHAR(500) NULL,
  role VARCHAR(100) NULL,
  can_publish TINYINT(1) NOT NULL DEFAULT 0 CHECK (can_publish IN (0,1)),
  status ENUM('active','inactive','revoked') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  last_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_social_channels_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE CASCADE,
  UNIQUE KEY uq_social_channels_account_external (social_account_id,provider_id,external_channel_id),
  KEY ix_social_channels_provider_status (provider_id,status),
  KEY ix_social_channels_account (social_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publishing_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id VARCHAR(50) NOT NULL,
  social_channel_id BIGINT UNSIGNED NOT NULL,
  mode ENUM('live') NOT NULL,
  final_text TEXT NOT NULL,
  visibility ENUM('PUBLIC','CONNECTIONS') NOT NULL,
  external_post_id VARCHAR(500) NULL,
  status ENUM('success','failed') NOT NULL,
  error_code VARCHAR(100) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_publishing_attempt_channel FOREIGN KEY (social_channel_id) REFERENCES social_channels(id),
  KEY ix_publishing_attempts_provider_created (provider_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  base_text TEXT NOT NULL,
  resolved_text TEXT NOT NULL,
  link_url VARCHAR(2048) NULL,
  link_title VARCHAR(399) NULL,
  link_description VARCHAR(4085) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  status ENUM('publishing','published','failed') NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_posts_user FOREIGN KEY (created_by) REFERENCES users(id),
  KEY ix_posts_created (created_at), KEY ix_posts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_targets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  provider_id VARCHAR(50) NOT NULL,
  social_account_id BIGINT UNSIGNED NOT NULL,
  channel_id BIGINT UNSIGNED NOT NULL,
  final_text TEXT NOT NULL,
  final_link_url VARCHAR(2048) NULL,
  link_title VARCHAR(399) NULL,
  link_description VARCHAR(4085) NULL,
  status ENUM('publishing','published','failed') NOT NULL,
  external_post_id VARCHAR(500) NULL,
  published_at DATETIME NULL,
  error_code VARCHAR(100) NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_post_targets_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_targets_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id),
  CONSTRAINT fk_post_targets_channel FOREIGN KEY (channel_id) REFERENCES social_channels(id),
  KEY ix_post_targets_post (post_id), KEY ix_post_targets_provider_status (provider_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE publishing_attempts ADD COLUMN IF NOT EXISTS post_id BIGINT UNSIGNED NULL AFTER id;
SET @has_post_fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='publishing_attempts' AND CONSTRAINT_NAME='fk_publishing_attempt_post');
SET @sql = IF(@has_post_fk=0,'ALTER TABLE publishing_attempts ADD CONSTRAINT fk_publishing_attempt_post FOREIGN KEY (post_id) REFERENCES posts(id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS media_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NULL,
  provider_id VARCHAR(50) NOT NULL,
  social_account_id BIGINT UNSIGNED NOT NULL,
  channel_id BIGINT UNSIGNED NULL,
  media_type ENUM('image','video','document') NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(127) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  external_media_id VARCHAR(500) NULL,
  upload_status ENUM('stored','initializing','uploading','uploaded','failed') NOT NULL DEFAULT 'stored',
  processing_status ENUM('pending','processing','available','failed') NOT NULL DEFAULT 'pending',
  error_code VARCHAR(100) NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_media_assets_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL,
  CONSTRAINT fk_media_assets_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id),
  CONSTRAINT fk_media_assets_channel FOREIGN KEY (channel_id) REFERENCES social_channels(id),
  UNIQUE KEY uq_media_assets_storage_key (storage_key),
  KEY ix_media_assets_post (post_id), KEY ix_media_assets_status (provider_id,upload_status,processing_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  imprint MEDIUMTEXT NOT NULL,
  privacy MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS text_blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  block_key VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  is_system TINYINT(1) NOT NULL DEFAULT 0 CHECK (is_system IN (0,1)),
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_text_blocks_key (block_key),
  KEY ix_text_blocks_order (sort_order,title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0 CHECK (enabled IN (0,1)),
  smtp_host VARCHAR(255) NOT NULL DEFAULT '',
  smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
  encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
  smtp_username VARCHAR(255) NOT NULL DEFAULT '',
  smtp_password_encrypted TEXT NULL,
  from_email VARCHAR(254) NOT NULL DEFAULT '',
  from_name VARCHAR(255) NOT NULL DEFAULT '',
  reply_to VARCHAR(254) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CHECK (id = 1),
  CHECK (smtp_port BETWEEN 1 AND 65535)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaign / engagement module (also available as migration 011).
CREATE TABLE IF NOT EXISTS campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 base_reply_text TEXT NOT NULL,
 status ENUM('draft','ready','partially_published','published','cancelled') NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NOT NULL,
 platform_settings_json JSON NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 review_json JSON NULL,
 review_token_hash CHAR(64) NULL,
 review_expires_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS engagement_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 created_by BIGINT UNSIGNED NOT NULL,
 provider_id VARCHAR(50) NOT NULL,
 social_account_id BIGINT UNSIGNED NULL,
 external_author_id VARCHAR(255) NULL,
 author_display_name VARCHAR(255) NOT NULL,
 external_post_id VARCHAR(500) NOT NULL,
 external_post_urn VARCHAR(500) NULL,
 external_post_url VARCHAR(2048) NULL,
 dedupe_key CHAR(64) NOT NULL,
 post_excerpt TEXT NOT NULL,
 published_at DATETIME NULL,
 discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 relevance_score SMALLINT UNSIGNED NULL CHECK(relevance_score <= 100),
 relevance_reason VARCHAR(500) NULL,
 relevance_source VARCHAR(50) NULL,
 status ENUM('new','selected','edited','commented','ignored') NOT NULL DEFAULT 'new',
 metadata_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES users(id),
 FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE SET NULL,
 UNIQUE KEY uq_engagement_source (created_by,provider_id,dedupe_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS campaign_targets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 engagement_item_id BIGINT UNSIGNED NOT NULL,
 provider_id VARCHAR(50) NOT NULL,
 social_account_id BIGINT UNSIGNED NULL,
 external_post_id VARCHAR(500) NOT NULL,
 external_post_urn VARCHAR(500) NULL,
 author_display_name VARCHAR(255) NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 reply_text TEXT NOT NULL,
 reply_is_customized TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('draft','ready','publishing','published','failed','disabled') NOT NULL DEFAULT 'draft',
 external_comment_id VARCHAR(500) NULL,
 published_at DATETIME NULL,
 error_code VARCHAR(100) NULL,
 error_message VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
 FOREIGN KEY (engagement_item_id) REFERENCES engagement_items(id),
 FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE SET NULL,
 UNIQUE KEY uq_campaign_item (campaign_id,engagement_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
