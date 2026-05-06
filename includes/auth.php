<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

const AUTH_SESSION_COOKIE = 'panda_session';
const AUTH_CSRF_COOKIE = 'panda_csrf';
const AUTH_SESSION_TTL_SECONDS = 2592000;
const AUTH_CSRF_TTL_SECONDS = 7200;
const AUTH_GENERIC_ERROR = 'No se pudo validar la cuenta con esos datos.';
const AUTH_DISPOSABLE_EMAIL_SOURCE = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/refs/heads/main/disposable_email_blocklist.conf';
const AUTH_SECLISTS_10K_SOURCE = 'https://raw.githubusercontent.com/danielmiessler/SecLists/master/Passwords/Common-Credentials/10k-most-common.txt';
const AUTH_SECLISTS_DARKWEB_SOURCE = 'https://raw.githubusercontent.com/danielmiessler/SecLists/master/Passwords/Common-Credentials/darkweb2017-top10000.txt';
const AUTH_MIN_DISPOSABLE_DOMAINS = 1000;
const AUTH_MIN_WEAK_PASSWORDS = 1000;

function authApplySecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function authIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function authCookieOptions(int $expires, bool $httpOnly = true): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => authIsHttps(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ];
}

function authSetCookie(string $name, string $value, int $expires, bool $httpOnly = true): void
{
    setcookie($name, $value, authCookieOptions($expires, $httpOnly));
    $_COOKIE[$name] = $value;
}

function authClearCookie(string $name): void
{
    setcookie($name, '', authCookieOptions(time() - 3600));
    unset($_COOKIE[$name]);
}

function authCsrfToken(): string
{
    $token = (string) ($_COOKIE[AUTH_CSRF_COOKIE] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        authSetCookie(AUTH_CSRF_COOKIE, $token, time() + AUTH_CSRF_TTL_SECONDS);
    }

    return $token;
}

