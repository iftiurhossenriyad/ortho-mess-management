# Social Sign-in Setup

The sign-in page currently offers Google and GitHub. Each provider requires an OAuth application; credentials are loaded from outside the repository and must remain private. Facebook is intentionally excluded from the sign-in interface and startup configuration.

Save the Google Web client JSON outside the repository as:

`%LOCALAPPDATA%\OrthoMessManagement\credentials\google-client.json`

For GitHub, save a JSON file with a `web` object containing `client_id`, `client_secret`, and a `redirect_uris` array at:

- `%LOCALAPPDATA%\OrthoMessManagement\credentials\oauth-client-github.json`

The callback URI in each file must match the local callback URL listed below. Create the `credentials` folder if it does not exist. Never paste a client secret into chat.

Run this from PowerShell in the project folder:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-with-oauth.ps1
```

The launcher reads configured client IDs and secrets only into the app process. Enter the public app base URL (use `http://localhost:8000` for local testing). If the GitHub JSON file is absent, it prompts for a GitHub Client ID and hidden Client Secret; leave the ID empty to skip GitHub. For a deployed site, use its HTTPS URL as the base and register the matching HTTPS callback URLs below.

For local development, register these exact redirect URIs:

- Google: `http://localhost:8000/oauth.php?provider=google`
- GitHub: `http://localhost:8000/oauth.php?provider=github`

Provider registration:

- **Google:** In Google Cloud Console, configure the OAuth consent screen and create an OAuth Client ID with application type **Web application**. Add `http://localhost:8000` as an authorized JavaScript origin and the Google redirect URI above as an authorized redirect URI. During testing, add your Google account as a test user if the consent screen is in testing mode.
- **GitHub:** In GitHub **Settings -> Developer settings -> OAuth Apps**, create an OAuth App and set its Authorization callback URL to the GitHub redirect URI above.

Required provider permissions:

- Google: OpenID, email, and profile
- GitHub: read user profile and user email

Create or join a mess with the registration form first. For the initial setup only, use the account's Mess ID, User ID/phone, and password to open the dashboard, then use **Connect a social sign-in** to link a provider. After linking, use that social provider from the main sign-in page for future visits. For security, a social profile alone cannot create a manager or member account, join a mess, or grant dashboard access.

Google currently starts in **Testing** mode: only Google accounts listed as test users can sign in. Add each intended tester in **Google Auth Platform -> Audience -> Add users**. Making Google sign-in available to the general public requires a public HTTPS site and any Google verification required for the consent screen and scopes; localhost testing does not publish the app.

## First-time Social Account Linking

The main sign-in page uses Google and GitHub only. Local credentials are retained only for first-time social-account linking; use the phone number registered to the account and its password at `http://localhost:8000/local_login.html` to link a provider from the dashboard. To recover access later, sign in with the same provider account already linked to your Ortho account. Local-password recovery is not supported.

The application database starts empty, and users set their own password when they register. There is no shared or built-in default password.
