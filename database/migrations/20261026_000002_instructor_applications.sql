CREATE TABLE lm_instructor_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 applicant_id BIGINT UNSIGNED NOT NULL,
 statement TEXT NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 reviewed_by BIGINT UNSIGNED NULL,
 review_note VARCHAR(500) NULL,
 submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY instructor_application_applicant (applicant_id),
 KEY instructor_application_status (status,submitted_at),
 CONSTRAINT instructor_application_user FOREIGN KEY (applicant_id) REFERENCES lm_users(id) ON DELETE RESTRICT,
 CONSTRAINT instructor_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES lm_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
