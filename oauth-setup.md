# Social Sign-in Setup

Ortho supports Google, GitHub, and Facebook sign-in. Each provider requires an OAuth application owned by you; its client secret cannot be generated or enabled without your provider account. Create one or more OAuth apps using the callback URLs below, then start Ortho with the interactive launcher. Google, GitHub, and Facebook credentials are read from JSON files in your Windows user profile, outside the repository. Keep these files private and never add them to the project or GitHub. If a GitHub or Facebook JSON file is absent, the launcher can instead prompt for the client ID and enter the secret as hidden input for that session.

Save the Google Web client JSON outside the repository as:

`%LOCALAPPDATA%\OrthoMessManagement\credentials\google-client.json`

For GitHub or Facebook, save a JSON file with a `web` object containing `client_id`, `client_secret`, and a `redirect_uris` array at:

- `%LOCALAPPDATA%\OrthoMessManagement\credentials\oauth-client-github.json`
- `%LOCALAPPDATA%\OrthoMessManagement\credentials\oauth-client-facebook.json`

The callback URI in each file must match the local callback URL listed below. Create the `credentials` folder if it does not exist. Never paste a client secret into chat.

Run this from PowerShell in the project folder:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-with-oauth.ps1
```

The launcher reads configured client IDs and secrets only into the app process. Enter the public app base URL (use `http://localhost:8000` for local testing). It can also configure GitHub and Facebook interactively when their JSON files are absent: enter each provider's Client ID and its hidden Client Secret when prompted; leave the ID empty to skip that provider. For a deployed site, use its HTTPS URL as the base and register the matching HTTPS callback URLs below.

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

Create or join a mess with the registration form first. For the initial setup only, use the account's Mess ID, User ID/phone, and password to open the dashboard, then use **Connect a social sign-in** to link a provider. After linking, use that social provider from the main sign-in page for future visits. For security, a social profile alone cannot create a manager or member account, join a mess, or grant dashboard access.

Google currently starts in **Testing** mode: only Google accounts listed as test users can sign in. Add each intended tester in **Google Auth Platform -> Audience -> Add users**. Making Google sign-in available to the general public requires a public HTTPS site and any Google verification required for the consent screen and scopes; localhost testing does not publish the app.

## First-time Social Account Linking

The main sign-in page uses Google, GitHub, or Facebook only. Local credentials are retained for first-time social-account linking and account recovery; use the phone number registered to the account and its password at `http://localhost:8000/local_login.html`. Once a provider is linked from the dashboard, use social sign-in on later visits.

The application database starts empty, and users set their own password when they register. There is no shared or built-in default password.
