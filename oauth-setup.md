# Social Sign-in Setup

Ortho supports Google, GitHub, and Facebook sign-in. Each provider requires an OAuth application owned by you; its client secret cannot be generated or enabled without your provider account. Create one or more OAuth apps using the callback URLs below, then start Ortho with the interactive launcher. Client secrets are entered as hidden input and are only kept in the current PowerShell process (they are not written into project files or GitHub).

Run this from PowerShell in the project folder:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-with-oauth.ps1
```

Enter the public app base URL (use `http://localhost:8000` for local testing), then the Client ID and hidden Client Secret for each provider you created. Leave a provider's Client ID empty to skip it. For a deployed site, use its HTTPS URL as the base and register the matching HTTPS callback URLs below.

For local development, register these exact redirect URIs:

- Google: `http://localhost:8000/oauth.php?provider=google`
- GitHub: `http://localhost:8000/oauth.php?provider=github`
- Facebook: `http://localhost:8000/oauth.php?provider=facebook`

Provider registration:

- **Google:** In Google Cloud Console, configure the OAuth consent screen and create an OAuth Client ID with application type **Web application**. Add `http://localhost:8000` as an authorized JavaScript origin and the Google redirect URI above as an authorized redirect URI. During testing, add your Google account as a test user if the consent screen is in testing mode.
- **GitHub:** In GitHub **Settings -> Developer settings -> OAuth Apps**, create an OAuth App and set its Authorization callback URL to the GitHub redirect URI above.
- **Facebook:** In Meta for Developers, create an app with Facebook Login, enable Web OAuth login, and set the Valid OAuth Redirect URI to the Facebook redirect URI above. The app may need to be in Live mode and have the required permissions approved for other people to sign in.

Required provider permissions:

- Google: OpenID, email, and profile
- GitHub: read user profile and user email
- Facebook: email and public profile

Create or join a mess with the regular registration form first. Then sign in with the account's Mess ID, User ID/phone, and password. From the dashboard, use **Connect a social sign-in** to link the provider to that account. Once linked, the provider can be used from the main sign-in page. For security, a social profile alone cannot create a manager or member account, join a mess, or grant dashboard access.

## Local Dashboard Login

Use `http://localhost:8000/local_login.html` for the local role-based login. Sign in with the phone number registered to the account and its password.

The application database starts empty, and users set their own password when they register. There is no shared or built-in default password.
