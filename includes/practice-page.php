<?php
$practiceTopicKey = isset($_GET['topic']) && isset($topicPages[$_GET['topic']]) ? $_GET['topic'] : 'math';
$practiceTopic = $topicPages[$practiceTopicKey];
$practiceSubtopic = isset($_GET['subtopic']) ? preg_replace('/[^a-z-]/', '', $_GET['subtopic']) : $practiceTopic['subtopics'][0]['slug'];
?>
<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <section class="practice-shell" data-practice="true" data-domain="<?php echo e($practiceTopicKey); ?>" data-subtopic="<?php echo e($practiceSubtopic); ?>" data-difficulty="<?php echo e(isset($_GET['difficulty']) ? preg_replace('/[^a-z]/', '', $_GET['difficulty']) : 'easy'); ?>">
        <div class="practice-main panel">
            <div class="practice-topline">
                <a class="text-link focus-ring" href="index.php?page=<?php echo e($practiceTopicKey); ?>">Volver a <?php echo e($practiceTopic['title']); ?></a>
                <span data-practice-status>Reto de práctica</span>
            </div>

            <div class="practice-question-card">
                <div class="practice-problems-list" data-problems-list>
                    <article class="practice-problem-card is-loading">
                        <h2>Preparando ejercicios...</h2>
                        <div class="practice-answers">
                            <button type="button" disabled></button>
                            <button type="button" disabled></button>
                            <button type="button" disabled></button>
                            <button type="button" disabled></button>
                        </div>
                    </article>
                </div>
            </div>

            <div class="practice-actions">
                <button type="button" data-next-problem>Nuevo grupo</button>
            </div>
        </div>

        <aside class="side-stack">
            <article class="panel hint-panel">
                <div class="panel-header">
                    <div>
                        <h2>Pista suave</h2>
                        <p>Lee despacio y busca los números importantes.</p>
                    </div>
                </div>
                <p data-problem-hint>Después, piensa si tienes que juntar, quitar, ordenar o comparar.</p>
            </article>
        </aside>
    </section>
</section>
