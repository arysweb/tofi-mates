<?php $practiceStats = getPracticeStats(); ?>
<div class="side-stack">
    <article class="panel streak-card">
        <div class="panel-header">
            <div>
                <h2>Actividad real</h2>
                <p>Datos leídos de la base de datos</p>
            </div>
        </div>
        <div class="streak-number"><?php echo e($practiceStats['total_sets']); ?></div>
        <p>Grupos de ejercicios preparados hasta ahora.</p>
        <div class="topic-stats">
            <span><?php echo e($practiceStats['stored_problems']); ?> ejercicios guardados</span>
            <span><?php echo e($practiceStats['answers']); ?> respuestas registradas</span>
        </div>
    </article>

    <article class="panel">
        <div class="panel-header">
            <div>
                <h2>Generador rápido</h2>
                <p>Retos listos para pedir a la IA.</p>
            </div>
        </div>

        <div class="task-list">
            <div class="task">
                <div class="task-mark">+</div>
                <div>
                    <strong>Mates · Restar</strong>
                    <span>Problemas nuevos para resolver ahora</span>
                </div>
                <a href="index.php?page=practice&topic=math&subtopic=restar">Pedir</a>
            </div>
            <div class="task">
                <div class="task-mark">?</div>
                <div>
                    <strong>Lógica · Series</strong>
                    <span>La IA ajusta la dificultad al niño</span>
                </div>
                <a href="index.php?page=practice&topic=logic&subtopic=series">Pedir</a>
            </div>
            <div class="task">
                <div class="task-mark">$</div>
                <div>
                    <strong>Dinero · Cambios</strong>
                    <span>Ejercicios cortos con monedas</span>
                </div>
                <a href="index.php?page=practice&topic=money&subtopic=cambio">Pedir</a>
            </div>
        </div>
    </article>
</div>
