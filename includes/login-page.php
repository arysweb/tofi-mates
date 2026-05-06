<section class="content auth-content">
    <section class="auth-shell">
        <article class="auth-card">
            <span class="eyebrow"><span class="dot"></span> Acceso familiar</span>
            <h1>Bienvenido de nuevo.</h1>
            <p>Entra para guardar la práctica, proteger el progreso y mantener cada sesión asociada a tu cuenta.</p>

            <form class="auth-form" data-auth-form data-auth-endpoint="api/auth-login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                <input type="hidden" name="form_started_at" value="<?php echo e(time()); ?>">
                <label class="auth-trap" aria-hidden="true">
                    Web
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
                <label class="auth-trap" aria-hidden="true">
                    Empresa
                    <input type="text" name="company" tabindex="-1" autocomplete="off">
                </label>

                <label>
                    Email
                    <input class="focus-ring" type="email" name="email" autocomplete="email" required>
                </label>

                <label>
                    Contraseña
                    <input class="focus-ring" type="password" name="password" autocomplete="current-password" required>
                </label>

                <button class="primary-button focus-ring" type="submit">Entrar</button>
                <p class="auth-status" data-auth-status role="status" aria-live="polite"></p>
            </form>
        </article>

        <aside class="auth-side-card">
            <strong>Sin cuenta todavía?</strong>
            <p>Crea una cuenta con email real y una contraseña fuerte. Bloqueamos emails temporales y contraseñas conocidas.</p>
            <a class="secondary-button focus-ring" href="index.php?page=register">Crear cuenta</a>
        </aside>
    </section>
</section>