function authVerifyCsrf(array $payload): void
{
    $provided = trim((string) ($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    $cookie = (string) ($_COOKIE[AUTH_CSRF_COOKIE] ?? '');

    if ($provided === '' || $cookie === '' || !hash_equals($cookie, $provided)) {
        authSendJson(['error' => 'La sesión de seguridad ha caducado. Recarga la página.'], 419);
    }
}

function authReadPayload(): array
{
    if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    return $_POST;
}

function authSendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function authCurrentUser(?PDO $pdo = null): ?array
{
    static $cachedUser = false;

    if (is_array($cachedUser) || $cachedUser === null) {
        return $cachedUser;
    }

    $token = (string) ($_COOKIE[AUTH_SESSION_COOKIE] ?? '');

    if (!preg_match('/^[a-f0-9]{96}$/', $token)) {
        $cachedUser = null;
        return null;
    }

    $pdo ??= getDbConnection();
    authEnsureSchema($pdo);
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.display_name, u.role, u.avatar_url, s.id AS session_id
         FROM auth_sessions s
         INNER JOIN app_users u ON u.id = s.user_id
         WHERE s.token_hash = :token_hash
           AND s.revoked_at IS NULL
           AND s.expires_at > now()
           AND u.disabled_at IS NULL
           AND (u.locked_until IS NULL OR u.locked_until <= now())
         LIMIT 1'
    );
    $stmt->execute([':token_hash' => $tokenHash]);
    $user = $stmt->fetch();

    if (!is_array($user)) {
        authClearCookie(AUTH_SESSION_COOKIE);
        $cachedUser = null;
        return null;
    }

    $touch = $pdo->prepare('UPDATE auth_sessions SET last_seen_at = now() WHERE id = :id');
    $touch->execute([':id' => $user['session_id']]);
    unset($user['session_id']);
    $cachedUser = $user;

    return $cachedUser;
}

function authRequireUserJson(): array
{
    $user = authCurrentUser();

    if ($user === null) {
        authSendJson(['error' => 'Necesitas iniciar sesión.'], 401);
    }

    return $user;
}

function authRequireUserPage(string $currentPage): ?array
{
    $publicPages = ['login', 'register'];
    $user = authCurrentUser();

    if ($user === null && !in_array($currentPage, $publicPages, true)) {
        header('Location: index.php?page=login');
        exit;
    }

    if ($user !== null && in_array($currentPage, $publicPages, true)) {
        header('Location: index.php');
        exit;
    }

    return $user;
}

function authRegister(array $payload): array
{
    $pdo = getDbConnection();
    authEnsureSchema($pdo);
    authVerifyCsrf($payload);
    authCheckHoneypot($payload);

    $email = authNormalizeEmail($payload['email'] ?? '');
    $firstName = authNormalizeDisplayName($payload['first_name'] ?? '');
    $lastName = authNormalizeDisplayName($payload['last_name'] ?? '');
    $legacyDisplayName = authNormalizeDisplayName($payload['display_name'] ?? '');
    $displayName = authNormalizeDisplayName(trim($firstName . ' ' . $lastName));
    if ($displayName === '') {
        $displayName = $legacyDisplayName;
    }
    $password = (string) ($payload['password'] ?? '');
    $ip = authClientIp();

    if (!authConsumeRateLimit($pdo, 'register_ip', $ip, 5, 3600, 3600)) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Prueba más tarde.'];
    }

    if ($email !== '' && !authConsumeRateLimit($pdo, 'register_email', $email, 3, 3600, 3600)) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Prueba más tarde.'];
    }

    if (!authEmailIsAllowed($pdo, $email)) {
        return ['ok' => false, 'error' => 'Usa un email real, no temporal.'];
    }

    if ($displayName === '') {
        return ['ok' => false, 'error' => 'Escribe tu nombre.'];
    }

    $passwordError = authPasswordError($pdo, $password, $email, $displayName);

    if ($passwordError !== null) {
        return ['ok' => false, 'error' => $passwordError];
    }

    $hash = password_hash($password, PASSWORD_ARGON2ID, authPasswordOptions());

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO app_users (email, email_normalized, password_hash, display_name, role)
             VALUES (:email, :email_normalized, :password_hash, :display_name, \'guardian\')
             RETURNING id, email, display_name, role, avatar_url'
        );
        $stmt->execute([
            ':email' => $email,
            ':email_normalized' => $email,
            ':password_hash' => $hash,
            ':display_name' => $displayName,
        ]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'No se pudo crear la cuenta con esos datos.'];
    }

    $user = $stmt->fetch();

    if (!is_array($user)) {
        return ['ok' => false, 'error' => 'No se pudo crear la cuenta con esos datos.'];
    }

    authIssueSession($pdo, (string) $user['id']);

    return ['ok' => true, 'user' => $user];
}

function authLogin(array $payload): array
{
    $pdo = getDbConnection();
    authEnsureSchema($pdo);
    authVerifyCsrf($payload);
    authCheckHoneypot($payload);

    $email = authNormalizeEmail($payload['email'] ?? '');
    $password = (string) ($payload['password'] ?? '');
    $ip = authClientIp();

    if (!authConsumeRateLimit($pdo, 'login_ip', $ip, 10, 900, 900)) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Prueba más tarde.'];
    }

    if ($email !== '' && !authConsumeRateLimit($pdo, 'login_email', $email, 6, 900, 900)) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Prueba más tarde.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, email, password_hash, display_name, role, avatar_url, failed_login_count, locked_until, disabled_at
         FROM app_users
         WHERE email_normalized = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!is_array($user) || !authUserCanLogin($user) || !password_verify($password, (string) $user['password_hash'])) {
        if (is_array($user)) {
            authRecordFailedLogin($pdo, (string) $user['id'], (int) $user['failed_login_count']);
        }

        return ['ok' => false, 'error' => AUTH_GENERIC_ERROR];
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID, authPasswordOptions())) {
        $rehash = password_hash($password, PASSWORD_ARGON2ID, authPasswordOptions());
        $updateHash = $pdo->prepare('UPDATE app_users SET password_hash = :hash WHERE id = :id');
        $updateHash->execute([':hash' => $rehash, ':id' => $user['id']]);
    }

    $update = $pdo->prepare(
        'UPDATE app_users
         SET failed_login_count = 0, locked_until = NULL, last_login_at = now()
         WHERE id = :id'
    );
    $update->execute([':id' => $user['id']]);
    authIssueSession($pdo, (string) $user['id']);

    unset($user['password_hash'], $user['failed_login_count'], $user['locked_until'], $user['disabled_at']);

    return ['ok' => true, 'user' => $user];
}

