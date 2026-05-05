<?php
declare(strict_types=1);

$dbStatusText = 'DB ERROR | not tested';

require_once __DIR__ . '/api/db.php';

try {
    $row = getDbHealthRow();
    $dbName = $row['db_name'] ?? 'unknown';
    $dbStatusText = 'DB OK | database=' . $dbName;
} catch (Throwable $e) {
    $dbStatusText = 'DB ERROR | ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TofiMates User Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@300;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,200,0,0" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/user-panel.css">
</head>
<body>
    <main class="user-panel">
        <aside class="floating-sidebar"></aside>
        <section class="content-area"><?= htmlspecialchars($dbStatusText, ENT_QUOTES, 'UTF-8'); ?></section>
    </main>

    <script src="assets/js/user-panel.js"></script>
</body>
</html>
