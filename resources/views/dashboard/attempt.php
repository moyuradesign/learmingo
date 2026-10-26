<section class="lm-page-hero"><div class="lm-container"><a href="/student/records" class="small fw-semibold text-decoration-none">← My records</a><h1 class="lm-page-title">Assessment attempt #<?= (int)$record['attempt_no'] ?></h1>
<p class="lm-page-subtitle"><?= e($record['quiz_title']) ?> · <?= e($record['score_percent']) ?>% · <?= $record['passed']?'Passed':'Not passed' ?></p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page"><div class="lm-panel"><div class="lm-panel-body">
<?php foreach ($record['answers'] as $answer): ?><div class="mb-3"><h2 class="lm-panel-title"><?= e($answer['prompt']) ?></h2><p>Your answer: <code><?= e((string)($answer['selection_key']??$answer['selected_option'])) ?></code></p>
<p class="lm-muted small">Points earned: <?= (int)$answer['points_awarded'] ?>/<?= (int)$answer['points'] ?></p></div><?php endforeach; ?>
<p class="lm-muted small">Answer keys are not displayed during the assessment lifecycle.</p>
</div></div></div></section>