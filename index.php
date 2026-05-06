<?php
require __DIR__ . '/includes/practice-stats.php';
require __DIR__ . '/includes/page-data.php';
require __DIR__ . '/includes/auth.php';

authApplySecurityHeaders();

$currentPage = isset($_GET['page']) ? preg_replace('/[^a-z-]/', '', $_GET['page']) : 'home';

if (!isset($pageRoutes[$currentPage])) {
    $currentPage = 'home';
}

$currentUser = authRequireUserPage($currentPage);
$csrfToken = authCsrfToken();
$pageMeta = $pageRoutes[$currentPage];
?>
<!doctype html>
<html lang="es" data-csrf="<?php echo e($csrfToken); ?>">
<?php require __DIR__ . '/includes/head.php'; ?>
<body>
    <main class="app-shell">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <?php require __DIR__ . '/includes/' . $pageMeta['include']; ?>
        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</body>
</html>
