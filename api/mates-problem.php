<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

const MATES_SUBTOPICS = ['sumar', 'restar', 'comparar', 'problemas'];
const MATES_DIFFICULTIES = ['easy', 'medium', 'hard'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['error' => 'Method not allowed.'], 405);
    }

    $payload = readJsonPayload();
    $subtopic = sanitizeChoice($payload['subtopic'] ?? 'sumar', MATES_SUBTOPICS, 'sumar');
    $difficulty = sanitizeChoice($payload['difficulty'] ?? 'easy', MATES_DIFFICULTIES, 'easy');

    $practiceSet = generateMatesProblemSet($subtopic, $difficulty);
    sendJson($practiceSet);
} catch (Throwable $e) {
    sendJson(['error' => 'No se pudo generar el reto ahora mismo.'], 500);
}

function readJsonPayload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function sanitizeChoice(mixed $value, array $allowed, string $fallback): string
{
    $clean = preg_replace('/[^a-z-]/', '', (string) $value) ?: $fallback;

    return in_array($clean, $allowed, true) ? $clean : $fallback;
}

function generateMatesProblemSet(string $subtopic, string $difficulty): array
{
    loadDotEnvFromProjectParent();

    $prompt = buildMatesPrompt($subtopic, $difficulty);
    $aiResult = callRotatingAiProvider($prompt);
    $providerName = $aiResult['provider'] ?? 'local';
    $raw = $aiResult['raw'] ?? null;

    if ($raw === null) {
        return persistPracticeSet($subtopic, $difficulty, fallbackMatesProblems($subtopic, $difficulty), $providerName);
    }

    $problems = parseProblemSetJson($raw);

    return persistPracticeSet($subtopic, $difficulty, $problems ?? fallbackMatesProblems($subtopic, $difficulty), $providerName);
}

function buildMatesPrompt(string $subtopic, string $difficulty): string
{
    $difficultyGuide = [
        'easy' => 'numbers from 0 to 20, one simple step, very friendly wording',
        'medium' => 'numbers from 0 to 50, one or two simple steps',
        'hard' => 'numbers from 0 to 100, two steps, still age appropriate',
    ];

    $subtopicGuide = [
        'sumar' => 'addition',
        'restar' => 'subtraction',
        'comparar' => 'comparing numbers using greater than, less than, or equal ideas',
        'problemas' => 'short word problems',
    ];

    return 'Generate exactly 3 ' . $subtopicGuide[$subtopic] . ' math problems for a Spanish-speaking 7 or 8 year old kid. '
        . 'Difficulty: ' . $difficultyGuide[$difficulty] . '. '
        . 'Use warm, simple Spanish. Do not mention AI, backend, JSON, prompts, levels, points, stars, shops, missions, or rewards. '
        . 'Respond ONLY in valid JSON, no markdown, no code fences, no extra text. '
        . 'Use this exact structure: '
        . '{"problems":[{"question":"...","type":"multiple_choice","options":["...","...","...","..."],"correct_answer":"...","hint":"...","explanation":"..."}]} '
        . 'Rules: problems must contain exactly 3 items; each type must be "multiple_choice"; each options array must have exactly 4 short strings; correct_answer must exactly match one option; '
        . 'hint must help without giving the answer; explanation must be encouraging and child-friendly.';
}

function callRotatingAiProvider(string $prompt): ?array
{
    $providers = getAvailableAiProviders();

    if ($providers === []) {
        return null;
    }

    $startIndex = random_int(0, count($providers) - 1);
    $orderedProviders = array_merge(
        array_slice($providers, $startIndex),
        array_slice($providers, 0, $startIndex)
    );

    foreach ($orderedProviders as $provider) {
        $raw = callAiProvider($provider, $prompt);

        if ($raw !== null) {
            return [
                'provider' => $provider['name'] ?? 'unknown',
                'raw' => $raw,
            ];
        }
    }

    return null;
}

