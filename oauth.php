<?php
session_start();

$provider = strtolower($_GET['provider'] ?? '');
$providers = [
    'google' => [
        'client_id' => getenv('OAUTH_GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('OAUTH_GOOGLE_CLIENT_SECRET') ?: '',
        'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token'
    ],
    'github' => [
        'client_id' => getenv('OAUTH_GITHUB_CLIENT_ID') ?: '',
        'client_secret' => getenv('OAUTH_GITHUB_CLIENT_SECRET') ?: '',
        'authorize' => 'https://github.com/login/oauth/authorize',
        'token' => 'https://github.com/login/oauth/access_token'
    ],
    'facebook' => [
        'client_id' => getenv('OAUTH_FACEBOOK_CLIENT_ID') ?: '',
        'client_secret' => getenv('OAUTH_FACEBOOK_CLIENT_SECRET') ?: '',
        'authorize' => 'https://www.facebook.com/v20.0/dialog/oauth',
        'token' => 'https://graph.facebook.com/v20.0/oauth/access_token'
    ]
];

function fail_login(string $error): void {
    $provider = strtolower($_GET['provider'] ?? '');
    $query = ['error' => $error];
    if ($provider) $query['provider'] = $provider;
    header('Location: login.html?' . http_build_query($query));
    exit();
}

function callback_url(string $provider): string {
    $base = rtrim(getenv('OAUTH_BASE_URL') ?: 'http://localhost:8000', '/');
    return $base . '/oauth.php?provider=' . rawurlencode($provider);
}

function request_json(string $url, array $post = [], array $headers = []): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers
    ]);
    if ($post) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false || $status >= 400) return [];
    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

if (!isset($providers[$provider])) fail_login('oauth_failed');
$config = $providers[$provider];
$redirect_uri = callback_url($provider);

if (!isset($_GET['code'])) {
    if (!$config['client_id'] || !$config['client_secret']) fail_login('provider_not_configured');
    $_SESSION['oauth_state'] = bin2hex(random_bytes(24));
    $_SESSION['oauth_provider'] = $provider;
    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'state' => $_SESSION['oauth_state'],
        'scope' => $provider === 'github' ? 'read:user user:email' : ($provider === 'facebook' ? 'email,public_profile' : 'openid email profile')
    ];
    header('Location: ' . $config['authorize'] . '?' . http_build_query($params));
    exit();
}

if (!hash_equals($_SESSION['oauth_state'] ?? '', $_GET['state'] ?? '') || ($_SESSION['oauth_provider'] ?? '') !== $provider) {
    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);
    fail_login('invalid_state');
}
unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

$token_post = [
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'code' => $_GET['code'],
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
];
$token = request_json($config['token'], $token_post, ['Accept: application/json']);
if (empty($token['access_token'])) fail_login('oauth_failed');
$access_token = $token['access_token'];

if ($provider === 'google') {
    $profile = request_json('https://openidconnect.googleapis.com/v1/userinfo?access_token=' . rawurlencode($access_token));
    $provider_id = $profile['sub'] ?? '';
    $email = $profile['email'] ?? '';
    $name = $profile['name'] ?? 'Ortho Member';
    $avatar = $profile['picture'] ?? '';
} elseif ($provider === 'github') {
    $profile = request_json('https://api.github.com/user', [], ['Authorization: Bearer ' . $access_token, 'User-Agent: Ortho-App']);
    $emails = request_json('https://api.github.com/user/emails', [], ['Authorization: Bearer ' . $access_token, 'User-Agent: Ortho-App']);
    $email = '';
    foreach ($emails as $entry) {
        if (!empty($entry['primary']) && !empty($entry['verified'])) { $email = $entry['email']; break; }
    }
    $provider_id = (string) ($profile['id'] ?? '');
    $name = $profile['name'] ?: ($profile['login'] ?? 'Ortho Member');
    $avatar = $profile['avatar_url'] ?? '';
} else {
    $profile = request_json('https://graph.facebook.com/v20.0/me?fields=id,name,email,picture.type(large)&access_token=' . rawurlencode($access_token));
    $provider_id = (string) ($profile['id'] ?? '');
    $email = $profile['email'] ?? '';
    $name = $profile['name'] ?? 'Ortho Member';
    $avatar = $profile['picture']['data']['url'] ?? '';
}

if (!$provider_id) fail_login('oauth_failed');
require_once 'db.php';
$conn->query("CREATE TABLE IF NOT EXISTS auth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(20) NOT NULL,
    provider_id VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    name VARCHAR(100) NOT NULL,
    avatar VARCHAR(500) NOT NULL DEFAULT '',
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY provider_account (provider, provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmt = $conn->prepare("SELECT id, user_id FROM auth_accounts WHERE provider = ? AND provider_id = ?");
$stmt->bind_param('ss', $provider, $provider_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if ($account) {
    $account_id = (int) $account['id'];
    $user_id = $account['user_id'] ? (int) $account['user_id'] : null;
    $update = $conn->prepare("UPDATE auth_accounts SET email = ?, name = ?, avatar = ? WHERE id = ?");
    $update->bind_param('sssi', $email, $name, $avatar, $account_id);
    $update->execute();
} else {
    $insert = $conn->prepare("INSERT INTO auth_accounts (provider, provider_id, email, name, avatar) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param('sssss', $provider, $provider_id, $email, $name, $avatar);
    $insert->execute();
    $account_id = $insert->insert_id;
    $user_id = null;
}

$_SESSION['auth_account_id'] = $account_id;
$_SESSION['auth_provider'] = $provider;
$_SESSION['auth_name'] = $name;
$_SESSION['auth_email'] = $email;
$_SESSION['auth_user_id'] = $user_id;
header('Location: index.html?login=success');
exit();
?>
