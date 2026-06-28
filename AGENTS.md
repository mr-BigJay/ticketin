# AGENTS.md

## Cursor Cloud specific instructions

This is a **PHP + MySQL/MariaDB** Persian (RTL) IT support/ticketing app ("تیکتین").
There is no Composer/npm — it is plain PHP served by the built-in dev server, with PDO talking to MariaDB.

### Services (start manually each session; the update script does NOT start them)

1. **MariaDB** — start with:
   `sudo mariadbd-safe` (run it in a background/tmux session; verify with `sudo mysqladmin ping`).
2. **PHP dev server** — from the repo root (`/workspace`):
   `php -S 0.0.0.0:8000 -t /workspace`
   App entry points: `/login.php` (users), `/jay_controller.php` (admin), `/test.php` (DB connectivity check).

### Database

- Connection settings are **hardcoded** in `includes/db.php`: db `ticketin`, user `ticketuser`, password `StrongPass123!`, host `localhost`.
- `includes/admin_helpers.php::admin_ensure_schema()` only runs `ALTER TABLE` on an already-existing `users` table; it does **not** create base tables. The full `CREATE TABLE` schema + an installer live on the **`cursor/deploy-fix-host-a1f4`** branch (`database/schema.sql`, `install.php`), not on the main/base branch.
- The `ticketin` database is already created and persisted in the MariaDB data dir. To rebuild it from scratch:
  `git show origin/cursor/deploy-fix-host-a1f4:database/schema.sql | mysql -u ticketuser -p'StrongPass123!' ticketin`
  then insert a `role='admin'` user (password via PHP `password_hash`).

### Non-obvious socket gotcha (important)

- `db.php` connects with `host=localhost`, which forces a **unix socket** connection. In this VM `/var/run` is a real directory (NOT symlinked to `/run`), so PHP's default socket path `/var/run/mysqld/mysqld.sock` does not match MariaDB's actual socket `/run/mysqld/mysqld.sock`.
- This is fixed by `/etc/php/8.3/cli/conf.d/99-mysql-socket.ini`, which points `pdo_mysql.default_socket` / `mysqli.default_socket` at `/run/mysqld/mysqld.sock`. If you see `SQLSTATE[HY000] [2002] No such file or directory`, that config is missing or MariaDB isn't running.

### Credentials (seeded for development)

- **Admin**: log in at `/jay_controller.php` with username `superadmin` / password `Admin12345`.
- **Users** log in at `/login.php` with mobile number + on-screen captcha (registration via `/register.php` creates a `pending` user that an admin must activate).

### Lint / test / build

- No automated test or build tooling. Lint = PHP syntax check:
  `find . -name '*.php' -not -path './.git/*' -exec php -l {} \;`
