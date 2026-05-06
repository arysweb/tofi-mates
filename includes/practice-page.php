<?php
$practiceTopicKey = isset($_GET['topic']) && isset($topicPages[$_GET['topic']]) ? $_GET['topic'] : 'math';
$practiceTopic = $topicPages[$practiceTopicKey];
$practiceSubtopic = isset($_GET['subtopic']) ? preg_replace('/[^a-z-]/', '', $_GET['subtopic']) : $practiceTopic['subtopics'][0]['slug'];
?>
<section class="content">
    <?php require __DIR__ . '/topbar.php'; ?>

    <section class="practice-shell" data-mates-practice="<?php echo $practiceTopicKey === 'math' ? 'true' : 'false'; ?>" data-subtopic="<?php echo e($practiceSubtopic); ?>" data-difficulty="<?php echo e(isset($_GET['difficulty']) ? preg_replace('/[^a-z]/', '', $_GET['difficulty']) : 'easy'); ?>">
        <div class="practice-main panel">
            <div class="practice-topline">
                <a class="text-link focus-ring" href="index.php?page=<?php echo e($practiceTopicKey); ?>">Volver a <?php echo e($practiceTopic['title']); ?></a>
                <span data-practice-status>Reto de práctica</span>
            </div>

            <div class="practice-question-card">
                <h2 data-problem-question><?php echo e($practiceTopic['sample']['question']); ?></h2>
                <div class="practice-answers" data-problem-options>
                    <?php foreach ($practiceTopic['sample']['answers'] as $answerIndex => $answer): ?>
                        <button class="<?php echo $practiceTopic['sample']['correct'] === $answerIndex ? 'is-correct' : ''; ?>" type="button"><?php echo e($answer); ?></button>
                    <?php endforeach; ?>
                </div>
                <p class="answer-feedback" data-answer-feedback hidden></p>
            </div>

            <div class="practice-actions">
                <button type="button" data-show-hint>Necesito una pista</button>
                <button type="button" data-next-problem>Siguiente reto</button>
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
