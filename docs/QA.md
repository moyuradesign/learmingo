# Learmingo QA and deployment checks

## Syntax checks

Run from the project root:

```bash
find app bootstrap config install routes public -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Installer checks

1. Confirm PHP 8.1+ and PDO MySQL are enabled.
2. Confirm `storage/` and `public/uploads/` are writable.
3. Open `/install` and complete all five steps.
4. Confirm `.env` is created and is not committed.
5. Confirm `storage/installed.lock` is created.
6. Confirm opening `/install` afterward returns a blocked response.
7. Confirm the administrator can log in.

## Functional smoke test

- Open `/` and `/courses`.
- Search the catalogue.
- Open a published course.
- Register as a student.
- Enroll in a published course.
- Confirm enrollment appears on `/dashboard`.
- Create an instructor account in the database or installer seed process.
- Create a course at `/instructor/courses/create`.
- Confirm admin statistics at `/admin`.
- Confirm admin user management at `/admin/users`.

## Security checks

- Confirm forms include CSRF tokens.
- Confirm passwords use `password_hash()`.
- Confirm PDO prepared statements are used for user input.
- Confirm `.env`, SQL, log, and storage files cannot be publicly downloaded.
- Confirm uploads are validated before adding upload features.

## Deployment

For Apache, point the document root to `public/` and enable `mod_rewrite`. On shared hosting without a separate document root, use the included public routing rules and protect application directories from direct access. For Nginx, route unknown requests to `public/index.php` and keep `.env` outside the public directory where possible.
