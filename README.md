# Asset Prim Render DB Tester (v2)

This version uses Apache's default `/var/www/html` document root. Keep `index.php` and `.htaccess` in the repository root.

## Deploy
1. Replace the old repository files with all files in this project.
2. Commit and push to GitHub.
3. In Render, confirm the service Root Directory is blank (or `.`), and Dockerfile Path is `./Dockerfile`.
4. Trigger **Manual Deploy → Clear build cache & deploy**.
5. Add DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_SSL in Render Environment.

## Endpoints
- `/` — tester home page
- `/health` — web service health
- `/db-check` — read-only database test

Do not commit database credentials. `/db-check` exposes table names and counts, so remove or restrict this diagnostic service after testing.
