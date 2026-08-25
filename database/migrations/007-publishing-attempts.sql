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
