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

## Development status

This repository contains the initial v0.0.1 infrastructure and is being developed in small, reviewable commits. Production features such as course authoring, lessons, quizzes, enrollments, payments, certificates, and reporting will be added incrementally.

## License

Copyright © Ton Samith. This project is distributed under the applicable Envato Market license for the purchaser. See `LICENSE` for the project notice and marketplace licensing terms.