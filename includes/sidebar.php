<aside class="sidebar" aria-label="Navegación de aprendizaje">
    <div class="brand">
        <img class="brand-logo" src="assets/img/logo.png" alt="Panda">
        <div>
            <p class="brand-title">Panda</p>
            <p class="brand-subtitle">Pequeños logros cada día</p>
        </div>
    </div>

    <p class="nav-label">Aprender</p>
    <nav class="nav-list">
        <?php foreach ($pageRoutes as $pageKey => $route): ?>
            <?php if (isset($route['nav']) && $route['nav'] === false) {
                continue;
            } ?>
            <a class="nav-link <?php echo $currentPage === $pageKey ? 'active' : ''; ?> focus-ring" href="<?php echo $pageKey === 'home' ? 'index.php' : 'index.php?page=' . e($pageKey); ?>">
                <span class="nav-icon"><?php echo e($route['icon']); ?></span>
                <?php echo e($route['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
