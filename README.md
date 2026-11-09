# Asset Prim — Render DB Tester

A small PHP 8.3 + Apache service for testing a remote MySQL/MariaDB database from Render. It does not modify database data.

## Deploy

1. Upload this folder to a GitHub repository.
2. In Render, choose **New + → Web Service** and connect the repository.
3. Select **Docker** runtime (Render should detect the Dockerfile).
4. Deploy.
5. In Render → your service → **Environment**, add the variables below.
6. Save changes and redeploy if prompted.

## Required Render environment variables

| Key | Value |
|---|---|
| `DB_HOST` | Filess database hostname only (no `https://`) |
| `DB_PORT` | Port shown by Filess |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |
| `DB_CHARSET` | `utf8mb4` |
| `DB_SSL` | `false` unless Filess explicitly requires TLS |

If Filess supplies a CA certificate and requires certificate verification, set `DB_SSL=true` and `DB_SSL_CA` to the certificate file path. Do not guess the port or TLS settings; use the exact values shown in the Filess control panel.

## Test endpoints

- `/health` — confirms the Render web service is running.
- `/db-check` — connects to the remote database and returns server version, table names/counts, query timings and estimated storage.

`/db-check` performs read-only queries (`SELECT`, `SHOW TABLES`, `COUNT(*)`). It does not insert, update, delete, or create tables.

## Security notes

- Never commit `.env` or database credentials to GitHub.
- Keep `APP_DEBUG=false` in production.
- This is a diagnostic endpoint. After testing, remove the service or restrict access before using it for anything beyond a temporary test. The endpoint exposes table names and approximate row counts to anyone who can access its URL.
- This service is not the full Asset Prim application.
