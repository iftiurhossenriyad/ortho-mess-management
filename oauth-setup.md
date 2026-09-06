# Social Sign-in Setup

The login portal is available at:

`http://localhost:8000/login.html`

The OAuth callback URL for local development is:

`http://localhost:8000/oauth.php?provider=google`
`http://localhost:8000/oauth.php?provider=github`
`http://localhost:8000/oauth.php?provider=facebook`

Register an OAuth app in each provider's developer console and add the matching callback URL. Then set these environment variables before starting PHP:

For Google, open **Google Cloud Console -> APIs & Services -> Credentials -> Create Credentials -> OAuth client ID**, choose **Web application**, add `http://localhost:8000` as an authorized JavaScript origin, and add the callback URL above as an authorized redirect URI.

```powershell
$env:OAUTH_BASE_URL = 'http://localhost:8000'
$env:OAUTH_GOOGLE_CLIENT_ID = 'your-google-client-id'
$env:OAUTH_GOOGLE_CLIENT_SECRET = 'your-google-client-secret'
$env:OAUTH_GITHUB_CLIENT_ID = 'your-github-client-id'
$env:OAUTH_GITHUB_CLIENT_SECRET = 'your-github-client-secret'
$env:OAUTH_FACEBOOK_CLIENT_ID = 'your-facebook-app-id'
$env:OAUTH_FACEBOOK_CLIENT_SECRET = 'your-facebook-app-secret'
```

Required provider permissions:

- Google: OpenID, email, and profile
- GitHub: read user profile and user email
- Facebook: email and public profile

After setting the variables, start the project from the same PowerShell session. The callback creates the local `auth_accounts` table automatically and stores the provider account safely without storing a provider password.

## Local Dashboard Login

Use `http://localhost:8000/local_login.html` for the local role-based login. Existing users are initialized with:

- Manager: `+8801781981789`
- Members: their saved phone number
- Initial password: `Ortho@123`

Set `ORTHO_DEFAULT_PASSWORD` before starting PHP to use a different initial password. Change the default password before sharing the app beyond your local machine.
