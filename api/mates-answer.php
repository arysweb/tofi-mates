<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['error' => 'Method not allowed.'], 405);
    }

    $payload = readJsonPayload();
    $problemId = trim((string) ($payload['problem_id'] ?? ''));
    $selectedAnswer = trim((string) ($payload['selected_answer'] ?? ''));

    if (!isUuid($problemId) || $selectedAnswer === '') {
        sendJson(['error' => 'Invalid answer payload.'], 422);
    }

    $result = saveMatesAnswer($problemId, $selectedAnswer);
    sendJson($result);
} catch (Throwable $e) {
    sendJson(['error' => 'No se pudo guardar la respuesta ahora mismo.'], 500);
}

function readJsonPayload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function isUuid(string $value): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
}

function saveMatesAnswer(string $problemId, string $selectedAnswer): array
{
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    try {
        $problemStmt = $pdo->prepare(
            'SELECT correct_answer, explanation
             FROM practice_problems
             WHERE id = :id'
        );
        $problemStmt->execute([':id' => $problemId]);
        $problem = $problemStmt->fetch();

        if (!is_array($problem)) {
            $pdo->rollBack();
            sendJson(['error' => 'Problem not found.'], 404);
        }

        $isCorrect = hash_equals((string) $problem['correct_answer'], $selectedAnswer);
        $answerStmt = $pdo->prepare(
            'INSERT INTO practice_answers (practice_problem_id, selected_answer, is_correct)
             VALUES (:practice_problem_id, :selected_answer, :is_correct)
             RETURNING id'
        );
        $answerStmt->execute([
            ':practice_problem_id' => $problemId,
            ':selected_answer' => $selectedAnswer,
            ':is_correct' => $isCorrect,
        ]);

        $answerId = (string) $answerStmt->fetchColumn();
        $pdo->commit();

        return [
            'answer_id' => $answerId,
            'is_correct' => $isCorrect,
            'correct_answer' => (string) $problem['correct_answer'],
            'explanation' => (string) $problem['explanation'],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
