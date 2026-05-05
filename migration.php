<?php
declare(strict_types=1);

require_once __DIR__ . '/api/db.php';

function writeMigrationLine(string $message, bool $isError = false): void
{
    if (PHP_SAPI === 'cli' && $isError) {
        fwrite(STDERR, $message . "\n");
        return;
    }

    echo $message . "\n";
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');

    $secret = getenv('MIGRATION_SECRET') ?: '';
    $providedSecret = $_GET['key'] ?? '';

    if ($secret === '' || !hash_equals($secret, (string)$providedSecret)) {
        http_response_code(403);
        echo "Migration blocked.\n";
        echo "Run this from CLI or set MIGRATION_SECRET and pass ?key=your-secret.\n";
        exit;
    }
}

$schemaPath = __DIR__ . '/schema.sql';

if (!is_file($schemaPath) || !is_readable($schemaPath)) {
    writeMigrationLine('schema.sql was not found or is not readable.', true);
    exit(1);
}

$sql = file_get_contents($schemaPath);

if ($sql === false || trim($sql) === '') {
    writeMigrationLine('schema.sql is empty or could not be loaded.', true);
    exit(1);
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    $pdo->exec($sql);
    $pdo->commit();

    writeMigrationLine('Migration completed successfully.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    writeMigrationLine('Migration failed: ' . $e->getMessage(), true);
    exit(1);
}
