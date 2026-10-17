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

After setting the variables, start the project from the same PowerShell session. First sign in with the account's Mess ID, User ID/phone, and password. From the dashboard, use **Connect a social sign-in** to link the provider to that account. Once linked, the provider can be used from the main sign-in page. A social profile by itself does not create a mess account or grant dashboard access.

## Local Dashboard Login

Use `http://localhost:8000/local_login.html` for the local role-based login. Sign in with the phone number registered to the account and its password.

The application database starts empty, and users set their own password when they register. There is no shared or built-in default password.
