<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

const PRACTICE_SUBTOPICS = [
    'math' => ['sumar', 'restar', 'comparar', 'problemas'],
    'logic' => ['series', 'formas', 'adivinanzas', 'ordenar'],
    'time' => ['relojes', 'rutinas', 'duracion', 'antes-despues'],
    'money' => ['monedas', 'precios', 'cambio', 'ahorrar'],
];
const PRACTICE_DIFFICULTIES = ['easy', 'medium', 'hard'];
const AI_PROVIDER_ATTEMPTS = 1;
const AI_PROVIDER_TIMEOUT_SECONDS = 6;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['error' => 'Method not allowed.'], 405);
    }

    $payload = readJsonPayload();
    $domain = sanitizeChoice($payload['domain'] ?? $payload['topic'] ?? 'math', array_keys(PRACTICE_SUBTOPICS), 'math');
    $subtopic = sanitizeChoice($payload['subtopic'] ?? PRACTICE_SUBTOPICS[$domain][0], PRACTICE_SUBTOPICS[$domain], PRACTICE_SUBTOPICS[$domain][0]);
    $difficulty = sanitizeChoice($payload['difficulty'] ?? 'easy', PRACTICE_DIFFICULTIES, 'easy');
    $clientKey = sanitizeClientKey($payload['client_key'] ?? null);
    $forceNew = (bool) ($payload['force_new'] ?? false);

    $practiceSet = generatePracticeProblemSet($domain, $subtopic, $difficulty, $clientKey, $forceNew);
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

function sanitizeClientKey(mixed $value): ?string
{
    $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value) ?: '';

    return $clean === '' ? null : substr($clean, 0, 120);
}

