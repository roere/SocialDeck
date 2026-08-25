ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS link_title VARCHAR(399) NULL AFTER link_url,
  ADD COLUMN IF NOT EXISTS link_description VARCHAR(4085) NULL AFTER link_title;

ALTER TABLE post_targets
  ADD COLUMN IF NOT EXISTS link_title VARCHAR(399) NULL AFTER final_link_url,
  ADD COLUMN IF NOT EXISTS link_description VARCHAR(4085) NULL AFTER link_title;
