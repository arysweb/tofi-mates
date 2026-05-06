<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['error' => 'Method not allowed.'], 405);
    }

    $payload = readJsonPayload();
    $reason = trim((string) ($payload['reason'] ?? ''));
    $question = trim((string) ($payload['question'] ?? ''));

    if ($reason === '' || $question === '') {
        sendJson(['error' => 'Report reason and question are required.'], 422);
    }

    $reportId = saveProblemReport($payload);
    sendJson(['report_id' => $reportId, 'ok' => true]);
} catch (Throwable $e) {
    sendJson(['error' => 'No se pudo enviar el reporte ahora mismo.'], 500);
}

function readJsonPayload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function saveProblemReport(array $payload): string
{
    $pdo = getDbConnection();
    ensureProblemReportsTable($pdo);

    $problemId = normalizeUuid($payload['problem_id'] ?? null);
    $setId = normalizeUuid($payload['set_id'] ?? null);
    $stmt = $pdo->prepare(
        'INSERT INTO problem_reports (
            practice_problem_id,
            practice_set_id,
            domain_slug,
            subtopic_slug,
            difficulty,
            reason,
            details,
            question,
            options,
            correct_answer,
            selected_answer,
            reporter_email,
            client_key
        )
        VALUES (
            :practice_problem_id,
            :practice_set_id,
            :domain_slug,
            :subtopic_slug,
            :difficulty,
            :reason,
            :details,
            :question,
            :options,
            :correct_answer,
            :selected_answer,
            :reporter_email,
            :client_key
        )
        RETURNING id'
    );
    $stmt->execute([
        ':practice_problem_id' => $problemId,
        ':practice_set_id' => $setId,
        ':domain_slug' => sanitizeSlug($payload['domain'] ?? ''),
        ':subtopic_slug' => sanitizeSlug($payload['subtopic'] ?? ''),
        ':difficulty' => sanitizeSlug($payload['difficulty'] ?? ''),
        ':reason' => substr(trim((string) ($payload['reason'] ?? '')), 0, 500),
        ':details' => substr(trim((string) ($payload['details'] ?? '')), 0, 3000),
        ':question' => substr(trim((string) ($payload['question'] ?? '')), 0, 1000),
        ':options' => json_encode(is_array($payload['options'] ?? null) ? $payload['options'] : [], JSON_UNESCAPED_UNICODE),
        ':correct_answer' => substr(trim((string) ($payload['correct_answer'] ?? '')), 0, 255),
        ':selected_answer' => substr(trim((string) ($payload['selected_answer'] ?? '')), 0, 255),
        ':reporter_email' => sanitizeEmail($payload['reporter_email'] ?? null),
        ':client_key' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($payload['client_key'] ?? '')) ?: '', 0, 120),
    ]);

    return (string) $stmt->fetchColumn();
}

function ensureProblemReportsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS problem_reports (
            id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
            practice_problem_id uuid REFERENCES practice_problems(id) ON DELETE SET NULL,
            practice_set_id uuid REFERENCES practice_sets(id) ON DELETE SET NULL,
            domain_slug varchar(60),
            subtopic_slug varchar(80),
            difficulty varchar(24),
            reason text NOT NULL,
            details text,
            question text NOT NULL,
            options jsonb NOT NULL DEFAULT \'[]\'::jsonb,
            correct_answer text,
            selected_answer text,
            reporter_email varchar(255),
            client_key varchar(120),
            status varchar(24) NOT NULL DEFAULT \'open\',
            created_at timestamptz NOT NULL DEFAULT now()
        )'
    );
    $pdo->exec('ALTER TABLE problem_reports ADD COLUMN IF NOT EXISTS details text');
    $pdo->exec('ALTER TABLE problem_reports ADD COLUMN IF NOT EXISTS reporter_email varchar(255)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_problem_reports_status_created ON problem_reports(status, created_at DESC)');
}

function normalizeUuid(mixed $value): ?string
{
    $clean = trim((string) $value);

    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clean) ? $clean : null;
}

function sanitizeSlug(mixed $value): string
{
    return substr(preg_replace('/[^a-z-]/', '', (string) $value) ?: '', 0, 80);
}

function sanitizeEmail(mixed $value): ?string
{
    $email = trim((string) $value);

    if ($email === '') {
        return null;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 255) : null;
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
