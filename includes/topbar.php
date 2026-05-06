<header class="topbar">
    <div>
        <h1><?php echo e($pageMeta['title'] ?? 'Buenas tardes, Mia.'); ?></h1>
        <p><?php echo e($pageMeta['subtitle'] ?? 'Elige un reto y mantén viva tu racha de práctica.'); ?></p>
    </div>
    <?php if ($currentUser !== null): ?>
        <div class="topbar-account" aria-label="Cuenta activa">
            <img src="<?php echo e($currentUser['avatar_url'] ?? ''); ?>" alt="" width="38" height="38" loading="lazy">
            <div>
                <strong><?php echo e($currentUser['display_name'] ?? 'Cuenta'); ?></strong>
                <small><?php echo e($currentUser['role'] ?? 'guardian'); ?></small>
            </div>
        </div>
    <?php endif; ?>
</header>
