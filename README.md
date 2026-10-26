# Learmingo

Learmingo is a modern Learning Management System built with pure PHP, MySQL/MariaDB, and Bootstrap 5. It is designed for shared hosting, VPS, and dedicated servers.

- **Version:** v0.0.1
- **Author:** Ton Samith
- **Author website:** https://tonsamith.com
- **Project website:** https://learmingo.tonsamith.com
- **Marketplace:** CodeCanyon / Envato Market
- **License:** Envato Market License (Regular or Extended, according to the purchased license)

## Project goals

Learmingo provides a maintainable foundation for administrators, instructors, and students. The initial infrastructure includes a front controller, lightweight routing, configuration loading, PDO database access, CSRF protection, session authentication, role checks, validation helpers, a five-step installer, database schema, Bootstrap-ready views, and deployment documentation.

## Requirements

- PHP 8.1 or newer
- MySQL 8.0+ or MariaDB 10.5+
- PDO and PDO MySQL
- OpenSSL, JSON, Mbstring, Fileinfo, and Ctype extensions
- Apache with mod_rewrite or Nginx with a PHP-FPM location
- Writable `storage/` and `public/uploads/` directories

## Installation

1. Copy the package to the web server.
2. Point the document root to `public/` when possible.
3. Make `storage/` and `public/uploads/` writable by the web server.
4. Open `/install` in a browser.
5. Complete the five steps: requirements, database, application configuration, administrator, and completion.
6. Remove or disable installer access after installation. Learmingo creates an installation lock in `storage/installed.lock`.

## Security hardening

The core includes stronger session security, CSRF token rotation, login/registration throttling, live authorization revalidation and a private setup-token installer. The installer requires a server-side token from `storage/setup.token` on fresh installations. See [Security & deployment](docs/SECURITY.md) before upgrading or deploying. Paid self-enrollment is disabled until verified checkout is implemented.

## Development status

Development now includes instructor-owned draft courses, section/lesson authoring and ordering, admin course review, student progress, and transactional activity records. The infrastructure milestone adds manual SHA256-ledger SQL migrations. See [Learning core](docs/LEARNING_CORE.md) for exact capabilities and deferred modules. This is not yet a fully accepted production release: database migrations and real aaPanel QA must pass before deployment. Paid checkout and verified payment entitlements remain future milestones. Milestone 3 adds instructor applications and review, server-graded MCQ assessments, student learning records, verifiable certificates, and immutable course versions with detached instructor revisions. Its migrations are manual and must be approved in isolated QA before deployment. Advanced draft assessments support true/false and multiple-correct-answer questions, an instructor-scoped gradebook, student attempt history, printable certificates and administrator-logged certificate revocation.

## Database migrations (operator-only)

Always back up the target database and verify the backup before applying schema changes. Run read-only `php scripts/migrate.php --check`; after staging approval, use `php scripts/migrate.php --apply TARGET_DB I_HAVE_A_VERIFIED_DATABASE_BACKUP`. Migrations are checksummed and forward-only, and MySQL DDL does not roll back automatically. Never run the tests against a real customer database.

## License

Copyright © Ton Samith. This project is distributed under the applicable Envato Market license for the purchaser. See `LICENSE` for the project notice and marketplace licensing terms.