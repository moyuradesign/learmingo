# Learmingo security and production deployment

## Security controls

The v0.0.1 core-hardening patch provides strict, cookie-only PHP sessions; HttpOnly and SameSite=Lax cookies; Secure cookies for public HTTPS deployments; a 30-minute idle timeout; a 12-hour absolute session lifetime; and session ID renewal every 15 minutes. Login and logout rotate CSRF tokens. Current account status and role are rechecked against the database on authenticated requests.

Login throttling: 6 attempts per email account and 30 attempts per server REMOTE_ADDR in a 15-minute window. Registration throttling: 5 attempts per server REMOTE_ADDR per hour. Counters are locked private JSON files under storage/security/rate-limits, outside the public document root. Do not trust client-supplied X-Forwarded-For unless you separately implement trusted proxy configuration. This is not a substitute for a WAF or distributed rate limiter.

Security headers include Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy and no-store. HSTS is emitted for direct HTTPS requests; configure HSTS on your TLS proxy if PHP is behind HTTPS termination. Exceptions display generic 500 responses rather than PHP stack traces.

Admin status controls protect administrator accounts, including the last admin. Registration always creates student roles. Paid self-enrollment is disabled until verified checkout exists. Review historical paid course enrollments before deployment because the previous endpoint did not enforce payment.

## Fresh installation

1. Back up your data. Point the domain document root to the public/ directory. Keep .env, storage/, install/, and database/ outside public/.
2. Create writable storage/ and public/uploads/ directories for the PHP-FPM user. Use restricted permissions such as 0700 for private storage and 0600 for private files.
3. Enable HTTPS. Open /install/ after confirming the new code is deployed and the site is not already installed.
4. A private 64-character token is generated at storage/setup.token. Read it from the server terminal or aaPanel's private file manager. Enter it in the installer form over HTTPS, **never as a URL parameter**.
5. Verify requirements, test a least-privilege database connection, configure the app URL and timezone, and create the admin with a 12+ character password.
6. Completion writes .env and storage/installed.lock exclusively. An existing .env or lock blocks installation. The installer never overwrites a preexisting admin identity.
7. Disable /install/ at the web-server level after installation and leave the installed.lock in place.

Fresh-install recovery: MySQL schema DDL is not transactional. If a fresh install partially fails, review the database and private setup files and restore from backup before retrying. Never remove .env or installed.lock to rerun installation on a live site. Upgrades must preserve the existing .env, installed.lock, storage data, and user media.

## Apache

Set DocumentRoot to the application's public/ directory and enable mod_rewrite with AllowOverride All. The repository provides public/.htaccess for the front controller, installer mapping and dangerous file denials, plus public/uploads/.htaccess that denies executable script extensions.

## Nginx / aaPanel

Adapt this server-block location example. The PHP-FPM socket and site root MUST be checked against your aaPanel installation before enabling it:

~~~nginx
root /www/wwwroot/learmingo.tonsamith.com/public;
index index.php;
autoindex off;

location / { try_files $uri $uri/ /index.php?$query_string; }
location = /install { return 301 /install/; }
location = /install/ { rewrite ^ /install/index.php last; }

location ~* ^/uploads/.*\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|asp|aspx)$ { return 403; }
location ~* /\.(?!well-known/) { return 403; }
location ~* \.(?:env|sql|log|ini|bak|zip|phar)$ { return 403; }
location ~ ^/(index|install/index)\.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/tmp/php-cgi-84.sock;
}
location ~ \.php$ { return 403; }
~~~

After installation, disable installer access with a location such as:
    location ^~ /install { return 403; }

CAUTION: Adapt the example to your existing aaPanel includes and PHP configuration; never blindly replace a working production Nginx config.

## Regression and staging acceptance

Run:
~~~bash
find app bootstrap config install public resources routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/integration.php
php tests/security.php
~~~

In a staging environment, verify CSRF rejection (HTTP 419), brute-force throttling (HTTP 429), Secure/HttpOnly/SameSite cookies, admin deactivation guard, paid enrollment denial for new enrollments, current-role revalidation after account deactivation, and login/register/course function flows. Confirm that uploaded PHP is neither executed nor directly downloadable, private config files return 403/404, and CSS/JS return 200.

Do not deploy directly from this security branch. Back up the database, secrets, and uploads; merge after CI; perform read-only server preflight and a staged rollout.

## Limitations

File-based throttling is suitable for modest shared-hosting traffic. For higher scale, use Redis or another centralized backend and a WAF. The new password policy applies to newly created accounts; existing hashes remain valid. Paid enrollment controls do not retroactively revoke enrollments and cannot verify earlier payments. Password recovery, verified payments, safe media uploads, certificate issuance, and monitoring need separate fully tested implementations.
