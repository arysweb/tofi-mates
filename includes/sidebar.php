<aside class="sidebar" aria-label="Navegación de aprendizaje">
    <div class="brand">
        <img class="brand-logo" src="assets/img/logo.png" alt="Panda">
        <div>
            <p class="brand-title">Panda</p>
            <p class="brand-subtitle">Pequeños logros cada día</p>
        </div>
    </div>

    <?php if ($currentUser === null): ?>
        <p class="nav-label">Cuenta</p>
        <nav class="nav-list">
            <a class="nav-link <?php echo $currentPage === 'login' ? 'active' : ''; ?> focus-ring" href="index.php?page=login">
                <span class="nav-icon">→</span>
                Entrar
            </a>
            <a class="nav-link <?php echo $currentPage === 'register' ? 'active' : ''; ?> focus-ring" href="index.php?page=register">
                <span class="nav-icon">+</span>
                Crear cuenta
            </a>
        </nav>
    <?php else: ?>
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

        <div class="sidebar-account">
            <div class="sidebar-account-profile">
                <img src="<?php echo e($currentUser['avatar_url'] ?? ''); ?>" alt="" width="42" height="42" loading="lazy">
                <div>
                    <strong><?php echo e($currentUser['display_name'] ?? 'Cuenta'); ?></strong>
                    <span><?php echo e($currentUser['email'] ?? ''); ?></span>
                </div>
            </div>
            <form method="post" action="api/auth-logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <button class="focus-ring" type="submit">Salir</button>
            </form>
        </div>
    <?php endif; ?>
</aside>
