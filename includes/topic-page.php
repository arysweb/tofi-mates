<?php $topic = $topicPages[$currentPage]; ?>
<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <?php if ($currentPage === 'math'): ?>
        <section class="difficulty-panel" data-mates-difficulty>
            <div>
                <h2>Dificultad</h2>
                <p>Elige cómo de suaves o retadores serán los ejercicios de mates.</p>
            </div>

            <div class="difficulty-options" role="radiogroup" aria-label="Dificultad de mates">
                <button class="active" type="button" data-difficulty="easy">Suave</button>
                <button type="button" data-difficulty="medium">Normal</button>
                <button type="button" data-difficulty="hard">Reto</button>
            </div>
        </section>
    <?php endif; ?>

    <section class="page-hero topic-hero <?php echo e($topic['class']); ?>">
        <div>
            <span class="eyebrow"><span class="dot"></span> <?php echo e($topic['eyebrow']); ?></span>
            <h2><?php echo e($topic['title']); ?></h2>
            <p><?php echo e($topic['description']); ?></p>
            <div class="hero-actions">
                <a class="primary-button focus-ring" href="index.php?page=practice&topic=<?php echo e($currentPage); ?>"><?php echo e($topic['cta']); ?></a>
                <a class="secondary-button focus-ring" href="#subtemas">Ver subtemas</a>
            </div>
        </div>

        <div class="hero-play-card">
            <small><?php echo e($topic['heroCard']['label']); ?></small>
            <strong><?php echo e($topic['heroCard']['title']); ?></strong>
            <p><?php echo e($topic['heroCard']['text']); ?></p>
            <a class="play-chip" href="index.php?page=practice&topic=<?php echo e($currentPage); ?>" data-mates-practice-link><?php echo e($topic['heroCard']['meta']); ?></a>
        </div>
    </section>

    <section class="topic-layout">
        <div class="panel" id="subtemas">
            <div class="panel-header">
                <div>
                    <h2>Subtemas</h2>
                    <p>El niño elige una opción y la IA prepara ejercicios nuevos.</p>
                </div>
            </div>

            <div class="subtopic-grid">
                <?php foreach ($topic['subtopics'] as $subtopic): ?>
                    <article class="subtopic-card">
                        <div class="subtopic-icon"><?php echo e($subtopic['icon']); ?></div>
                        <h3><?php echo e($subtopic['title']); ?></h3>
                        <p><?php echo e($subtopic['text']); ?></p>
                        <a href="index.php?page=practice&topic=<?php echo e($currentPage); ?>&subtopic=<?php echo e($subtopic['slug']); ?>" data-mates-practice-link>Preparar reto</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="side-stack">
            <article class="panel guidance-panel">
                <div class="panel-header">
                    <div>
                        <h2>Consejo</h2>
                        <p><?php echo e($topic['coach']); ?></p>
                    </div>
                </div>

                <div class="guidance-note">
                    <strong>Empieza fácil, responde y sigue con el siguiente reto cuando esté listo.</strong>
                </div>
            </article>

            <article class="panel stats-panel">
                <div class="panel-header">
                    <div>
                        <h2>Hoy</h2>
                        <p><?php echo e($topic['daily']); ?></p>
                    </div>
                </div>

                <div class="topic-stats">
                    <?php foreach ($topic['stats'] as $stat): ?>
                        <span><?php echo e($stat); ?></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="panel sample-panel" id="vista-previa">
                <div class="panel-header">
                    <div>
                        <h2><?php echo e($topic['sample']['label']); ?></h2>
                        <p>Así se verá un reto generado.</p>
                    </div>
                </div>
                <div class="sample-question">
                    <strong><?php echo e($topic['sample']['question']); ?></strong>
                    <div class="answer-row">
                        <?php foreach ($topic['sample']['answers'] as $answerIndex => $answer): ?>
                            <span class="answer <?php echo $topic['sample']['correct'] === $answerIndex ? 'correct' : ''; ?>"><?php echo e($answer); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        </aside>
    </section>
</section>
