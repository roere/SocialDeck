CREATE TABLE IF NOT EXISTS text_blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  block_key VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_text_blocks_key (block_key),
  KEY ix_text_blocks_order (sort_order,title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
