CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  base_text TEXT NOT NULL,
  resolved_text TEXT NOT NULL,
  link_url VARCHAR(2048) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  status ENUM('publishing','published','failed') NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_posts_user FOREIGN KEY (created_by) REFERENCES users(id),
  KEY ix_posts_created (created_at),
  KEY ix_posts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_targets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  provider_id VARCHAR(50) NOT NULL,
  social_account_id BIGINT UNSIGNED NOT NULL,
  channel_id BIGINT UNSIGNED NOT NULL,
  final_text TEXT NOT NULL,
  final_link_url VARCHAR(2048) NULL,
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
  KEY ix_post_targets_post (post_id),
  KEY ix_post_targets_provider_status (provider_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE publishing_attempts ADD COLUMN IF NOT EXISTS post_id BIGINT UNSIGNED NULL AFTER id;
SET @has_post_fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='publishing_attempts' AND CONSTRAINT_NAME='fk_publishing_attempt_post');
SET @sql = IF(@has_post_fk=0,'ALTER TABLE publishing_attempts ADD CONSTRAINT fk_publishing_attempt_post FOREIGN KEY (post_id) REFERENCES posts(id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
