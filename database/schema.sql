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