function getAvailableAiProviders(): array
{
    $providers = [];

    if ((getenv('AI_API_KEY') ?: '') !== '') {
        $providers[] = [
            'name' => 'ai',
            'type' => 'openai_compatible',
            'url' => getenv('AI_API_URL') ?: getenv('AI_BASE_URL') ?: 'https://api.deepseek.com/chat/completions',
            'apiKey' => getenv('AI_API_KEY') ?: '',
            'model' => getenv('AI_MODEL') ?: 'deepseek-chat',
        ];
    }

    if ((getenv('GEMINI_API_KEY') ?: '') !== '') {
        $providers[] = [
            'name' => 'gemini',
            'type' => 'gemini',
            'apiKey' => getenv('GEMINI_API_KEY') ?: '',
            'model' => getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash',
        ];
    }

    if ((getenv('MISTRAL_API_KEY') ?: '') !== '') {
        $providers[] = [
            'name' => 'mistral',
            'type' => 'openai_compatible',
            'url' => getenv('MISTRAL_API_URL') ?: 'https://api.mistral.ai/v1/chat/completions',
            'apiKey' => getenv('MISTRAL_API_KEY') ?: '',
            'model' => getenv('MISTRAL_MODEL') ?: 'mistral-small-latest',
        ];
    }

    return $providers;
}

function callAiProvider(array $provider, string $prompt): ?string
{
    if (($provider['type'] ?? '') === 'gemini') {
        return callGemini($prompt, (string) $provider['apiKey'], (string) $provider['model']);
    }

    if (($provider['type'] ?? '') === 'openai_compatible') {
        return callOpenAiCompatible(
            (string) $provider['url'],
            (string) $provider['apiKey'],
            (string) $provider['model'],
            $prompt
        );
    }

    return null;
}

function callGemini(string $prompt, string $apiKey, string $model): ?string
{
    if ($apiKey === '') {
        return null;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $response = postJson($url, [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'responseMimeType' => 'application/json',
        ],
    ]);

    if ($response === null) {
        return null;
    }

    return $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function callOpenAiCompatible(string $url, string $apiKey, string $model, string $prompt): ?string
{
    if ($apiKey === '') {
        return null;
    }

    $response = postJson($url, [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You create safe, friendly math exercises for kids and answer only in valid JSON.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.7,
        'response_format' => ['type' => 'json_object'],
    ], [
        'Authorization: Bearer ' . $apiKey,
    ]);

    if ($response === null) {
        return null;
    }

    return $response['choices'][0]['message']['content'] ?? null;
}

function postJson(string $url, array $payload, array $extraHeaders = []): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $extraHeaders),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($body) || $status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
}

function parseProblemSetJson(string $raw): ?array
{
    $cleaned = trim(preg_replace('/```json|```/i', '', $raw) ?? $raw);
    $data = json_decode($cleaned, true);

    if (!is_array($data)) {
        return null;
    }

    $items = is_array($data['problems'] ?? null) ? $data['problems'] : [];

    if (count($items) !== 3) {
        return null;
    }

    $problems = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            return null;
        }

        $problem = normalizeProblem($item);

        if ($problem === null) {
            return null;
        }

        $problems[] = $problem;
    }

    return $problems;
}

function normalizeProblem(array $data): ?array
{
    $problem = [
        'question' => trim((string) ($data['question'] ?? '')),
        'type' => trim((string) ($data['type'] ?? '')),
        'options' => array_values(array_map('strval', is_array($data['options'] ?? null) ? $data['options'] : [])),
        'correct_answer' => trim((string) ($data['correct_answer'] ?? '')),
        'hint' => trim((string) ($data['hint'] ?? '')),
        'explanation' => trim((string) ($data['explanation'] ?? '')),
    ];

    if ($problem['question'] === '' || $problem['type'] !== 'multiple_choice') {
        return null;
    }

    if (count($problem['options']) !== 4 || !in_array($problem['correct_answer'], $problem['options'], true)) {
        return null;
    }

    if ($problem['hint'] === '' || $problem['explanation'] === '') {
        return null;
    }

    return $problem;
}

