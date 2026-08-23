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
