<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

authApplySecurityHeaders();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        authSendJson(['error' => 'Method not allowed.'], 405);
    }

    $result = authLogin(authReadPayload());

    if (!$result['ok']) {
        authSendJson(['error' => $result['error']], 401);
    }

    authSendJson([
        'ok' => true,
        'user' => $result['user'],
        'redirect' => 'index.php',
    ]);
} catch (Throwable $e) {
    authSendJson(['error' => 'No se pudo iniciar sesión ahora mismo.'], 500);
}
