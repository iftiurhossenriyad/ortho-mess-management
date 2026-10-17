# Ortho Mess Management

A PHP and MySQL app for managing shared mess meals, expenses, deposits, member balances, and meal history.

## Requirements

- PHP 8.1+ with `mysqli` and `curl` enabled
- MySQL 8+ or a compatible MariaDB server
- A browser with JavaScript enabled

## Local setup (Windows / XAMPP)

1. Open PowerShell in the project folder and start the local app:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start.ps1
   ```

   The process-scoped execution-policy setting lets the downloaded PowerShell launcher run without changing Windows' system policy. The launcher expects XAMPP at `D:\XAMPP`. It starts XAMPP MariaDB on port `3306` and creates the empty app schema from `database.sql` if needed. If XAMPP is installed elsewhere, update the executable paths at the top of `start.ps1`.
2. Visit `http://localhost:8000/login.html`, choose **Create or join a mess**, and create a mess manager account. Members can join using the invite code shown when the mess is created. Managers can also add members from the dashboard; each new member receives a temporary password that should be shared privately for initial social-account linking.

The dashboard is served from `index.html`; opening it without a valid session redirects to the social sign-in page. Use local credentials only for initial account setup and linking a social provider; social sign-in is the normal way to return.

## Optional services

- Social sign-in: Google and GitHub are available from the sign-in page. Follow [oauth-setup.md](./oauth-setup.md) to configure provider apps and use `start-with-oauth.ps1`; then connect a provider from the signed-in dashboard. Facebook sign-in is currently excluded from the interface. Keep client credentials out of the repository.
- Account recovery: sign in with the same Google or GitHub account already linked to your Ortho account. See [oauth-setup.md](./oauth-setup.md). SMS recovery is not supported.

## Data handling

The application database starts empty. Only messes and accounts created through registration (and activity subsequently entered by those accounts) are stored; there are no bundled or imported user records. The default database name is deliberately separate from the older `ortho_db` database so existing local databases are not reused. Back up any data you want to keep before changing or deleting any database.

## Deployment

The PHP built-in server and the included XAMPP launcher are for local development only. For public hosting, use a production PHP web server with HTTPS, set database and provider credentials in the hosting environment, and restrict database access to the application host. Never commit `.env` files, credentials, private keys, database exports, or user data.
