<?php
declare(strict_types=1);

function loadDotEnvFromProjectParent(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('pdo_pgsql is not installed in this runtime.');
    }

    $cfg = getDbConfig();
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['dbName'],
        $cfg['sslMode']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function getDbConfig(): array
{
    loadDotEnvFromProjectParent();

    $databaseUrl = getenv('DATABASE_URL') ?: '';

    if ($databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            throw new RuntimeException('Invalid DATABASE_URL format.');
        }

        $host = $parts['host'] ?? '';
        $port = (string)($parts['port'] ?? '5432');
        $dbName = ltrim((string)($parts['path'] ?? ''), '/');
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';

        parse_str($parts['query'] ?? '', $queryParams);
        $sslMode = $queryParams['sslmode'] ?? 'require';
    } else {
        $host = getenv('PGHOST') ?: '127.0.0.1';
        $port = getenv('PGPORT') ?: '5432';
        $dbName = getenv('PGDATABASE') ?: '';
        $user = getenv('PGUSER') ?: '';
        $pass = getenv('PGPASSWORD') ?: '';
        $sslMode = getenv('PGSSLMODE') ?: 'prefer';
    }

    if ($dbName === '' || $user === '') {
        throw new RuntimeException('PostgreSQL env vars are missing (DATABASE_URL or PG* vars).');
    }

    return [
        'host' => $host,
        'port' => $port,
        'dbName' => $dbName,
        'user' => $user,
        'pass' => $pass,
        'sslMode' => $sslMode,
    ];
}

function getDbHealthRow(): array
{
    $cfg = getDbConfig();

    if (extension_loaded('pdo_pgsql')) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbName'],
            $cfg['sslMode']
        );

        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $row = $pdo->query('SELECT current_database() AS db_name, now() AS server_time')->fetch();
        return is_array($row) ? $row : [];
    }

    if (function_exists('pg_connect')) {
        $connString = sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s sslmode=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbName'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['sslMode']
        );

        $conn = @pg_connect($connString);
        if ($conn === false) {
            throw new RuntimeException('PostgreSQL connection failed via pgsql extension.');
        }

        $result = pg_query($conn, 'SELECT current_database() AS db_name, now() AS server_time');
        if ($result === false) {
            pg_close($conn);
            throw new RuntimeException('PostgreSQL query failed via pgsql extension.');
        }

        $row = pg_fetch_assoc($result) ?: [];
        pg_free_result($result);
        pg_close($conn);

        return $row;
    }

    throw new RuntimeException(
        'PostgreSQL driver not installed. Enable pdo_pgsql or pgsql in Railway runtime.'
    );
}
