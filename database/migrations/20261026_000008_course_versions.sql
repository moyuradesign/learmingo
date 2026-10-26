CREATE TABLE lm_course_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id BIGINT UNSIGNED NOT NULL,
 version_no INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 content_sha256 CHAR(64) NOT NULL,
 published_by BIGINT UNSIGNED NOT NULL,
 published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY version_per_course (course_id,version_no),
 CONSTRAINT course_version_course FOREIGN KEY (course_id) REFERENCES lm_courses(id) ON DELETE RESTRICT,
 CONSTRAINT course_version_reviewer FOREIGN KEY (published_by) REFERENCES lm_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
