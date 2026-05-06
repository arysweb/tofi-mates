<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

function getPracticeStats(): array
{
    static $stats = null;

    if (is_array($stats)) {
        return $stats;
    }

    $stats = emptyPracticeStats();

    try {
        $pdo = getDbConnection();
        $stats['total_sets'] = (int) $pdo->query('SELECT count(*) FROM practice_sets')->fetchColumn();
        $stats['stored_problems'] = (int) $pdo->query('SELECT count(*) FROM problem_bank WHERE is_active = true')->fetchColumn();
        $stats['answers'] = (int) $pdo->query('SELECT count(*) FROM practice_answers')->fetchColumn();
        $stats['correct_answers'] = (int) $pdo->query('SELECT count(*) FROM practice_answers WHERE is_correct = true')->fetchColumn();
        $stats['week_sets'] = (int) $pdo->query("SELECT count(*) FROM practice_sets WHERE created_at >= date_trunc('week', now())")->fetchColumn();
        $stats['today_sets'] = (int) $pdo->query("SELECT count(*) FROM practice_sets WHERE created_at::date = current_date")->fetchColumn();
        $stats['weekly_counts'] = fetchWeeklyPracticeCounts($pdo);
        $stats['domain_counts'] = fetchDomainPracticeCounts($pdo);
        $stats['recent_sets'] = fetchRecentPracticeSets($pdo);
        $stats['streak_days'] = fetchPracticeStreakDays($pdo);
    } catch (Throwable $e) {
        $stats['db_available'] = false;
    }

    return $stats;
}

function emptyPracticeStats(): array
{
    return [
        'answers' => 0,
        'correct_answers' => 0,
        'db_available' => true,
        'domain_counts' => ['math' => 0, 'logic' => 0, 'time' => 0, 'money' => 0],
        'recent_sets' => [],
        'stored_problems' => 0,
        'streak_days' => 0,
        'today_sets' => 0,
        'total_sets' => 0,
        'week_sets' => 0,
        'weekly_counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0],
    ];
}

function fetchWeeklyPracticeCounts(PDO $pdo): array
{
    $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];
    $stmt = $pdo->query(
        "SELECT extract(isodow from created_at)::int AS day_number, count(*)::int AS total
         FROM practice_sets
         WHERE created_at >= date_trunc('week', now())
         GROUP BY day_number"
    );

    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['day_number']] = (int) $row['total'];
    }

    return $counts;
}

function fetchDomainPracticeCounts(PDO $pdo): array
{
    $counts = ['math' => 0, 'logic' => 0, 'time' => 0, 'money' => 0];
    $stmt = $pdo->query(
        "SELECT domain_slug, count(*)::int AS total
         FROM practice_sets
         GROUP BY domain_slug"
    );

    foreach ($stmt->fetchAll() as $row) {
        $domain = (string) $row['domain_slug'];

        if (array_key_exists($domain, $counts)) {
            $counts[$domain] = (int) $row['total'];
        }
    }

    return $counts;
}

function fetchRecentPracticeSets(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT domain_slug, subtopic_slug, difficulty, created_at
         FROM practice_sets
         ORDER BY created_at DESC
         LIMIT 3"
    );

    return $stmt->fetchAll();
}

function fetchPracticeStreakDays(PDO $pdo): int
{
    $rows = $pdo->query(
        "SELECT DISTINCT created_at::date AS activity_date
         FROM practice_sets
         ORDER BY activity_date DESC
         LIMIT 60"
    )->fetchAll();

    $dates = array_map(static fn (array $row): string => (string) $row['activity_date'], $rows);
    $cursor = new DateTimeImmutable('today');
    $streak = 0;

    while (in_array($cursor->format('Y-m-d'), $dates, true)) {
        $streak++;
        $cursor = $cursor->modify('-1 day');
    }

    return $streak;
}

function practiceDomainLabel(string $domain): string
{
    return [
        'logic' => 'Lógica',
        'math' => 'Mates',
        'money' => 'Dinero',
        'time' => 'Tiempo',
    ][$domain] ?? ucfirst($domain);
}

function practiceSubtopicLabel(string $subtopic): string
{
    return ucfirst(str_replace('-', ' ', $subtopic));
}
