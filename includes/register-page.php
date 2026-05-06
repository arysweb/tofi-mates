<section class="content auth-content">
    <section class="auth-shell">
        <article class="auth-card">
            <span class="eyebrow"><span class="dot"></span> Cuenta segura</span>
            <h1>Crea tu acceso familiar.</h1>
            <p>Usaremos este acceso para guardar la práctica y separar el progreso de cada familia.</p>

            <form class="auth-form" data-auth-form data-auth-endpoint="api/auth-register.php" novalidate>
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
                    Nombre
                    <input class="focus-ring" type="text" name="display_name" autocomplete="name" maxlength="120" required>
                </label>

                <label>
                    Email
                    <input class="focus-ring" type="email" name="email" autocomplete="email" required>
                </label>

                <label>
                    Contraseña
                    <input class="focus-ring" type="password" name="password" autocomplete="new-password" minlength="12" required>
                </label>

                <p class="auth-help">Mínimo 12 caracteres y mezcla de mayúsculas, minúsculas, números o símbolos.</p>
                <button class="primary-button focus-ring" type="submit">Crear cuenta</button>
                <p class="auth-status" data-auth-status role="status" aria-live="polite"></p>
            </form>
        </article>

        <aside class="auth-side-card">
            <strong>Ya tienes cuenta?</strong>
            <p>Entra con tu email y contraseña. Las sesiones se guardan en base de datos y se pueden cerrar al instante.</p>
            <a class="secondary-button focus-ring" href="index.php?page=login">Entrar</a>
        </aside>
    </section>
</section>
