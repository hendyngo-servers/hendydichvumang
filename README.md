# HENDY Cybertech PHP Catalog

PHP 8.3 + MySQL website with member registration/login, administrator dashboard, product CRUD and screenshot uploads.

## Configure
Import database.sql into MySQL/MariaDB. Set DB_HOST, DB_PORT (default 3306), DB_NAME, DB_USER and DB_PASSWORD in the service environment. Set ADMIN_EMAIL and ADMIN_PASSWORD on first boot; when no administrator exists, the app creates one with a hashed password. Remove ADMIN_PASSWORD after the first admin is created. Never commit production credentials.

## Docker
docker build -t hendydichvumang .
docker run --rm -p 8080:80 -e DB_HOST=host.docker.internal -e DB_NAME=hendydichvumang -e DB_USER=your_user -e DB_PASSWORD=your_password -e ADMIN_EMAIL=admin@example.com -e ADMIN_PASSWORD=use-a-long-random-password hendydichvumang

## Render
Create a Docker Web Service, connect a reachable MySQL-compatible database, import database.sql and configure the environment variables in the Render dashboard. Mount a persistent disk at /var/www/html/uploads if uploaded images must survive redeploys.

Security: PDO prepared statements, escaped output, password hashing, session rotation, HttpOnly/SameSite cookies, CSRF tokens, admin-only mutations, POST-only deletion, MIME/content image validation, random upload names and PHP execution denied in uploads. Use HTTPS in production.
