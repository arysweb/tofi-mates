<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

authApplySecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

if (PHP_SAPI !== 'cli') {
    $secret = getenv('MIGRATION_SECRET') ?: '';
    $providedSecret = $_GET['key'] ?? '';

    if ($secret === '' || !hash_equals($secret, (string) $providedSecret)) {
        http_response_code(403);
        echo json_encode(['error' => 'List sync blocked.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $pdo = getDbConnection();
    $result = authSyncSecurityLists($pdo, true);

    echo json_encode([
        'ok' => true,
        'sources' => [
            AUTH_DISPOSABLE_EMAIL_SOURCE,
            AUTH_SECLISTS_10K_SOURCE,
            AUTH_SECLISTS_DARKWEB_SOURCE,
        ],
        'imported' => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Auth list sync failed.'], JSON_UNESCAPED_UNICODE);
}
