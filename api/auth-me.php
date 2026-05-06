<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

authApplySecurityHeaders();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        authSendJson(['error' => 'Method not allowed.'], 405);
    }

    $user = authCurrentUser();
    authSendJson(['user' => $user]);
} catch (Throwable $e) {
    authSendJson(['error' => 'No se pudo leer la sesión ahora mismo.'], 500);
}
