# Ortho Mess Management

A PHP and MySQL app for managing shared mess meals, expenses, deposits, member balances, and meal history.

## Requirements

- PHP 8.1+ with `mysqli` and `curl` enabled
- MySQL 8+ or a compatible MariaDB server
- A browser with JavaScript enabled

## Local setup (Windows / XAMPP)

1. Start MySQL in XAMPP.
2. Create the empty application database and tables by importing `database.sql` in phpMyAdmin. It creates a separate `ortho_mess_management` database and inserts no sample accounts, users, meals, or transactions. From PowerShell, you can also run:

   ```powershell
   & $env:ComSpec /c '"D:\XAMPP\mysql\bin\mysql.exe" -u root < "database.sql"'
   ```

   If your MySQL root account has a password, add `-p` and enter it when prompted.
3. Open PowerShell in the project folder. Configure the database environment variables if your local MySQL settings differ from the defaults:

   ```powershell
   $env:ORTHO_DB_HOST = '127.0.0.1'
   $env:ORTHO_DB_PORT = '3306'
   $env:ORTHO_DB_USER = 'root'
   $env:ORTHO_DB_PASSWORD = ''
   $env:ORTHO_DB_NAME = 'ortho_mess_management'
   ```

4. Start the local PHP server:

   ```powershell
   .\start.ps1
   ```

   The launcher expects XAMPP at `D:\XAMPP`. If yours is installed elsewhere, update the executable paths at the top of `start.ps1`.
5. Visit `http://localhost:8000/login.html`, choose **Create a new account**, and create a mess manager account. Members can join using the invite code shown when the mess is created. Managers can also add members from the dashboard; each new member receives a temporary password that should be shared privately and changed after the first sign-in.

The dashboard is served from `index.html`; opening it without a valid session redirects to local sign-in.

## Optional services

- Social sign-in: follow [oauth-setup.md](./oauth-setup.md), set provider credentials as environment variables, then connect a provider from inside the signed-in dashboard. Do not put real credentials in source files.
- SMS password recovery: follow [sms-setup.md](./sms-setup.md) and configure Twilio credentials in the same PowerShell session used to start PHP.

## Data handling

The application database starts empty. Only messes and accounts created through registration (and activity subsequently entered by those accounts) are stored; there are no bundled or imported user records. The default database name is deliberately separate from the older `ortho_db` database so existing local databases are not reused. Back up any data you want to keep before changing or deleting any database.

## Deployment

The PHP built-in server and the included XAMPP launcher are for local development only. For public hosting, use a production PHP web server with HTTPS, set database and provider credentials in the hosting environment, and restrict database access to the application host. Never commit `.env` files, credentials, private keys, database exports, or user data.
