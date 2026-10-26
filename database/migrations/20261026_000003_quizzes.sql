CREATE TABLE lm_quizzes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 course_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 passing_percent TINYINT UNSIGNED NOT NULL DEFAULT 70,
 max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
 status ENUM('draft','published') NOT NULL DEFAULT 'draft',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY quiz_course_title (course_id,title),
 KEY quiz_course_status (course_id,status),
 CONSTRAINT quiz_course FOREIGN KEY (course_id) REFERENCES lm_courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
