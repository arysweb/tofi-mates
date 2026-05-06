<section class="content auth-content">
    <section class="auth-shell">
        <article class="auth-card">
            <div class="auth-brand-lockup">
                <img src="assets/img/logo.png" alt="Panda">
                <div>
                    <strong>Panda</strong>
                    <span>Pequeños logros cada día</span>
                </div>
            </div>

            <div class="auth-copy">
                <span class="eyebrow"><span class="dot"></span> Acceso familiar</span>
                <h1>Vuelve a practicar.</h1>
                <p>Entra para guardar la práctica, proteger el progreso y mantener cada sesión asociada a tu cuenta.</p>
            </div>

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

            <p class="auth-switch">Sin cuenta todavía? <a class="focus-ring" href="index.php?page=register">Crear cuenta segura</a></p>
        </article>

        <aside class="auth-visual-card" aria-label="Vista de aprendizaje Panda">
            <div class="auth-visual-glow"></div>
            <div class="auth-visual-image">
                <img src="assets/img/logo.png" alt="">
            </div>
            <div class="auth-floating-card auth-floating-card-main">
                <span>3 retos</span>
                <strong>listos para hoy</strong>
            </div>
            <div class="auth-floating-card auth-floating-card-small">
                <span>7 días</span>
                <strong>racha semanal</strong>
            </div>
            <div class="auth-visual-copy">
                <span>Progreso familiar</span>
                <h2>Todo el progreso queda en casa.</h2>
                <p>Retos cortos, pistas suaves y avances visibles para seguir practicando sin presión.</p>
            </div>
        </aside>
    </section>
</section>
