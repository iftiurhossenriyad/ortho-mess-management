<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/app_config.php';
start_app_session();

$provider_value = $_GET['provider'] ?? '';
$provider = is_string($provider_value) ? strtolower($provider_value) : '';
$configuration_error = false;
try {
    $google_client_id = app_config_value('OAUTH_GOOGLE_CLIENT_ID');
    $google_client_secret = app_config_value('OAUTH_GOOGLE_CLIENT_SECRET');
    $github_client_id = app_config_value('OAUTH_GITHUB_CLIENT_ID');
    $github_client_secret = app_config_value('OAUTH_GITHUB_CLIENT_SECRET');
} catch (UnexpectedValueException $error) {
    $configuration_error = true;
}
if ($configuration_error) {
    http_response_code(500);
    exit('Social sign-in configuration is invalid.');
}
$providers = [
    'google' => [
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token'
    ],
    'github' => [
        'client_id' => $github_client_id,
        'client_secret' => $github_client_secret,
        'authorize' => 'https://github.com/login/oauth/authorize',
        'token' => 'https://github.com/login/oauth/access_token'
    ]
];

function fail_login(string $error): void {
    $provider_value = $_GET['provider'] ?? '';
    $provider = is_string($provider_value) ? strtolower($provider_value) : '';
    $query = ['error' => $error];
    if ($provider) $query['provider'] = $provider;
    if (!empty($_SESSION['oauth_link_user_id'])) {
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_link_user_id']);
        header('Location: index.html?' . http_build_query(['social_error' => $error]));
        exit();
    }
    header('Location: login.html?' . http_build_query($query));
    exit();
}

function callback_url(string $provider): string {
    try {
        $base = app_config_value('OAUTH_BASE_URL', 'http://localhost:8000');
    } catch (UnexpectedValueException $error) {
        http_response_code(500);
        exit('Social sign-in configuration is invalid.');
    }
    $base = rtrim($base, '/');
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

if (isset($_GET['error'])) fail_login('oauth_failed');

if (!isset($_GET['code'])) {
    unset($_SESSION['oauth_link_user_id']);
    if (($_GET['link'] ?? '') === '1') {
        if (empty($_SESSION['local_user_id'])) {
            header('Location: local_login.html');
            exit();
        }
        require_once 'db.php';
        $link_user = require_local_user();
        if (!empty($_SESSION['is_impersonating'])) {
            header('Location: index.html?social_error=oauth_failed');
            exit();
        }
        $_SESSION['oauth_link_user_id'] = $link_user['id'];
    }
    if (!$config['client_id'] || !$config['client_secret']) fail_login('provider_not_configured');
    $_SESSION['oauth_state'] = bin2hex(random_bytes(24));
    $_SESSION['oauth_provider'] = $provider;
    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'state' => $_SESSION['oauth_state'],
        'scope' => $provider === 'github' ? 'read:user user:email' : 'openid email profile'
    ];
    header('Location: ' . $config['authorize'] . '?' . http_build_query($params));
    exit();
}

$authorization_code = $_GET['code'];
if (!is_string($authorization_code) || $authorization_code === '') {
    fail_login('oauth_failed');
}

$callback_state = $_GET['state'] ?? '';
if (!is_string($callback_state)
    || !hash_equals($_SESSION['oauth_state'] ?? '', $callback_state)
    || ($_SESSION['oauth_provider'] ?? '') !== $provider) {
    fail_login('invalid_state');
}
$link_user_id = isset($_SESSION['oauth_link_user_id']) ? (int) $_SESSION['oauth_link_user_id'] : null;
unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

$token_post = [
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'code' => $authorization_code,
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
    $name = ($profile['name'] ?? '') ?: ($profile['login'] ?? 'Ortho Member');
    $avatar = $profile['avatar_url'] ?? '';
}

if (!$provider_id) fail_login('oauth_failed');
require_once 'db.php';
$table_result = $conn->query("CREATE TABLE IF NOT EXISTS auth_accounts (
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
if (!$table_result) fail_login('oauth_failed');

$stmt = $conn->prepare("SELECT id, user_id FROM auth_accounts WHERE provider = ? AND provider_id = ?");
if (!$stmt) fail_login('oauth_failed');
$stmt->bind_param('ss', $provider, $provider_id);
if (!$stmt->execute()) fail_login('oauth_failed');
$account = $stmt->get_result()->fetch_assoc();

if ($link_user_id !== null) {
    $current_user = require_local_user();
    if ($current_user['id'] !== $link_user_id || !empty($_SESSION['is_impersonating'])) {
        fail_login('oauth_failed');
    }
    if ($account && $account['user_id'] && (int) $account['user_id'] !== $link_user_id) {
        fail_login('social_account_linked');
    }

    if ($account) {
        $update = $conn->prepare("UPDATE auth_accounts SET email = ?, name = ?, avatar = ?, user_id = ? WHERE id = ?");
        if (!$update) fail_login('oauth_failed');
        $account_id = (int) $account['id'];
        $update->bind_param('sssii', $email, $name, $avatar, $link_user_id, $account_id);
        if (!$update->execute()) fail_login('oauth_failed');
    } else {
        $insert = $conn->prepare("INSERT INTO auth_accounts (provider, provider_id, email, name, avatar, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$insert) fail_login('oauth_failed');
        $insert->bind_param('sssssi', $provider, $provider_id, $email, $name, $avatar, $link_user_id);
        if (!$insert->execute()) fail_login('oauth_failed');
    }
    unset($_SESSION['oauth_link_user_id']);
    header('Location: index.html?linked=' . rawurlencode($provider));
    exit();
}

if (!$account || empty($account['user_id'])) fail_login('social_not_linked');
$user_id = (int) $account['user_id'];
$user_stmt = $conn->prepare("SELECT u.id, u.mess_id, u.name, u.role, m.mess_code FROM users u JOIN messes m ON m.id = u.mess_id WHERE u.id = ?");
if (!$user_stmt) fail_login('oauth_failed');
$user_stmt->bind_param('i', $user_id);
if (!$user_stmt->execute()) fail_login('oauth_failed');
$user = $user_stmt->get_result()->fetch_assoc();
if (!$user) fail_login('social_not_linked');

session_regenerate_id(true);
unset($_SESSION['admin_user_id'], $_SESSION['admin_mess_id'], $_SESSION['admin_role'], $_SESSION['admin_name'], $_SESSION['admin_mess_code'], $_SESSION['is_impersonating']);
$_SESSION['local_user_id'] = (int) $user['id'];
$_SESSION['local_mess_id'] = (int) $user['mess_id'];
$_SESSION['local_mess_code'] = $user['mess_code'];
$_SESSION['local_role'] = $user['role'];
$_SESSION['local_name'] = $user['name'];
header('Location: index.html?login=success');
exit();
?>
