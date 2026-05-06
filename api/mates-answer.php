<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['error' => 'Method not allowed.'], 405);
    }

    $authUser = authRequireUserJson();
    $payload = readJsonPayload();
    authVerifyCsrf($payload);
    $problemId = trim((string) ($payload['problem_id'] ?? ''));
    $selectedAnswer = trim((string) ($payload['selected_answer'] ?? ''));

    if (!isUuid($problemId) || $selectedAnswer === '') {
        sendJson(['error' => 'Invalid answer payload.'], 422);
    }

    $result = saveMatesAnswer($problemId, $selectedAnswer, (string) $authUser['id']);
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

function saveMatesAnswer(string $problemId, string $selectedAnswer, string $userId): array
{
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    try {
        $problemStmt = $pdo->prepare(
            'SELECT pp.practice_set_id, pp.correct_answer, pp.explanation
             FROM practice_problems pp
             INNER JOIN practice_sets ps ON ps.id = pp.practice_set_id
             WHERE pp.id = :id
               AND ps.user_id = :user_id'
        );
        $problemStmt->execute([':id' => $problemId, ':user_id' => $userId]);
        $problem = $problemStmt->fetch();

        if (!is_array($problem)) {
            $pdo->rollBack();
            sendJson(['error' => 'Problem not found.'], 404);
        }

        $isCorrect = hash_equals((string) $problem['correct_answer'], $selectedAnswer);
        $answerStmt = $pdo->prepare(
            'INSERT INTO practice_answers (practice_problem_id, selected_answer, is_correct)
             VALUES (:practice_problem_id, :selected_answer, :is_correct)
             ON CONFLICT (practice_problem_id) DO UPDATE SET
                selected_answer = EXCLUDED.selected_answer,
                is_correct = EXCLUDED.is_correct,
                answered_at = now()
             RETURNING id'
        );
        $answerStmt->execute([
            ':practice_problem_id' => $problemId,
            ':selected_answer' => $selectedAnswer,
            ':is_correct' => $isCorrect,
        ]);

        $answerId = (string) $answerStmt->fetchColumn();
        markPracticeSetCompletedIfAnswered($pdo, (string) $problem['practice_set_id']);
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

function markPracticeSetCompletedIfAnswered(PDO $pdo, string $practiceSetId): void
{
    $stmt = $pdo->prepare(
        'UPDATE practice_sets
         SET completed_at = now()
         WHERE id = :practice_set_id
           AND completed_at IS NULL
           AND (
                SELECT count(*)
                FROM practice_problems
                WHERE practice_set_id = :practice_set_id
           ) = (
                SELECT count(*)
                FROM practice_problems pp
                INNER JOIN practice_answers pa ON pa.practice_problem_id = pp.id
                WHERE pp.practice_set_id = :practice_set_id
           )'
    );
    $stmt->execute([':practice_set_id' => $practiceSetId]);
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