function authLogout(): void
{
    $token = (string) ($_COOKIE[AUTH_SESSION_COOKIE] ?? '');

    if (preg_match('/^[a-f0-9]{96}$/', $token)) {
        $pdo = getDbConnection();
        authEnsureSchema($pdo);
        $stmt = $pdo->prepare(
            'UPDATE auth_sessions
             SET revoked_at = now()
             WHERE token_hash = :token_hash
               AND revoked_at IS NULL'
        );
        $stmt->execute([':token_hash' => hash('sha256', $token)]);
    }

    authClearCookie(AUTH_SESSION_COOKIE);
}

function authIssueSession(PDO $pdo, string $userId): void
{
    $oldToken = (string) ($_COOKIE[AUTH_SESSION_COOKIE] ?? '');

    if (preg_match('/^[a-f0-9]{96}$/', $oldToken)) {
        $revoke = $pdo->prepare('UPDATE auth_sessions SET revoked_at = now() WHERE token_hash = :token_hash');
        $revoke->execute([':token_hash' => hash('sha256', $oldToken)]);
    }

    $token = bin2hex(random_bytes(48));
    $expiresAt = gmdate('Y-m-d H:i:sP', time() + AUTH_SESSION_TTL_SECONDS);
    $stmt = $pdo->prepare(
        'INSERT INTO auth_sessions (user_id, token_hash, ip_address, user_agent, expires_at)
         VALUES (:user_id, :token_hash, :ip_address, :user_agent, :expires_at)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => hash('sha256', $token),
        ':ip_address' => authClientIp(),
        ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        ':expires_at' => $expiresAt,
    ]);

    authSetCookie(AUTH_SESSION_COOKIE, $token, time() + AUTH_SESSION_TTL_SECONDS);
    authSetCookie(AUTH_CSRF_COOKIE, bin2hex(random_bytes(32)), time() + AUTH_CSRF_TTL_SECONDS);
}

function authCheckHoneypot(array $payload): void
{
    $trapFields = ['website', 'company'];

    foreach ($trapFields as $field) {
        if (trim((string) ($payload[$field] ?? '')) !== '') {
            authSendJson(['error' => AUTH_GENERIC_ERROR], 422);
        }
    }

    $startedAt = (int) ($payload['form_started_at'] ?? 0);
    $now = time();

    if ($startedAt <= 0 || $now - $startedAt < 2 || $now - $startedAt > 7200) {
        authSendJson(['error' => AUTH_GENERIC_ERROR], 422);
    }
}

function authNormalizeEmail(mixed $value): string
{
    return strtolower(trim((string) $value));
}

function authNormalizeDisplayName(mixed $value): string
{
    $name = trim(preg_replace('/\s+/', ' ', (string) $value) ?: '');

    return substr($name, 0, 120);
}

function authEmailIsAllowed(PDO $pdo, string $email): bool
{
    if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $domain = substr(strrchr($email, '@') ?: '', 1);

    if ($domain === '' || !str_contains($domain, '.')) {
        return false;
    }

    authEnsureRealSecurityLists($pdo);

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM blocked_email_domains
         WHERE :domain_exact = domain OR :domain_suffix LIKE \'%.\' || domain
         LIMIT 1'
    );
    $stmt->execute([':domain_exact' => $domain, ':domain_suffix' => $domain]);

    return $stmt->fetchColumn() === false;
}

function authPasswordOptions(): array
{
    return [
        'memory_cost' => 65536,
        'time_cost' => 3,
        'threads' => 1,
    ];
}

