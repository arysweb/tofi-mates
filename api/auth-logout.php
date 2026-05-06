<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

authApplySecurityHeaders();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        authSendJson(['error' => 'Method not allowed.'], 405);
    }

    $payload = authReadPayload();
    authVerifyCsrf($payload);
    authLogout();

    if (stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false) {
        authSendJson(['ok' => true, 'redirect' => '../index.php?page=login']);
    }

    header('Location: ../index.php?page=login');
    exit;
} catch (Throwable $e) {
    authSendJson(['error' => 'No se pudo cerrar sesión ahora mismo.'], 500);
}
