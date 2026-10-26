CREATE TABLE lm_version_lessons (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 version_id BIGINT UNSIGNED NOT NULL,
 source_lesson_id BIGINT UNSIGNED NULL,
 section_title VARCHAR(200) NOT NULL,
 section_position INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 content LONGTEXT NULL,
 video_url VARCHAR(500) NULL,
 lesson_position INT UNSIGNED NOT NULL,
 KEY version_lesson_order (version_id,section_position,lesson_position,id),
 CONSTRAINT version_lesson_version FOREIGN KEY (version_id) REFERENCES lm_course_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