function authPasswordError(PDO $pdo, string $password, string $email, string $displayName): ?string
{
    if (strlen($password) < 12) {
        return 'La contraseña debe tener al menos 12 caracteres.';
    }

    if (strlen($password) > 128) {
        return 'La contraseña es demasiado larga.';
    }

    $classes = 0;
    $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
    $classes += preg_match('/\d/', $password) ? 1 : 0;
    $classes += preg_match('/[^a-zA-Z\d]/', $password) ? 1 : 0;

    if ($classes < 3) {
        return 'Usa una mezcla de mayúsculas, minúsculas, números o símbolos.';
    }

    $lowerPassword = strtolower($password);
    $emailLocal = strtolower(strtok($email, '@') ?: '');
    $cleanName = strtolower(preg_replace('/[^a-z0-9]/i', '', $displayName) ?: '');

    if ($emailLocal !== '' && str_contains($lowerPassword, $emailLocal)) {
        return 'No uses tu email dentro de la contraseña.';
    }

    if ($cleanName !== '' && strlen($cleanName) >= 4 && str_contains($lowerPassword, $cleanName)) {
        return 'No uses tu nombre dentro de la contraseña.';
    }

    if (authPasswordInWeakList($pdo, $lowerPassword)) {
        return 'Esa contraseña aparece en listas de contraseñas débiles.';
    }

    if (authPasswordLooksPatterned($lowerPassword)) {
        return 'Evita patrones fáciles de adivinar.';
    }

    return null;
}

function authPasswordInWeakList(PDO $pdo, string $lowerPassword): bool
{
    authEnsureRealSecurityLists($pdo);
    $stmt = $pdo->prepare('SELECT 1 FROM weak_passwords WHERE password_hash = :hash LIMIT 1');
    $stmt->execute([':hash' => hash('sha256', $lowerPassword)]);

    return $stmt->fetchColumn() !== false;
}

function authPasswordLooksPatterned(string $lowerPassword): bool
{
    return (bool) preg_match('/(.)\1{3,}/', $lowerPassword)
        || str_contains($lowerPassword, '1234')
        || str_contains($lowerPassword, 'abcd')
        || str_contains($lowerPassword, 'qwer');
}

function authUserCanLogin(array $user): bool
{
    if (!empty($user['disabled_at'])) {
        return false;
    }

    if (empty($user['locked_until'])) {
        return true;
    }

    return strtotime((string) $user['locked_until']) <= time();
}

function authRecordFailedLogin(PDO $pdo, string $userId, int $currentFailures): void
{
    $nextFailures = $currentFailures + 1;
    $lockSql = $nextFailures >= 5 ? ', locked_until = now() + interval \'15 minutes\'' : '';
    $stmt = $pdo->prepare(
        'UPDATE app_users
         SET failed_login_count = :failed_login_count' . $lockSql . '
         WHERE id = :id'
    );
    $stmt->execute([
        ':failed_login_count' => $nextFailures,
        ':id' => $userId,
    ]);
}

