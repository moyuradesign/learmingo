CREATE TABLE lm_version_lesson_progress (
 enrollment_id BIGINT UNSIGNED NOT NULL,
 version_lesson_id BIGINT UNSIGNED NOT NULL,
 completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (enrollment_id,version_lesson_id),
 CONSTRAINT version_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES lm_enrollments(id) ON DELETE RESTRICT,
 CONSTRAINT version_progress_lesson FOREIGN KEY (version_lesson_id) REFERENCES lm_version_lessons(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
