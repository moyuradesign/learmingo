CREATE TABLE lm_certificate_revocations (
 certificate_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 admin_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 revoked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT revoked_certificate FOREIGN KEY (certificate_id) REFERENCES lm_certificates(id) ON DELETE RESTRICT,
 CONSTRAINT revoking_admin FOREIGN KEY (admin_id) REFERENCES lm_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