function authConsumeRateLimit(PDO $pdo, string $action, string $identifier, int $maxAttempts, int $windowSeconds, int $lockSeconds): bool
{
    $identifierHash = hash('sha256', $action . ':' . $identifier);
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT attempts, window_start, locked_until
             FROM auth_rate_limits
             WHERE action = :action AND identifier_hash = :identifier_hash
             FOR UPDATE'
        );
        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
        ]);
        $row = $stmt->fetch();
        $now = time();

        if (!is_array($row)) {
            $insert = $pdo->prepare(
                'INSERT INTO auth_rate_limits (action, identifier_hash, attempts)
                 VALUES (:action, :identifier_hash, 1)'
            );
            $insert->execute([
                ':action' => $action,
                ':identifier_hash' => $identifierHash,
            ]);
            $pdo->commit();
            return true;
        }

        if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > $now) {
            $pdo->commit();
            return false;
        }

        $windowStart = strtotime((string) $row['window_start']);
        $attempts = ($windowStart + $windowSeconds) <= $now ? 1 : ((int) $row['attempts'] + 1);
        $lockedUntilSql = $attempts > $maxAttempts ? ', locked_until = :locked_until' : ', locked_until = NULL';
        $windowSql = $attempts === 1 ? ', window_start = now()' : '';
        $update = $pdo->prepare(
            'UPDATE auth_rate_limits
             SET attempts = :attempts' . $windowSql . $lockedUntilSql . '
             WHERE action = :action AND identifier_hash = :identifier_hash'
        );
        $params = [
            ':attempts' => $attempts,
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
        ];

        if ($attempts > $maxAttempts) {
            $params[':locked_until'] = gmdate('Y-m-d H:i:sP', $now + $lockSeconds);
        }

        $update->execute($params);
        $pdo->commit();

        return $attempts <= $maxAttempts;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function authClientIp(): string
{
    $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $candidate = trim($parts[0]);

        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function authEnsureSchema(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $ensured = true;
    $pdo->exec('ALTER TABLE app_users ADD COLUMN IF NOT EXISTS email_normalized varchar(255)');
    $pdo->exec('UPDATE app_users SET email_normalized = lower(trim(email)) WHERE email_normalized IS NULL OR email_normalized = \'\'');
    $pdo->exec('ALTER TABLE app_users ALTER COLUMN email_normalized SET NOT NULL');
    $pdo->exec('ALTER TABLE app_users ADD COLUMN IF NOT EXISTS failed_login_count integer NOT NULL DEFAULT 0');
    $pdo->exec('ALTER TABLE app_users ADD COLUMN IF NOT EXISTS locked_until timestamptz');
    $pdo->exec('ALTER TABLE app_users ADD COLUMN IF NOT EXISTS last_login_at timestamptz');
    $pdo->exec('ALTER TABLE app_users ADD COLUMN IF NOT EXISTS disabled_at timestamptz');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_app_users_email_normalized ON app_users(email_normalized)');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS auth_sessions (
            id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
            user_id uuid NOT NULL REFERENCES app_users(id) ON DELETE CASCADE,
            token_hash char(64) NOT NULL UNIQUE,
            ip_address inet,
            user_agent text,
            expires_at timestamptz NOT NULL,
            revoked_at timestamptz,
            last_seen_at timestamptz NOT NULL DEFAULT now(),
            created_at timestamptz NOT NULL DEFAULT now()
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS auth_rate_limits (
            id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
            action varchar(40) NOT NULL,
            identifier_hash char(64) NOT NULL,
            attempts integer NOT NULL DEFAULT 0,
            window_start timestamptz NOT NULL DEFAULT now(),
            locked_until timestamptz,
            updated_at timestamptz NOT NULL DEFAULT now(),
            UNIQUE (action, identifier_hash)
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS blocked_email_domains (
            domain varchar(255) PRIMARY KEY,
            source varchar(255) NOT NULL DEFAULT \'upstream\',
            created_at timestamptz NOT NULL DEFAULT now()
        )'
    );
    $pdo->exec('ALTER TABLE blocked_email_domains ALTER COLUMN source TYPE varchar(255)');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS weak_passwords (
            password_hash char(64) PRIMARY KEY,
            label varchar(255),
            created_at timestamptz NOT NULL DEFAULT now()
        )'
    );
    $pdo->exec('ALTER TABLE weak_passwords ALTER COLUMN label TYPE varchar(255)');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS auth_security_list_sources (
            source_key varchar(80) PRIMARY KEY,
            source_url text NOT NULL,
            item_count integer NOT NULL DEFAULT 0,
            synced_at timestamptz,
            created_at timestamptz NOT NULL DEFAULT now()
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_sessions_user_active ON auth_sessions(user_id, expires_at DESC) WHERE revoked_at IS NULL');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_rate_limits_lookup ON auth_rate_limits(action, identifier_hash)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_security_list_sources_synced ON auth_security_list_sources(synced_at DESC)');
    $pdo->exec('ALTER TABLE practice_sets ADD COLUMN IF NOT EXISTS user_id uuid REFERENCES app_users(id) ON DELETE SET NULL');
}

function authEnsureRealSecurityLists(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $domainCount = (int) $pdo->query('SELECT count(*) FROM blocked_email_domains')->fetchColumn();
    $passwordCount = (int) $pdo->query('SELECT count(*) FROM weak_passwords')->fetchColumn();

    if ($domainCount >= AUTH_MIN_DISPOSABLE_DOMAINS && $passwordCount >= AUTH_MIN_WEAK_PASSWORDS) {
        return;
    }

    authSyncSecurityLists($pdo, false);
}

