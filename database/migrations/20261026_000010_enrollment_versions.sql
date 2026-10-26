CREATE TABLE lm_enrollment_versions (
 enrollment_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 version_id BIGINT UNSIGNED NOT NULL,
 pinned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT enrollment_version_enrollment FOREIGN KEY (enrollment_id) REFERENCES lm_enrollments(id) ON DELETE RESTRICT,
 CONSTRAINT enrollment_version_version FOREIGN KEY (version_id) REFERENCES lm_course_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
