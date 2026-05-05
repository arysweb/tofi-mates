<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db.php';

try {
    $row = getDbHealthRow();
    echo 'DB OK | database=' . ($row['db_name'] ?? 'unknown') . ' | time=' . ($row['server_time'] ?? 'unknown');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB ERROR | ' . $e->getMessage();
}