function authSyncSecurityLists(PDO $pdo, bool $force = true): array
{
    authEnsureSchema($pdo);

    $results = [
        'blocked_email_domains' => 0,
        'weak_passwords' => 0,
    ];

    $domainCount = (int) $pdo->query('SELECT count(*) FROM blocked_email_domains')->fetchColumn();
    if ($force || $domainCount < AUTH_MIN_DISPOSABLE_DOMAINS) {
        $domains = authFetchListLines(AUTH_DISPOSABLE_EMAIL_SOURCE, 'domain');
        if ($domains !== []) {
            $results['blocked_email_domains'] = authReplaceBlockedEmailDomains($pdo, $domains, AUTH_DISPOSABLE_EMAIL_SOURCE);
        }
    }

    $passwordCount = (int) $pdo->query('SELECT count(*) FROM weak_passwords')->fetchColumn();
    if ($force || $passwordCount < AUTH_MIN_WEAK_PASSWORDS) {
        $passwords = array_merge(
            authFetchListLines(AUTH_SECLISTS_10K_SOURCE, 'password'),
            authFetchListLines(AUTH_SECLISTS_DARKWEB_SOURCE, 'password')
        );

        if ($passwords !== []) {
            $results['weak_passwords'] = authReplaceWeakPasswords($pdo, $passwords, 'SecLists Common-Credentials');
        }
    }

    return $results;
}

function authFetchListLines(string $sourceUrl, string $kind): array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: TofiMatesAuthListSync/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($sourceUrl, false, $context);

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $items = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $item = strtolower(trim($line));

        if ($item === '' || str_starts_with($item, '#') || str_starts_with($item, 'source url:') || str_starts_with($item, 'title:')) {
            continue;
        }

        if ($kind === 'domain') {
            $item = preg_replace('/[^a-z0-9.-]/', '', $item) ?: '';

            if ($item === '' || !str_contains($item, '.')) {
                continue;
            }
        }

        if ($kind === 'password' && strlen($item) > 128) {
            continue;
        }

        $items[$item] = true;
    }

    return array_keys($items);
}

function authReplaceBlockedEmailDomains(PDO $pdo, array $domains, string $sourceUrl): int
{
    $pdo->beginTransaction();

    try {
        $pdo->exec('TRUNCATE blocked_email_domains');
        $stmt = $pdo->prepare(
            'INSERT INTO blocked_email_domains (domain, source)
             VALUES (:domain, :source)
             ON CONFLICT (domain) DO NOTHING'
        );
        $count = 0;

        foreach ($domains as $domain) {
            $stmt->execute([':domain' => $domain, ':source' => $sourceUrl]);
            $count += $stmt->rowCount();
        }

        authRecordSecurityListSource($pdo, 'disposable_email_domains', $sourceUrl, $count);
        $pdo->commit();

        return $count;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function authReplaceWeakPasswords(PDO $pdo, array $passwords, string $sourceLabel): int
{
    $pdo->beginTransaction();

    try {
        $pdo->exec('TRUNCATE weak_passwords');
        $stmt = $pdo->prepare(
            'INSERT INTO weak_passwords (password_hash, label)
             VALUES (:password_hash, :label)
             ON CONFLICT (password_hash) DO NOTHING'
        );
        $count = 0;

        foreach ($passwords as $password) {
            $stmt->execute([
                ':password_hash' => hash('sha256', $password),
                ':label' => $sourceLabel,
            ]);
            $count += $stmt->rowCount();
        }

        authRecordSecurityListSource($pdo, 'seclists_common_credentials_10k', AUTH_SECLISTS_10K_SOURCE, $count);
        authRecordSecurityListSource($pdo, 'seclists_darkweb2017_top10000', AUTH_SECLISTS_DARKWEB_SOURCE, $count);
        $pdo->commit();

        return $count;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function authRecordSecurityListSource(PDO $pdo, string $sourceKey, string $sourceUrl, int $itemCount): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO auth_security_list_sources (source_key, source_url, item_count, synced_at)
         VALUES (:source_key, :source_url, :item_count, now())
         ON CONFLICT (source_key) DO UPDATE SET
            source_url = EXCLUDED.source_url,
            item_count = EXCLUDED.item_count,
            synced_at = now()'
    );
    $stmt->execute([
        ':source_key' => $sourceKey,
        ':source_url' => $sourceUrl,
        ':item_count' => $itemCount,
    ]);
}
