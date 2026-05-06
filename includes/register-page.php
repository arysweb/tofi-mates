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
                <span class="eyebrow"><span class="dot"></span> Cuenta segura</span>
                <h1>Crea tu cuenta.</h1>
                <p>Usaremos este acceso para guardar la práctica y separar el progreso de cada familia.</p>
            </div>

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

            <p class="auth-switch">Ya tienes cuenta? <a class="focus-ring" href="index.php?page=login">Entrar</a></p>
        </article>

        <aside class="auth-visual-card" aria-label="Cuenta segura Panda">
            <div class="auth-visual-glow"></div>
            <div class="auth-visual-image">
                <img src="assets/img/logo.png" alt="">
            </div>
            <div class="auth-floating-card auth-floating-card-main">
                <span>4 temas</span>
                <strong>mates, lógica y más</strong>
            </div>
            <div class="auth-floating-card auth-floating-card-small">
                <span>+ puntos</span>
                <strong>logros visibles</strong>
            </div>
            <div class="auth-visual-copy">
                <span>Para familias</span>
                <h2>Empieza con retos cortos.</h2>
                <p>Cada cuenta guarda ejercicios, respuestas y pequeñas victorias para que el niño vea su avance.</p>
            </div>
        </aside>
    </section>
</section>
