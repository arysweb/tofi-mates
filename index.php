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
$isAuthPage = in_array($currentPage, ['login', 'register'], true);
?>
<!doctype html>
<html lang="es" data-csrf="<?php echo e($csrfToken); ?>">
<?php require __DIR__ . '/includes/head.php'; ?>
<body class="<?php echo $isAuthPage ? 'auth-page' : ''; ?>">
    <main class="app-shell <?php echo $isAuthPage ? 'auth-app-shell' : ''; ?>">
        <?php if (!$isAuthPage): ?>
            <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <?php endif; ?>
        <?php require __DIR__ . '/includes/' . $pageMeta['include']; ?>
        <?php if (!$isAuthPage): ?>
            <?php require __DIR__ . '/includes/footer.php'; ?>
        <?php endif; ?>
    </main>
</body>
</html>