function persistPracticeSet(string $subtopic, string $difficulty, array $problems, string $providerName): array
{
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    try {
        $setStmt = $pdo->prepare(
            'INSERT INTO practice_sets (domain_slug, subtopic_slug, difficulty, provider_name)
             VALUES (:domain_slug, :subtopic_slug, :difficulty, :provider_name)
             RETURNING id'
        );
        $setStmt->execute([
            ':domain_slug' => 'math',
            ':subtopic_slug' => $subtopic,
            ':difficulty' => $difficulty,
            ':provider_name' => $providerName,
        ]);

        $setId = (string) $setStmt->fetchColumn();
        $problemStmt = $pdo->prepare(
            'INSERT INTO practice_problems (practice_set_id, question, question_type, options, correct_answer, hint, explanation, sort_order)
             VALUES (:practice_set_id, :question, :question_type, :options, :correct_answer, :hint, :explanation, :sort_order)
             RETURNING id'
        );

        foreach ($problems as $index => &$problem) {
            $problemStmt->execute([
                ':practice_set_id' => $setId,
                ':question' => $problem['question'],
                ':question_type' => $problem['type'],
                ':options' => json_encode($problem['options'], JSON_UNESCAPED_UNICODE),
                ':correct_answer' => $problem['correct_answer'],
                ':hint' => $problem['hint'],
                ':explanation' => $problem['explanation'],
                ':sort_order' => $index + 1,
            ]);

            $problem['id'] = (string) $problemStmt->fetchColumn();
        }
        unset($problem);

        $pdo->commit();

        return fetchPracticeSet($pdo, $setId, $providerName);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fetchPracticeSet(PDO $pdo, string $setId, string $providerName): array
{
    $stmt = $pdo->prepare(
        'SELECT id, question, question_type, options, correct_answer, hint, explanation
         FROM practice_problems
         WHERE practice_set_id = :practice_set_id
         ORDER BY sort_order ASC'
    );
    $stmt->execute([':practice_set_id' => $setId]);

    $problems = [];

    foreach ($stmt->fetchAll() as $row) {
        $options = json_decode((string) $row['options'], true);

        $problems[] = [
            'id' => (string) $row['id'],
            'question' => (string) $row['question'],
            'type' => (string) $row['question_type'],
            'options' => is_array($options) ? array_values(array_map('strval', $options)) : [],
            'correct_answer' => (string) $row['correct_answer'],
            'hint' => (string) $row['hint'],
            'explanation' => (string) $row['explanation'],
        ];
    }

    return [
        'set_id' => $setId,
        'provider' => $providerName,
        'problems' => $problems,
    ];
}

function fallbackMatesProblems(string $subtopic, string $difficulty): array
{
    $fallbacks = [
        'sumar' => [
            [
                'question' => 'Mia tiene 8 lápices y recibe 5 más. ¿Cuántos lápices tiene ahora?',
                'options' => ['11', '12', '13', '14'],
                'correct_answer' => '13',
                'hint' => 'Empieza en 8 y cuenta 5 pasos más.',
                'explanation' => '8 + 5 significa juntar 5 con 8. Si cuentas 9, 10, 11, 12, 13, llegas a 13.',
            ],
            [
                'question' => 'Hay 6 globos y llegan 4 globos más. ¿Cuántos globos hay?',
                'options' => ['8', '9', '10', '11'],
                'correct_answer' => '10',
                'hint' => 'Junta los 6 globos con los 4 nuevos.',
                'explanation' => '6 + 4 es 10. Al juntar todos los globos, hay 10.',
            ],
            [
                'question' => 'Tienes 9 canicas y encuentras 3 más. ¿Cuántas tienes en total?',
                'options' => ['10', '11', '12', '13'],
                'correct_answer' => '12',
                'hint' => 'Cuenta 3 pasos después del 9.',
                'explanation' => '9 + 3 da 12. Por eso tienes 12 canicas.',
            ],
        ],
        'restar' => [
            [
                'question' => 'Mia tiene 14 pegatinas y regala 5. ¿Cuántas le quedan?',
                'options' => ['7', '8', '9', '10'],
                'correct_answer' => '9',
                'hint' => 'Piensa en quitar 5 desde 14, uno por uno.',
                'explanation' => 'Si a 14 le quitas 5, bajas hasta 9. Por eso le quedan 9 pegatinas.',
            ],
            [
                'question' => 'Hay 16 galletas y se comen 6. ¿Cuántas quedan?',
                'options' => ['8', '9', '10', '11'],
                'correct_answer' => '10',
                'hint' => 'Quita 6 desde 16.',
                'explanation' => '16 - 6 es 10. Quedan 10 galletas.',
            ],
            [
                'question' => 'Tienes 12 cromos y pierdes 3. ¿Cuántos cromos quedan?',
                'options' => ['8', '9', '10', '11'],
                'correct_answer' => '9',
                'hint' => 'Desde 12, baja 3 números.',
                'explanation' => '12 - 3 es 9. Por eso quedan 9 cromos.',
            ],
        ],
        'comparar' => [
            [
                'question' => '¿Qué número es mayor: 18 o 12?',
                'options' => ['18', '12', 'Son iguales', 'Ninguno'],
                'correct_answer' => '18',
                'hint' => 'Mira cuál está más lejos si cuentas hacia arriba.',
                'explanation' => '18 es mayor que 12 porque aparece después cuando contamos hacia arriba.',
            ],
            [
                'question' => '¿Qué número es menor: 7 o 15?',
                'options' => ['7', '15', 'Son iguales', 'Ninguno'],
                'correct_answer' => '7',
                'hint' => 'El menor aparece primero cuando cuentas.',
                'explanation' => '7 es menor que 15 porque está antes al contar.',
            ],
            [
                'question' => '¿Cuál opción muestra dos números iguales?',
                'options' => ['8 y 6', '9 y 9', '10 y 7', '5 y 3'],
                'correct_answer' => '9 y 9',
                'hint' => 'Busca la pareja que repite el mismo número.',
                'explanation' => '9 y 9 son iguales porque los dos números son el mismo.',
            ],
        ],
        'problemas' => [
            [
                'question' => 'En una cesta hay 6 manzanas y 4 peras. ¿Cuántas frutas hay en total?',
                'options' => ['8', '9', '10', '11'],
                'correct_answer' => '10',
                'hint' => 'Junta las manzanas y las peras en una sola cuenta.',
                'explanation' => 'Hay que sumar 6 + 4. Al juntarlas, hay 10 frutas en total.',
            ],
            [
                'question' => 'Luca tiene 15 caramelos y comparte 4. ¿Cuántos caramelos le quedan?',
                'options' => ['9', '10', '11', '12'],
                'correct_answer' => '11',
                'hint' => 'Compartir 4 significa quitar 4.',
                'explanation' => '15 - 4 es 11. A Luca le quedan 11 caramelos.',
            ],
            [
                'question' => 'Hay 5 coches rojos y 7 coches azules. ¿Cuántos coches hay?',
                'options' => ['10', '11', '12', '13'],
                'correct_answer' => '12',
                'hint' => 'Junta los coches rojos y los azules.',
                'explanation' => '5 + 7 es 12. En total hay 12 coches.',
            ],
        ],
    ];

    $problems = $fallbacks[$subtopic] ?? $fallbacks['sumar'];

    return array_map(static function (array $problem) use ($difficulty): array {
        $problem['type'] = 'multiple_choice';
        $problem['difficulty'] = $difficulty;

        return $problem;
    }, $problems);
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