function generatePracticeProblemSet(string $domain, string $subtopic, string $difficulty, ?string $clientKey, bool $forceNew): array
{
    loadDotEnvFromProjectParent();
    $pdo = null;

    try {
        $pdo = getDbConnection();
    } catch (Throwable $e) {
        $pdo = null;
    }

    if ($pdo instanceof PDO) {
        try {
            if (!$forceNew && $clientKey !== null) {
                $recentSet = fetchRecentPracticeSet($pdo, $domain, $subtopic, $difficulty, $clientKey);

                if ($recentSet !== null && practiceSetIsUsable($recentSet, $domain)) {
                    return $recentSet;
                }
            }

            $cachedProblems = fetchCachedProblems($pdo, $domain, $subtopic, $difficulty);

            if (count($cachedProblems) >= 3) {
                return persistPracticeSet($pdo, $domain, $subtopic, $difficulty, array_slice($cachedProblems, 0, 3), 'cache', $clientKey);
            }
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    $prompt = buildPracticePrompt($domain, $subtopic, $difficulty);
    $aiResult = callRotatingAiProvider($prompt);
    $providerName = $aiResult['provider'] ?? 'local';
    $raw = $aiResult['raw'] ?? null;
    $problems = is_string($raw) ? parseProblemSetJson($raw) : null;
    $problems = filterProblemsForDomain($problems, $domain);
    $finalProvider = $problems === null ? 'local' : $providerName;
    $preparedProblems = $problems ?? fallbackPracticeProblems($domain, $subtopic, $difficulty);

    if ($pdo instanceof PDO) {
        try {
            $bankProblems = storeProblemsInBank($pdo, $domain, $subtopic, $difficulty, $preparedProblems, $finalProvider);

            return persistPracticeSet($pdo, $domain, $subtopic, $difficulty, $bankProblems, $finalProvider, $clientKey);
        } catch (Throwable $e) {
            return buildTransientPracticeSet($preparedProblems, $finalProvider);
        }
    }

    return buildTransientPracticeSet($preparedProblems, $finalProvider);
}

function buildTransientPracticeSet(array $problems, string $providerName): array
{
    $setId = uniqid('local_', true);

    foreach ($problems as $index => &$problem) {
        $problem['id'] = $setId . '_' . ($index + 1);
    }
    unset($problem);

    return [
        'set_id' => $setId,
        'provider' => $providerName,
        'problems' => $problems,
    ];
}

function buildPracticePrompt(string $domain, string $subtopic, string $difficulty): string
{
    $guides = getPracticeGuides();
    $domainGuide = $guides[$domain] ?? $guides['math'];
    $subtopicGuide = $domainGuide['subtopics'][$subtopic] ?? $domainGuide['subtopics'][array_key_first($domainGuide['subtopics'])];
    $difficultyGuide = $domainGuide['difficulty'][$difficulty] ?? $domainGuide['difficulty']['easy'];

    return 'Generate exactly 3 ' . $subtopicGuide . ' for a 7 or 8 year old kid in Spain. '
        . 'Subject: ' . $domainGuide['label'] . '. Difficulty: ' . $difficultyGuide . '. '
        . 'The difference between difficulties must be very pronounced. Easy must still be real practice, not baby work. Medium must require thinking. Hard must require two steps or deeper reasoning where the subject allows it. '
        . 'Use warm, simple Castilian Spanish from Spain. Use Spain vocabulary and spelling only. Do not use Latin American wording. '
        . getDomainPromptRules($domain)
        . 'Do not mention AI, backend, JSON, prompts, levels, points, stars, shops, missions, or rewards. '
        . 'Keep each question short. Keep each explanation in 2 or 3 very short sentences, with one idea per sentence. '
        . 'The explanation must explain the reasoning only; do not start it with praise like "Muy bien" or "Qué bien". '
        . 'Respond ONLY in valid JSON, no markdown, no code fences, no extra text. '
        . 'Use this exact structure: '
        . '{"problems":[{"question":"...","type":"multiple_choice","options":["...","...","...","..."],"correct_answer":"...","hint":"...","explanation":"..."}]} '
        . 'Rules: problems must contain exactly 3 items; each type must be "multiple_choice"; each options array must have exactly 4 short strings; correct_answer must exactly match one option; '
        . 'hint must help without giving the answer; explanation must be encouraging and child-friendly.';
}

function getDomainPromptRules(string $domain): string
{
    if ($domain === 'money') {
        return 'For money exercises, use euros and cents from Spain only. Write prices with € or euros. Never use pesos, dollars, centavos, "$", or any non-Spain currency. ';
    }

    return '';
}

function filterProblemsForDomain(?array $problems, string $domain): ?array
{
    if ($problems === null) {
        return null;
    }

    if ($domain !== 'money') {
        return $problems;
    }

    foreach ($problems as $problem) {
        if (problemHasNonSpainMoneyTerms($problem)) {
            return null;
        }
    }

    return $problems;
}

function practiceSetIsUsable(array $practiceSet, string $domain): bool
{
    $problems = is_array($practiceSet['problems'] ?? null) ? $practiceSet['problems'] : [];

    if (count($problems) !== 3) {
        return false;
    }

    $seenQuestions = [];

    foreach ($problems as $problem) {
        if (!is_array($problem)) {
            return false;
        }

        $question = trim((string) ($problem['question'] ?? ''));

        if ($question === '' || isset($seenQuestions[$question])) {
            return false;
        }

        if ($domain === 'money' && problemHasNonSpainMoneyTerms($problem)) {
            return false;
        }

        $seenQuestions[$question] = true;
    }

    return true;
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

    foreach (array_slice($orderedProviders, 0, AI_PROVIDER_ATTEMPTS) as $provider) {
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

    if ((getenv('OPENROUTER_API_KEY') ?: '') !== '') {
        $providers[] = [
            'name' => 'openrouter',
            'type' => 'openai_compatible',
            'url' => getenv('OPENROUTER_API_URL') ?: 'https://openrouter.ai/api/v1/chat/completions',
            'apiKey' => getenv('OPENROUTER_API_KEY') ?: '',
            'model' => getenv('OPENROUTER_MODEL') ?: 'openrouter/auto',
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
                'content' => 'You create safe, friendly learning exercises for kids and answer only in valid JSON.',
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
        CURLOPT_TIMEOUT => AI_PROVIDER_TIMEOUT_SECONDS,
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

function fetchRecentPracticeSet(PDO $pdo, string $domain, string $subtopic, string $difficulty, string $clientKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, provider_name
         FROM practice_sets
         WHERE client_key = :client_key
           AND domain_slug = :domain_slug
           AND subtopic_slug = :subtopic_slug
           AND difficulty = :difficulty
           AND created_at >= now() - interval \'30 minutes\'
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->execute([
        ':client_key' => $clientKey,
        ':domain_slug' => $domain,
        ':subtopic_slug' => $subtopic,
        ':difficulty' => $difficulty,
    ]);

    $row = $stmt->fetch();

    if (!is_array($row)) {
        return null;
    }

    return fetchPracticeSet($pdo, (string) $row['id'], (string) ($row['provider_name'] ?? 'recent'));
}

function fetchCachedProblems(PDO $pdo, string $domain, string $subtopic, string $difficulty): array
{
    $stmt = $pdo->prepare(
        'SELECT id, question, question_type, options, correct_answer, hint, explanation
         FROM problem_bank
         WHERE domain_slug = :domain_slug
           AND subtopic_slug = :subtopic_slug
           AND difficulty = :difficulty
           AND is_active = true
         ORDER BY times_served ASC, last_served_at ASC NULLS FIRST, random()
         LIMIT 3'
    );
    $stmt->execute([
        ':domain_slug' => $domain,
        ':subtopic_slug' => $subtopic,
        ':difficulty' => $difficulty,
    ]);

    $seenQuestions = [];
    $problems = [];

    foreach ($stmt->fetchAll() as $row) {
        $question = trim((string) $row['question']);

        if ($question === '' || isset($seenQuestions[$question])) {
            continue;
        }

        $seenQuestions[$question] = true;
        $options = json_decode((string) $row['options'], true);

        $problem = [
            'bank_id' => (string) $row['id'],
            'question' => $question,
            'type' => (string) $row['question_type'],
            'options' => is_array($options) ? array_values(array_map('strval', $options)) : [],
            'correct_answer' => (string) $row['correct_answer'],
            'hint' => (string) $row['hint'],
            'explanation' => (string) $row['explanation'],
        ];

        if ($domain === 'money' && problemHasNonSpainMoneyTerms($problem)) {
            continue;
        }

        $problems[] = $problem;

        if (count($problems) === 3) {
            break;
        }
    }

    return $problems;
}

function storeProblemsInBank(PDO $pdo, string $domain, string $subtopic, string $difficulty, array $problems, string $providerName): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO problem_bank (domain_slug, subtopic_slug, difficulty, question, question_type, options, correct_answer, hint, explanation, provider_name)
         VALUES (:domain_slug, :subtopic_slug, :difficulty, :question, :question_type, :options, :correct_answer, :hint, :explanation, :provider_name)
         RETURNING id'
    );

    foreach ($problems as &$problem) {
        $stmt->execute([
            ':domain_slug' => $domain,
            ':subtopic_slug' => $subtopic,
            ':difficulty' => $difficulty,
            ':question' => $problem['question'],
            ':question_type' => $problem['type'],
            ':options' => json_encode($problem['options'], JSON_UNESCAPED_UNICODE),
            ':correct_answer' => $problem['correct_answer'],
            ':hint' => $problem['hint'],
            ':explanation' => $problem['explanation'],
            ':provider_name' => $providerName,
        ]);

        $problem['bank_id'] = (string) $stmt->fetchColumn();
    }
    unset($problem);

    return $problems;
}

function persistPracticeSet(PDO $pdo, string $domain, string $subtopic, string $difficulty, array $problems, string $providerName, ?string $clientKey): array
{
    $pdo->beginTransaction();

    try {
        $setStmt = $pdo->prepare(
            'INSERT INTO practice_sets (domain_slug, subtopic_slug, difficulty, client_key, provider_name)
             VALUES (:domain_slug, :subtopic_slug, :difficulty, :client_key, :provider_name)
             RETURNING id'
        );
        $setStmt->execute([
            ':domain_slug' => $domain,
            ':subtopic_slug' => $subtopic,
            ':difficulty' => $difficulty,
            ':client_key' => $clientKey,
            ':provider_name' => $providerName,
        ]);

        $setId = (string) $setStmt->fetchColumn();
        $problemStmt = $pdo->prepare(
            'INSERT INTO practice_problems (practice_set_id, cached_problem_id, question, question_type, options, correct_answer, hint, explanation, sort_order)
             VALUES (:practice_set_id, :cached_problem_id, :question, :question_type, :options, :correct_answer, :hint, :explanation, :sort_order)
             RETURNING id'
        );

        foreach ($problems as $index => &$problem) {
            $problemStmt->execute([
                ':practice_set_id' => $setId,
                ':cached_problem_id' => $problem['bank_id'] ?? null,
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

        markBankProblemsServed($pdo, $problems);

        $pdo->commit();

        return fetchPracticeSet($pdo, $setId, $providerName);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function markBankProblemsServed(PDO $pdo, array $problems): void
{
    $bankIds = array_values(array_filter(array_map(static fn (array $problem): ?string => $problem['bank_id'] ?? null, $problems)));

    if ($bankIds === []) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($bankIds), '?'));
    $stmt = $pdo->prepare(
        'UPDATE problem_bank
         SET times_served = times_served + 1,
             last_served_at = now()
         WHERE id IN (' . $placeholders . ')'
    );
    $stmt->execute($bankIds);
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

function getPracticeGuides(): array
{
    return [
        'math' => [
            'label' => 'math',
            'subtopics' => [
                'sumar' => 'addition problems',
                'restar' => 'subtraction problems',
                'comparar' => 'number comparison problems',
                'problemas' => 'short math word problems',
            ],
            'difficulty' => [
                'easy' => 'numbers from 6 to 15, one step',
                'medium' => 'numbers from 12 to 35, one step with carrying or borrowing',
                'hard' => 'numbers from 30 to 100, two steps',
            ],
        ],
        'logic' => [
            'label' => 'logic',
            'subtopics' => [
                'series' => 'pattern and sequence problems',
                'formas' => 'shape and visual reasoning problems described in words',
                'adivinanzas' => 'short reasoning riddles',
                'ordenar' => 'ordering and classifying problems',
            ],
            'difficulty' => [
                'easy' => 'simple one-rule patterns or sorting with obvious clues',
                'medium' => 'two-step reasoning or less obvious patterns',
                'hard' => 'multi-clue reasoning with two conditions',
            ],
        ],
        'time' => [
            'label' => 'time and clocks',
            'subtopics' => [
                'relojes' => 'clock reading problems',
                'rutinas' => 'daily routine time problems',
                'duracion' => 'duration problems',
                'antes-despues' => 'before and after time ordering problems',
            ],
            'difficulty' => [
                'easy' => 'o’clock and half-hour times only',
                'medium' => 'quarter hours and simple elapsed time',
                'hard' => 'two-step elapsed time across routines',
            ],
        ],
        'money' => [
            'label' => 'money',
            'subtopics' => [
                'monedas' => 'coin counting problems',
                'precios' => 'price comparison problems',
                'cambio' => 'change after buying problems',
                'ahorrar' => 'saving money toward a goal problems',
            ],
            'difficulty' => [
                'easy' => 'small amounts up to 15 with one step',
                'medium' => 'amounts up to 50 with one operation',
                'hard' => 'two-step prices, payment, and change',
            ],
        ],
    ];
}

function fallbackPracticeProblems(string $domain, string $subtopic, string $difficulty): array
{
    if ($domain === 'math') {
        return generateLocalMathProblems($subtopic, $difficulty);
    }

    if ($domain === 'time') {
        return generateLocalTimeProblems($subtopic, $difficulty);
    }

    if ($domain === 'money') {
        return generateLocalMoneyProblems($subtopic, $difficulty);
    }

    $fallbacks = getFallbackProblemBank();

    $domainProblems = $fallbacks[$domain] ?? $fallbacks['math'];
    $difficultyProblems = $domainProblems[$difficulty] ?? $domainProblems['easy'];
    $problems = $difficultyProblems[$subtopic] ?? $difficultyProblems['sumar'];

    return array_map(static function (array $problem) use ($difficulty): array {
        $problem['type'] = 'multiple_choice';
        $problem['difficulty'] = $difficulty;

        return $problem;
    }, $problems);
}

function generateLocalMathProblems(string $subtopic, string $difficulty): array
{
    $problems = [];

    for ($index = 0; $index < 3; $index += 1) {
        $problems[] = match ($subtopic) {
            'restar' => generateSubtractionProblem($difficulty),
            'comparar' => generateComparisonProblem($difficulty),
            'problemas' => generateWordProblem($difficulty),
            default => generateAdditionProblem($difficulty),
        };
    }

    return $problems;
}

function generateAdditionProblem(string $difficulty): array
{
    if ($difficulty === 'hard') {
        $first = random_int(30, 70);
        $second = random_int(10, 25);
        $third = random_int(2, 12);
        $answer = $first + $second - $third;

        return localProblem(
            '¿Cuánto es ' . $first . ' + ' . $second . ' - ' . $third . '?',
            $answer,
            'Suma primero y después resta.',
            $first . ' + ' . $second . ' es ' . ($first + $second) . '. Luego ' . ($first + $second) . ' - ' . $third . ' es ' . $answer . '.'
        );
    }

    [$min, $max] = $difficulty === 'medium' ? [12, 35] : [6, 15];
    $first = random_int($min, $max);
    $second = random_int($difficulty === 'medium' ? 6 : 2, $difficulty === 'medium' ? 18 : 8);
    $answer = $first + $second;

    return localProblem(
        '¿Cuánto es ' . $first . ' + ' . $second . '?',
        $answer,
        'Empieza en ' . $first . ' y suma ' . $second . ' más.',
        $first . ' + ' . $second . ' es ' . $answer . '.'
    );
}

function generateSubtractionProblem(string $difficulty): array
{
    if ($difficulty === 'hard') {
        $first = random_int(50, 100);
        $second = random_int(12, 35);
        $third = random_int(3, 14);
        $answer = $first - $second + $third;

        return localProblem(
            '¿Cuánto es ' . $first . ' - ' . $second . ' + ' . $third . '?',
            $answer,
            'Resta primero y luego suma.',
            $first . ' - ' . $second . ' es ' . ($first - $second) . '. Luego suma ' . $third . ' y da ' . $answer . '.'
        );
    }

    [$min, $max] = $difficulty === 'medium' ? [16, 40] : [10, 18];
    $first = random_int($min, $max);
    $second = random_int($difficulty === 'medium' ? 7 : 3, min($first - 2, $difficulty === 'medium' ? 19 : 9));
    $answer = $first - $second;

    return localProblem(
        '¿Cuánto es ' . $first . ' - ' . $second . '?',
        $answer,
        'Quita ' . $second . ' desde ' . $first . '.',
        $first . ' - ' . $second . ' es ' . $answer . '.'
    );
}

function generateComparisonProblem(string $difficulty): array
{
    if ($difficulty === 'hard') {
        $leftA = random_int(20, 55);
        $leftB = random_int(5, 25);
        $rightA = random_int(30, 75);
        $rightB = random_int(4, 20);
        $left = $leftA + $leftB;
        $right = $rightA - $rightB;
        $correct = $left === $right ? 'Son iguales' : ($left > $right ? $leftA . ' + ' . $leftB : $rightA . ' - ' . $rightB);
        $explanation = $leftA . ' + ' . $leftB . ' es ' . $left . '. ' . $rightA . ' - ' . $rightB . ' es ' . $right . '. '
            . ($left === $right ? 'Son iguales.' : 'El mayor es ' . max($left, $right) . '.');

        return [
            'question' => '¿Qué resultado es mayor: ' . $leftA . ' + ' . $leftB . ' o ' . $rightA . ' - ' . $rightB . '?',
            'type' => 'multiple_choice',
            'options' => [$leftA . ' + ' . $leftB, $rightA . ' - ' . $rightB, 'Son iguales', 'No se puede saber'],
            'correct_answer' => $correct,
            'hint' => 'Calcula las dos operaciones antes de comparar.',
            'explanation' => $explanation,
        ];
    }

    [$min, $max] = $difficulty === 'medium' ? [10, 40] : [4, 18];
    $first = random_int($min, $max);
    $second = random_int($min, $max);

    while ($first === $second) {
        $second = random_int($min, $max);
    }

    $answer = max($first, $second);

    return localProblem(
        '¿Qué número es mayor: ' . $first . ' o ' . $second . '?',
        $answer,
        'El mayor aparece después al contar.',
        $answer . ' es mayor porque vale más que el otro número.'
    );
}

function generateWordProblem(string $difficulty): array
{
    $names = ['Ana', 'Leo', 'Mia', 'Noa'];
    $items = ['pegatinas', 'canicas', 'lápices', 'flores'];
    $name = $names[array_rand($names)];
    $item = $items[array_rand($items)];

    if ($difficulty === 'hard') {
        $start = random_int(20, 55);
        $added = random_int(8, 24);
        $removed = random_int(3, 15);
        $answer = $start + $added - $removed;

        return localProblem(
            $name . ' tiene ' . $start . ' ' . $item . ', recibe ' . $added . ' y usa ' . $removed . '. ¿Cuántas le quedan?',
            $answer,
            'Primero suma lo que recibe. Después quita lo que usa.',
            $start . ' + ' . $added . ' es ' . ($start + $added) . '. Luego quedan ' . $answer . '.'
        );
    }

    $start = random_int($difficulty === 'medium' ? 12 : 6, $difficulty === 'medium' ? 28 : 14);
    $added = random_int($difficulty === 'medium' ? 5 : 2, $difficulty === 'medium' ? 14 : 7);
    $answer = $start + $added;

    return localProblem(
        $name . ' tiene ' . $start . ' ' . $item . ' y recibe ' . $added . ' más. ¿Cuántas tiene?',
        $answer,
        'Junta las dos cantidades.',
        $start . ' + ' . $added . ' es ' . $answer . '.'
    );
}

function localProblem(string $question, int $answer, string $hint, string $explanation): array
{
    return [
        'question' => $question,
        'type' => 'multiple_choice',
        'options' => buildNumberOptions($answer),
        'correct_answer' => (string) $answer,
        'hint' => $hint,
        'explanation' => $explanation,
    ];
}

function buildNumberOptions(int $answer): array
{
    $options = [(string) $answer];
    $offsets = [-2, -1, 1, 2, 3, -3, 4, -4];
    shuffle($offsets);

    foreach ($offsets as $offset) {
        $candidate = $answer + $offset;

        if ($candidate >= 0 && !in_array((string) $candidate, $options, true)) {
            $options[] = (string) $candidate;
        }

        if (count($options) === 4) {
            break;
        }
    }

    shuffle($options);

    return $options;
}

function generateLocalTimeProblems(string $subtopic, string $difficulty): array
{
    $problems = [];

    for ($index = 0; $index < 3; $index += 1) {
        $problems[] = match ($subtopic) {
            'rutinas' => generateRoutineTimeProblem($difficulty),
            'duracion' => generateDurationProblem($difficulty),
            'antes-despues' => generateBeforeAfterTimeProblem($difficulty),
            default => generateClockProblem($difficulty),
        };
    }

    return $problems;
}

function generateClockProblem(string $difficulty): array
{
    if ($difficulty === 'hard') {
        $hour = random_int(1, 10);
        $minute = [5, 10, 15, 20, 25, 35, 40, 45][array_rand([5, 10, 15, 20, 25, 35, 40, 45])];
        $duration = [25, 35, 40, 45, 50][array_rand([25, 35, 40, 45, 50])];
        $answer = addMinutesToTime($hour, $minute, $duration);
        $start = formatTime($hour, $minute);

        return timeProblem(
            'El reloj marca las ' . $start . ' y pasan ' . $duration . ' minutos. ¿Qué hora marca después?',
            $answer,
            'Suma los minutos al reloj y cambia de hora si pasas de 60.',
            'Empiezas en ' . $start . '. Al sumar ' . $duration . ' minutos llegas a ' . $answer . '.'
        );
    }

    $minuteChoices = $difficulty === 'medium' ? [0, 15, 30, 45] : [0, 30];
    $hour = random_int(1, 12);
    $minute = $minuteChoices[array_rand($minuteChoices)];
    $answer = formatTime($hour, $minute);

    return timeProblem(
        '¿Qué hora muestra un reloj digital que dice ' . $answer . '?',
        $answer,
        'Lee primero la hora y después los minutos.',
        'El reloj ya muestra ' . $answer . '. Esa es la hora correcta.'
    );
}

function generateRoutineTimeProblem(string $difficulty): array
{
    $activities = [
        ['name' => 'desayunar', 'end' => [7, 45]],
        ['name' => 'preparar la mochila', 'end' => [8, 10]],
        ['name' => 'leer antes de dormir', 'end' => [20, 30]],
        ['name' => 'hacer deberes', 'end' => [18, 15]],
    ];
    $activity = $activities[array_rand($activities)];

    if ($difficulty === 'easy') {
        [$hour, $minute] = $activity['end'];
        $start = addMinutesToTime($hour, $minute, -30);
        $end = formatTime($hour, $minute);

        return [
            'question' => 'Empiezas a ' . $activity['name'] . ' a las ' . $start . ' y terminas a las ' . $end . '. ¿Qué pasa primero?',
            'type' => 'multiple_choice',
            'options' => ['Empezar', 'Terminar', 'Las dos a la vez', 'No se puede saber'],
            'correct_answer' => 'Empezar',
            'hint' => 'Compara las dos horas.',
            'explanation' => $start . ' ocurre antes que ' . $end . '. Por eso primero empiezas.',
        ];
    }

    $duration = $difficulty === 'hard'
        ? [25, 35, 45][array_rand([25, 35, 45])]
        : [15, 20, 30][array_rand([15, 20, 30])];
    [$endHour, $endMinute] = $activity['end'];
    $end = formatTime($endHour, $endMinute);
    $answer = addMinutesToTime($endHour, $endMinute, -$duration);

    return timeProblem(
        'Terminas de ' . $activity['name'] . ' a las ' . $end . '. Si tardas ' . $duration . ' minutos, ¿a qué hora empiezas?',
        $answer,
        'Resta la duración a la hora de terminar.',
        'Si terminas a las ' . $end . ' y tardas ' . $duration . ' minutos, empiezas a las ' . $answer . '.'
    );
}

function generateDurationProblem(string $difficulty): array
{
    $startHour = random_int(2, 7);
    $startMinute = $difficulty === 'easy' ? 0 : [0, 15, 20, 30, 45][array_rand([0, 15, 20, 30, 45])];
    $duration = match ($difficulty) {
        'hard' => [35, 45, 50, 65][array_rand([35, 45, 50, 65])],
        'medium' => [20, 30, 40, 45][array_rand([20, 30, 40, 45])],
        default => [10, 15, 30][array_rand([10, 15, 30])],
    };
    $end = addMinutesToTime($startHour, $startMinute, $duration);
    $start = formatTime($startHour, $startMinute);

    return [
        'question' => 'Empiezas a las ' . $start . ' y terminas a las ' . $end . '. ¿Cuánto tiempo pasa?',
        'type' => 'multiple_choice',
        'options' => buildMinuteOptions($duration),
        'correct_answer' => $duration . ' minutos',
        'hint' => 'Cuenta desde la hora de inicio hasta la hora final.',
        'explanation' => 'De ' . $start . ' a ' . $end . ' pasan ' . $duration . ' minutos.',
    ];
}

function generateBeforeAfterTimeProblem(string $difficulty): array
{
    $hour = random_int(1, 10);
    $minute = $difficulty === 'easy' ? [0, 30][array_rand([0, 30])] : [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
    $base = formatTime($hour, $minute);
    $step = $difficulty === 'hard' ? 45 : ($difficulty === 'medium' ? 30 : 15);
    $before = addMinutesToTime($hour, $minute, -$step);
    $after = addMinutesToTime($hour, $minute, $step);
    $askAfter = (bool) random_int(0, 1);

    return timeProblem(
        '¿Qué hora va ' . ($askAfter ? 'después' : 'antes') . ' de las ' . $base . '?',
        $askAfter ? $after : $before,
        'Mueve el reloj ' . $step . ' minutos ' . ($askAfter ? 'hacia adelante.' : 'hacia atrás.'),
        ($askAfter ? 'Después' : 'Antes') . ' de ' . $base . ' va ' . ($askAfter ? $after : $before) . '.'
    );
}

function timeProblem(string $question, string $answer, string $hint, string $explanation): array
{
    return [
        'question' => $question,
        'type' => 'multiple_choice',
        'options' => buildTimeOptions($answer),
        'correct_answer' => $answer,
        'hint' => $hint,
        'explanation' => $explanation,
    ];
}

function formatTime(int $hour, int $minute): string
{
    $normalizedHour = (($hour - 1) % 12) + 1;

    return $normalizedHour . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
}

function addMinutesToTime(int $hour, int $minute, int $minutesToAdd): string
{
    $total = (($hour % 12) * 60) + $minute + $minutesToAdd;
    $total = (($total % 720) + 720) % 720;
    $newHour = intdiv($total, 60);
    $newMinute = $total % 60;

    return formatTime($newHour === 0 ? 12 : $newHour, $newMinute);
}

function buildTimeOptions(string $answer): array
{
    [$hour, $minute] = array_map('intval', explode(':', $answer));
    $options = [$answer];
    $offsets = [-30, -15, 15, 30, 45, -45, 60, -60];
    shuffle($offsets);

    foreach ($offsets as $offset) {
        $candidate = addMinutesToTime($hour, $minute, $offset);

        if (!in_array($candidate, $options, true)) {
            $options[] = $candidate;
        }

        if (count($options) === 4) {
            break;
        }
    }

    shuffle($options);

    return $options;
}

function buildMinuteOptions(int $answer): array
{
    $options = [$answer . ' minutos'];
    $offsets = [-15, -10, 10, 15, 20, -20, 30, -30];
    shuffle($offsets);

    foreach ($offsets as $offset) {
        $candidate = $answer + $offset;

        if ($candidate > 0 && !in_array($candidate . ' minutos', $options, true)) {
            $options[] = $candidate . ' minutos';
        }

        if (count($options) === 4) {
            break;
        }
    }

    shuffle($options);

    return $options;
}

function generateLocalMoneyProblems(string $subtopic, string $difficulty): array
{
    $problems = [];

    for ($index = 0; $index < 3; $index += 1) {
        $problems[] = match ($subtopic) {
            'precios' => generatePriceComparisonProblem($difficulty),
            'cambio' => generateChangeProblem($difficulty),
            'ahorrar' => generateSavingProblem($difficulty),
            default => generateEuroCoinProblem($difficulty),
        };
    }

    return $problems;
}

function generateEuroCoinProblem(string $difficulty): array
{
    if ($difficulty === 'hard') {
        $first = random_int(12, 35);
        $second = random_int(8, 24);
        $spent = random_int(5, 18);
        $answer = $first + $second - $spent;

        return euroProblem(
            'Tienes ' . $first . ' € y recibes ' . $second . ' €. Después gastas ' . $spent . ' €. ¿Cuánto dinero te queda?',
            $answer,
            'Suma primero lo que recibes y después resta lo que gastas.',
            $first . ' € + ' . $second . ' € son ' . ($first + $second) . ' €. Luego quedan ' . $answer . ' €.'
        );
    }

    [$min, $max] = $difficulty === 'medium' ? [8, 25] : [2, 12];
    $first = random_int($min, $max);
    $second = random_int($difficulty === 'medium' ? 4 : 1, $difficulty === 'medium' ? 15 : 8);
    $answer = $first + $second;

    return euroProblem(
        'Tienes ' . $first . ' € y te dan ' . $second . ' € más. ¿Cuánto dinero tienes?',
        $answer,
        'Junta las dos cantidades de euros.',
        $first . ' € + ' . $second . ' € son ' . $answer . ' €.'
    );
}

function generatePriceComparisonProblem(string $difficulty): array
{
    $items = ['un bocadillo', 'un cuaderno', 'un zumo', 'un juguete'];
    $firstItem = $items[array_rand($items)];
    $secondItem = $items[array_rand($items)];

    while ($secondItem === $firstItem) {
        $secondItem = $items[array_rand($items)];
    }

    [$min, $max] = $difficulty === 'hard' ? [12, 60] : ($difficulty === 'medium' ? [6, 35] : [2, 15]);
    $firstPrice = random_int($min, $max);
    $secondPrice = random_int($min, $max);

    while ($secondPrice === $firstPrice) {
        $secondPrice = random_int($min, $max);
    }

    $answer = min($firstPrice, $secondPrice);

    return euroProblem(
        ucfirst($firstItem) . ' cuesta ' . $firstPrice . ' € y ' . $secondItem . ' cuesta ' . $secondPrice . ' €. ¿Cuál es el precio menor?',
        $answer,
        'Compara los dos precios en euros.',
        $answer . ' € es el precio menor.'
    );
}

function generateChangeProblem(string $difficulty): array
{
    $payment = match ($difficulty) {
        'hard' => random_int(40, 90),
        'medium' => random_int(20, 50),
        default => random_int(8, 20),
    };
    $price = random_int(max(1, intdiv($payment, 3)), $payment - 1);
    $answer = $payment - $price;

    return euroProblem(
        'Pagas con ' . $payment . ' € algo que cuesta ' . $price . ' €. ¿Cuánto cambio recibes?',
        $answer,
        'Resta el precio al dinero que entregas.',
        $payment . ' € - ' . $price . ' € son ' . $answer . ' € de cambio.'
    );
}

function generateSavingProblem(string $difficulty): array
{
    $goal = match ($difficulty) {
        'hard' => random_int(45, 100),
        'medium' => random_int(25, 60),
        default => random_int(10, 25),
    };
    $saved = random_int(max(1, intdiv($goal, 3)), $goal - 2);
    $answer = $goal - $saved;

    return euroProblem(
        'Quieres ahorrar ' . $goal . ' € y ya tienes ' . $saved . ' €. ¿Cuánto te falta?',
        $answer,
        'Resta lo que ya tienes al objetivo.',
        $goal . ' € - ' . $saved . ' € son ' . $answer . ' €. Eso es lo que falta.'
    );
}

function euroProblem(string $question, int $answer, string $hint, string $explanation): array
{
    return [
        'question' => $question,
        'type' => 'multiple_choice',
        'options' => buildEuroOptions($answer),
        'correct_answer' => $answer . ' €',
        'hint' => $hint,
        'explanation' => $explanation,
    ];
}

function buildEuroOptions(int $answer): array
{
    return array_map(static fn (string $option): string => $option . ' €', buildNumberOptions($answer));
}

function problemHasNonSpainMoneyTerms(array $problem): bool
{
    $text = implode(' ', [
        (string) ($problem['question'] ?? ''),
        implode(' ', array_map('strval', is_array($problem['options'] ?? null) ? $problem['options'] : [])),
        (string) ($problem['correct_answer'] ?? ''),
        (string) ($problem['hint'] ?? ''),
        (string) ($problem['explanation'] ?? ''),
    ]);

    return (bool) preg_match('/\\b(peso|pesos|d[oó]lar|d[oó]lares|centavo|centavos)\\b|\\$/iu', $text);
}

function getFallbackProblemBank(): array
{
    return [
        'math' => [
            'easy' => [
                'sumar' => [
                ['question' => '¿Cuánto es 8 + 5?', 'options' => ['11', '12', '13', '14'], 'correct_answer' => '13', 'hint' => 'Empieza en 8 y suma 5 más.', 'explanation' => '8 + 5 es 13. Puedes contar 9, 10, 11, 12 y 13.'],
                ['question' => '¿Cuánto es 7 + 6?', 'options' => ['11', '12', '13', '14'], 'correct_answer' => '13', 'hint' => 'Haz 7 + 3 para llegar a 10. Luego suma 3 más.', 'explanation' => '7 + 3 es 10. Faltan 3 más. 10 + 3 es 13.'],
                ['question' => '¿Cuánto es 9 + 4?', 'options' => ['12', '13', '14', '15'], 'correct_answer' => '13', 'hint' => 'Empieza en 9 y cuenta 4 pasos.', 'explanation' => 'Desde 9 cuentas 10, 11, 12 y 13. Entonces 9 + 4 es 13.'],
                ],
                'restar' => [
                ['question' => '¿Cuánto es 14 - 6?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '8', 'hint' => 'Piensa cuánto falta de 6 a 14.', 'explanation' => 'De 6 a 14 faltan 8. Por eso 14 - 6 es 8.'],
                ['question' => '¿Cuánto es 13 - 5?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '8', 'hint' => 'Quita 5 desde 13.', 'explanation' => '13 - 5 es 8.'],
                ['question' => '¿Cuánto es 15 - 7?', 'options' => ['7', '8', '9', '10'], 'correct_answer' => '8', 'hint' => 'Busca cuánto falta de 7 a 15.', 'explanation' => '7 + 8 llega a 15. Entonces 15 - 7 es 8.'],
                ],
                'comparar' => [
                ['question' => '¿Qué número es mayor: 9 o 13?', 'options' => ['9', '13', 'Son iguales', 'Ninguno'], 'correct_answer' => '13', 'hint' => 'El mayor aparece después al contar.', 'explanation' => '13 viene después de 9. Por eso 13 es mayor.'],
                ['question' => '¿Qué número es menor: 15 o 8?', 'options' => ['15', '8', 'Son iguales', 'Ninguno'], 'correct_answer' => '8', 'hint' => 'El menor aparece antes al contar.', 'explanation' => '8 viene antes que 15. Por eso 8 es menor.'],
                ['question' => '¿Cuál opción muestra números iguales?', 'options' => ['8 y 12', '11 y 11', '9 y 13', '15 y 10'], 'correct_answer' => '11 y 11', 'hint' => 'Busca la pareja que repite el mismo número.', 'explanation' => '11 y 11 son iguales. Los dos tienen el mismo valor.'],
                ],
                'problemas' => [
                ['question' => 'Ana tiene 8 flores y recibe 5 más. ¿Cuántas flores tiene?', 'options' => ['11', '12', '13', '14'], 'correct_answer' => '13', 'hint' => 'Junta 8 flores con 5 flores.', 'explanation' => '8 + 5 es 13. Ana tiene 13 flores.'],
                ['question' => 'Hay 14 galletas y comes 6. ¿Cuántas quedan?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '8', 'hint' => 'Quita 6 desde 14.', 'explanation' => '14 - 6 es 8. Quedan 8 galletas.'],
                ['question' => 'Leo tiene 7 coches y recibe 6 más. ¿Cuántos tiene?', 'options' => ['11', '12', '13', '14'], 'correct_answer' => '13', 'hint' => 'Junta 7 y 6.', 'explanation' => '7 + 6 es 13. Leo tiene 13 coches.'],
                ],
            ],
            'medium' => [
                'sumar' => [
                ['question' => '¿Cuánto es 9 + 7?', 'options' => ['14', '15', '16', '17'], 'correct_answer' => '16', 'hint' => 'Completa 10 desde 9 y sigue contando.', 'explanation' => '9 + 1 hace 10. Quedan 6 más. 10 + 6 es 16.'],
                ['question' => '¿Cuánto es 12 + 8?', 'options' => ['18', '19', '20', '21'], 'correct_answer' => '20', 'hint' => 'Piensa en llegar a 20.', 'explanation' => '12 + 8 llega justo a 20.'],
                ['question' => '¿Cuánto es 15 + 6?', 'options' => ['19', '20', '21', '22'], 'correct_answer' => '21', 'hint' => 'Desde 15 cuenta 6 pasos.', 'explanation' => '15 + 5 es 20. Un paso más da 21.'],
                ],
                'restar' => [
                ['question' => '¿Cuánto es 12 - 8?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'hint' => 'Piensa cuánto falta de 8 a 12.', 'explanation' => 'De 8 a 12 faltan 4. Por eso 12 - 8 es 4.'],
                ['question' => '¿Cuánto es 17 - 9?', 'options' => ['6', '7', '8', '9'], 'correct_answer' => '8', 'hint' => 'De 9 a 17 hay que subir.', 'explanation' => '9 + 8 llega a 17. Entonces 17 - 9 es 8.'],
                ['question' => '¿Cuánto es 20 - 7?', 'options' => ['11', '12', '13', '14'], 'correct_answer' => '13', 'hint' => 'Quita 7 desde 20.', 'explanation' => '20 - 7 es 13.'],
                ],
                'comparar' => [
                ['question' => '¿Qué número es mayor: 14 o 19?', 'options' => ['14', '19', 'Son iguales', 'Ninguno'], 'correct_answer' => '19', 'hint' => 'Mira cuál está más cerca de 20.', 'explanation' => '19 está más cerca de 20. Por eso es mayor que 14.'],
                ['question' => '¿Qué número es menor: 18 o 11?', 'options' => ['18', '11', 'Son iguales', 'Ninguno'], 'correct_answer' => '11', 'hint' => 'El menor aparece antes al contar.', 'explanation' => '11 aparece antes que 18. Por eso 11 es menor.'],
                ['question' => '¿Cuál es mayor que 13?', 'options' => ['9', '11', '14', '13'], 'correct_answer' => '14', 'hint' => 'Busca el número que pasa de 13.', 'explanation' => '14 viene después de 13. Por eso es mayor.'],
                ],
                'problemas' => [
                ['question' => 'Lucía tiene 12 fresas y come 8. ¿Cuántas quedan?', 'options' => ['3', '4', '5', '6'], 'correct_answer' => '4', 'hint' => 'Es una resta: 12 - 8.', 'explanation' => '12 - 8 es 4. Quedan 4 fresas.'],
                ['question' => 'Hay 9 libros y llegan 7 más. ¿Cuántos libros hay?', 'options' => ['14', '15', '16', '17'], 'correct_answer' => '16', 'hint' => 'Junta 9 y 7.', 'explanation' => '9 + 7 es 16. Hay 16 libros.'],
                ['question' => 'Tienes 20 cartas y regalas 7. ¿Cuántas quedan?', 'options' => ['11', '12', '13', '14'], 'correct_answer' => '13', 'hint' => 'Quita 7 desde 20.', 'explanation' => '20 - 7 es 13. Quedan 13 cartas.'],
                ],
            ],
            'hard' => [
                'sumar' => [
                ['question' => '¿Cuánto es 33 + 13 - 2?', 'options' => ['42', '43', '44', '45'], 'correct_answer' => '44', 'hint' => 'Primero suma 33 + 13. Luego quita 2.', 'explanation' => '33 + 13 es 46. Luego 46 - 2 es 44.'],
                ['question' => '¿Cuánto es 28 + 17 - 5?', 'options' => ['38', '39', '40', '41'], 'correct_answer' => '40', 'hint' => 'Haz la suma primero.', 'explanation' => '28 + 17 es 45. Luego 45 - 5 es 40.'],
                ['question' => '¿Cuánto es 41 + 12 - 8?', 'options' => ['43', '44', '45', '46'], 'correct_answer' => '45', 'hint' => 'Suma y después resta.', 'explanation' => '41 + 12 es 53. Después 53 - 8 es 45.'],
                ],
                'restar' => [
                ['question' => '¿Cuánto es 48 - 12 + 6?', 'options' => ['40', '41', '42', '43'], 'correct_answer' => '42', 'hint' => 'Primero quita 12. Luego suma 6.', 'explanation' => '48 - 12 es 36. Luego 36 + 6 es 42.'],
                ['question' => '¿Cuánto es 60 - 25 + 8?', 'options' => ['41', '42', '43', '44'], 'correct_answer' => '43', 'hint' => 'Haz la resta grande primero.', 'explanation' => '60 - 25 es 35. Luego 35 + 8 es 43.'],
                ['question' => '¿Cuánto es 72 - 18 - 4?', 'options' => ['48', '49', '50', '51'], 'correct_answer' => '50', 'hint' => 'Resta en dos pasos.', 'explanation' => '72 - 18 es 54. Luego 54 - 4 es 50.'],
                ],
                'comparar' => [
                ['question' => '¿Qué resultado es mayor: 33 + 13 o 60 - 20?', 'options' => ['33 + 13', '60 - 20', 'Son iguales', 'Ninguno'], 'correct_answer' => '33 + 13', 'hint' => 'Calcula los dos resultados.', 'explanation' => '33 + 13 es 46. 60 - 20 es 40. 46 es mayor.'],
                ['question' => '¿Qué resultado es menor: 70 - 18 o 30 + 15?', 'options' => ['70 - 18', '30 + 15', 'Son iguales', 'Ninguno'], 'correct_answer' => '30 + 15', 'hint' => 'Resuelve las dos operaciones.', 'explanation' => '70 - 18 es 52. 30 + 15 es 45. 45 es menor.'],
                ['question' => '¿Cuál da 44?', 'options' => ['33 + 13 - 2', '20 + 10 + 5', '60 - 10 - 8', '12 + 12 + 12'], 'correct_answer' => '33 + 13 - 2', 'hint' => 'Calcula cada opción poco a poco.', 'explanation' => '33 + 13 es 46. 46 - 2 es 44.'],
                ],
                'problemas' => [
                ['question' => 'Lucía tiene 15 fresas, recibe 8 y come 7. ¿Cuántas le quedan?', 'options' => ['14', '15', '16', '17'], 'correct_answer' => '16', 'hint' => 'Primero suma las fresas. Luego resta las que come.', 'explanation' => '15 + 8 es 23. Luego 23 - 7 es 16.'],
                ['question' => 'Hay 33 cromos, llegan 13 y se pierden 2. ¿Cuántos quedan?', 'options' => ['42', '43', '44', '45'], 'correct_answer' => '44', 'hint' => 'Suma primero. Después quita 2.', 'explanation' => '33 + 13 es 46. Luego 46 - 2 es 44.'],
                ['question' => 'Una caja tiene 48 lápices. Se usan 12 y llegan 6. ¿Cuántos hay?', 'options' => ['40', '41', '42', '43'], 'correct_answer' => '42', 'hint' => 'Quita 12 y luego suma 6.', 'explanation' => '48 - 12 es 36. Luego 36 + 6 es 42.'],
                ],
            ],
        ],
        'logic' => [
            'easy' => [
                'series' => fallbackTriplet('¿Qué sigue? rojo, azul, rojo, azul...', ['Rojo', 'Azul', 'Verde', 'Amarillo'], 'Rojo', 'El patrón repite rojo y azul. Después de azul vuelve rojo.'),
                'formas' => fallbackTriplet('¿Qué forma tiene 3 lados?', ['Círculo', 'Triángulo', 'Cuadrado', 'Estrella'], 'Triángulo', 'Un triángulo tiene 3 lados.'),
                'adivinanzas' => fallbackTriplet('Soy más grande que 5 y menor que 7. ¿Qué soy?', ['4', '5', '6', '7'], '6', '6 está entre 5 y 7.'),
                'ordenar' => fallbackTriplet('¿Qué va primero: desayunar o dormir por la noche?', ['Desayunar', 'Dormir', 'Cenar', 'Merendar'], 'Desayunar', 'Desayunar pasa por la mañana. Dormir por la noche va después.'),
            ],
            'medium' => [
                'series' => fallbackTriplet('¿Qué sigue? 2, 4, 6, 8...', ['9', '10', '11', '12'], '10', 'La serie suma 2 cada vez. Después de 8 viene 10.'),
                'formas' => fallbackTriplet('Una figura tiene 4 lados iguales. ¿Cuál es?', ['Triángulo', 'Círculo', 'Cuadrado', 'Óvalo'], 'Cuadrado', 'El cuadrado tiene 4 lados iguales.'),
                'adivinanzas' => fallbackTriplet('Tengo 2 decenas y 3 unidades. ¿Qué número soy?', ['20', '21', '23', '32'], '23', '2 decenas son 20. 3 unidades más hacen 23.'),
                'ordenar' => fallbackTriplet('Ordena de menor a mayor: 14, 9, 12. ¿Cuál va primero?', ['14', '9', '12', 'Todos'], '9', '9 es el menor. Por eso va primero.'),
            ],
            'hard' => [
                'series' => fallbackTriplet('¿Qué sigue? 3, 6, 12, 24...', ['30', '36', '48', '50'], '48', 'Cada número se duplica. 24 + 24 es 48.'),
                'formas' => fallbackTriplet('Tengo más lados que un triángulo y menos que un pentágono. ¿Qué soy?', ['Círculo', 'Cuadrado', 'Pentágono', 'Hexágono'], 'Cuadrado', 'Un triángulo tiene 3 lados. Un pentágono tiene 5. El cuadrado tiene 4.'),
                'adivinanzas' => fallbackTriplet('Si todos los zups son rojos y este zup es azul, ¿qué sabemos?', ['Es rojo', 'No es zup', 'Es todos', 'No sabemos nada'], 'No es zup', 'Todos los zups son rojos. Si es azul, no puede ser zup.'),
                'ordenar' => fallbackTriplet('Si Ana llegó antes que Leo y Leo antes que Sara, ¿quién llegó primero?', ['Ana', 'Leo', 'Sara', 'Empate'], 'Ana', 'Ana llegó antes que Leo. Leo llegó antes que Sara. Ana fue primera.'),
            ],
        ],
        'time' => [
            'easy' => [
                'relojes' => fallbackTriplet('¿Qué hora es si el reloj marca las 3:00?', ['3:00', '4:00', '3:30', '12:00'], '3:00', 'Cuando la aguja marca el 3 y los minutos están en 00, son las 3:00.'),
                'rutinas' => fallbackTriplet('Sales al cole a las 8:30 y desayunas 30 minutos antes. ¿A qué hora desayunas?', ['8:00', '8:15', '8:30', '9:00'], '8:00', '30 minutos antes de las 8:30 son las 8:00.'),
                'duracion' => fallbackTriplet('Si juegas 10 minutos, ¿cuánto tiempo pasa?', ['5 minutos', '10 minutos', '1 hora', '30 minutos'], '10 minutos', 'La duración es el tiempo que dura la actividad. Aquí son 10 minutos.'),
                'antes-despues' => fallbackTriplet('¿Qué va antes: comer o lavar el plato?', ['Comer', 'Lavar el plato', 'Dormir', 'Cenar'], 'Comer', 'Primero comes. Después puedes lavar el plato.'),
            ],
            'medium' => [
                'relojes' => fallbackTriplet('¿Qué hora es media hora después de las 4:00?', ['4:15', '4:30', '5:00', '3:30'], '4:30', 'Media hora son 30 minutos. 4:00 más 30 minutos es 4:30.'),
                'rutinas' => fallbackTriplet('Si sales al cole a las 8:30 y desayunas antes, ¿qué pasa primero?', ['Salir', 'Desayunar', 'Volver', 'Dormir'], 'Desayunar', 'Desayunas antes de salir. Por eso va primero.'),
                'duracion' => fallbackTriplet('Empiezas a leer a las 5:00 y terminas a las 5:30. ¿Cuánto lees?', ['15 minutos', '30 minutos', '1 hora', '5 minutos'], '30 minutos', 'De 5:00 a 5:30 pasan 30 minutos.'),
                'antes-despues' => fallbackTriplet('¿Qué hora va después de las 6:45?', ['6:15', '6:30', '7:00', '5:45'], '7:00', 'Después de 6:45 viene 7:00.'),
            ],
            'hard' => [
                'relojes' => fallbackTriplet('Empiezas a las 2:15 y pasan 45 minutos. ¿Qué hora es?', ['2:45', '3:00', '3:15', '4:00'], '3:00', 'De 2:15 a 2:45 pasan 30 minutos. Faltan 15 más. Llegas a 3:00.'),
                'rutinas' => fallbackTriplet('Tardas 20 minutos en comer y 15 en recoger. Empiezas a las 1:00. ¿Cuándo acabas?', ['1:20', '1:30', '1:35', '1:45'], '1:35', '20 + 15 son 35 minutos. 1:00 más 35 minutos es 1:35.'),
                'duracion' => fallbackTriplet('Una película empieza a las 6:20 y termina a las 7:05. ¿Cuánto dura?', ['35 minutos', '40 minutos', '45 minutos', '50 minutos'], '45 minutos', 'De 6:20 a 7:00 pasan 40 minutos. De 7:00 a 7:05 pasan 5 más. Son 45 minutos.'),
                'antes-despues' => fallbackTriplet('Si meriendas 15 minutos después de las 5:40, ¿a qué hora meriendas?', ['5:45', '5:55', '6:00', '6:15'], '5:55', '5:40 más 15 minutos es 5:55.'),
            ],
        ],
        'money' => [
            'easy' => [
                'monedas' => fallbackTriplet('Tienes 5 monedas y recibes 4. ¿Cuántas tienes?', ['7', '8', '9', '10'], '9', '5 + 4 es 9.'),
                'precios' => fallbackTriplet('Un lápiz cuesta 8 y una goma 6. ¿Cuál cuesta más?', ['Lápiz', 'Goma', 'Igual', 'Ninguno'], 'Lápiz', '8 es mayor que 6. El lápiz cuesta más.'),
                'cambio' => fallbackTriplet('Tienes 10 y gastas 6. ¿Cuánto queda?', ['2', '3', '4', '5'], '4', '10 - 6 es 4.'),
                'ahorrar' => fallbackTriplet('Ahorras 7 y luego 5 más. ¿Cuánto ahorras?', ['11', '12', '13', '14'], '12', '7 + 5 es 12.'),
            ],
            'medium' => [
                'monedas' => fallbackTriplet('Tienes 12 monedas y recibes 9 más. ¿Cuántas tienes?', ['19', '20', '21', '22'], '21', '12 + 9 es 21.'),
                'precios' => fallbackTriplet('Un libro cuesta 24 y un juego 31. ¿Cuál cuesta menos?', ['Libro', 'Juego', 'Igual', 'Ninguno'], 'Libro', '24 es menor que 31.'),
                'cambio' => fallbackTriplet('Pagas con 30 algo que cuesta 18. ¿Cuánto cambio recibes?', ['10', '11', '12', '13'], '12', '30 - 18 es 12.'),
                'ahorrar' => fallbackTriplet('Quieres 35 y ya tienes 18. ¿Cuánto falta?', ['15', '16', '17', '18'], '17', '18 + 17 llega a 35.'),
            ],
            'hard' => [
                'monedas' => fallbackTriplet('Tienes 28 monedas, recibes 17 y gastas 9. ¿Cuántas quedan?', ['34', '35', '36', '37'], '36', '28 + 17 es 45. 45 - 9 es 36.'),
                'precios' => fallbackTriplet('Compras algo de 34 y otro de 18. Pagas 60. ¿Cuánto sobra?', ['6', '7', '8', '9'], '8', '34 + 18 es 52. 60 - 52 es 8.'),
                'cambio' => fallbackTriplet('Pagas con 80 dos cosas de 27 y 19. ¿Cuánto cambio recibes?', ['32', '33', '34', '35'], '34', '27 + 19 es 46. 80 - 46 es 34.'),
                'ahorrar' => fallbackTriplet('Ahorras 22, luego 18, y gastas 7. ¿Cuánto queda?', ['31', '32', '33', '34'], '33', '22 + 18 es 40. 40 - 7 es 33.'),
            ],
        ],
    ];
}

function fallbackTriplet(string $question, array $options, string $correctAnswer, string $explanation): array
{
    return [
        ['question' => $question, 'options' => $options, 'correct_answer' => $correctAnswer, 'hint' => 'Lee el enunciado despacio y busca la pista importante.', 'explanation' => $explanation],
        ['question' => $question, 'options' => $options, 'correct_answer' => $correctAnswer, 'hint' => 'Prueba a resolverlo paso a paso.', 'explanation' => $explanation],
        ['question' => $question, 'options' => $options, 'correct_answer' => $correctAnswer, 'hint' => 'Elimina primero las respuestas que no pueden ser.', 'explanation' => $explanation],
    ];
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
