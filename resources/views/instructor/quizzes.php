<section class="lm-page-hero"><div class="lm-container"><a class="small fw-semibold text-decoration-none" href="/instructor/course?course=<?= (int)$course['id'] ?>">← Course builder</a><span class="lm-eyebrow d-flex mt-4">Assessments</span><h1 class="lm-page-title"><?= e($course['title']) ?> — Quizzes</h1><p class="lm-page-subtitle">Write multiple-choice questions and publish immutable assessments when the course is published.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page"><div class="lm-form-card mb-4"><h2 class="lm-panel-title">Create quiz</h2>
<form method="post" action="/instructor/quizzes/create"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
<label class="form-label">Title</label><input class="form-control mb-2" name="title" maxlength="200" minlength="3" required>
<div class="d-flex gap-3"><div><label class="form-label">Passing score (%)</label><input type="number" class="form-control mb-2" name="passing_percent" min="1" max="100" value="70" required></div>
<div><label class="form-label">Max attempts</label><input type="number" class="form-control mb-2" name="max_attempts" min="1" max="10" value="3" required></div></div>
<button class="lm-button lm-button-primary" type="submit">Create draft quiz</button></form></div>
<a class="lm-button lm-button-outline mb-4" href="/instructor/gradebook?course=<?= (int)$course['id'] ?>">Open gradebook</a>
<?php foreach ($quizzes as $quiz): ?><div class="lm-panel mb-4"><div class="lm-panel-body">
<h2 class="lm-panel-title"><?= e($quiz['title']) ?> <span class="lm-status"><?= e($quiz['status']) ?></span></h2>
<p class="lm-muted small">Pass <?= (int)$quiz['passing_percent'] ?>% · <?= (int)$quiz['max_attempts'] ?> attempts · <?= count($quiz['questions']) ?> questions</p>
<?php foreach ($quiz['questions'] as $question): ?><div class="mb-2"><strong><?= e($question['prompt']) ?></strong><div class="lm-muted small">Answer options: <?= e($question['option_a']) ?> / <?= e($question['option_b']) ?> / <?= e($question['option_c']) ?> / <?= e($question['option_d']) ?></div></div><?php endforeach; ?>
<?php if ($quiz['status']==='draft'): ?>
<details class="mt-3"><summary class="fw-semibold">Add question</summary><form method="post" action="/instructor/quizzes/question" class="pt-3">
<?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>"><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
<label class="form-label">Question</label><input class="form-control mb-2" name="prompt" maxlength="1000" minlength="3" required>
<?php foreach (['a','b','c','d'] as $key): ?><label class="form-label">Choice <?= strtoupper($key) ?></label><input class="form-control mb-2" name="option_<?= e($key) ?>" maxlength="500" required><?php endforeach; ?>
<label class="form-label">Correct choice</label><select class="form-select mb-2" name="correct_option"><?php foreach (['a','b','c','d'] as $key): ?><option value="<?= $key ?>"><?= strtoupper($key) ?></option><?php endforeach; ?></select>
<label class="form-label">Points</label><input class="form-control mb-2" name="points" type="number" min="1" max="100" value="1" required>
<button type="submit" class="lm-button lm-button-primary">Save question</button></form></details>

<details class="mt-3"><summary class="fw-semibold">Add advanced question: true/false or select all</summary>
 <form method="post" action="/instructor/quizzes/advanced-question" class="pt-3">
 <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>"><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
 <label class="form-label">Question type</label>
 <select class="form-select mb-2" name="format" required>
 <option value="multi_select">Multiple correct answers (select all)</option>
 <option value="true_false">True / False</option></select>
 <label class="form-label">Question</label><input class="form-control mb-2" name="prompt" minlength="3" maxlength="1000" required>
 <p class="lm-muted small">For true/false, option text is ignored and A = True, B = False. For multiple selection, fill in all four choices.</p>
 <?php foreach (['a','b','c','d'] as $k): ?>
 <label class="form-label">Option <?= strtoupper($k) ?></label>
 <input class="form-control mb-2" name="option_<?= e($k) ?>" maxlength="500" placeholder="Choice <?= strtoupper($k) ?>">
 <?php endforeach; ?>
 <p class="lm-muted small">For multiple selection tick every correct option (at least two).</p>
 <?php foreach (['a','b','c','d'] as $k): ?>
 <label class="me-3"><input type="checkbox" name="correct_choices[]" value="<?= $k ?>"> <?= strtoupper($k) ?></label>
 <?php endforeach; ?>
 <label class="form-label d-block mt-3">For true/false, correct answer</label>
 <select name="correct_single" class="form-select mb-2"><option value="a">True</option><option value="b">False</option></select>
 <label class="form-label">Instructor explanation (optional)</label>
 <textarea class="form-control mb-2" name="explanation" maxlength="1000" rows="2"></textarea>
 <label class="form-label">Points</label><input class="form-control mb-3" name="points" type="number" min="1" max="100" value="1" required>
 <button type="submit" class="lm-button lm-button-primary">Save advanced question</button>
 </form></details>
<form class="mt-3" method="post" action="/instructor/quizzes/publish"><?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>"><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
<button class="lm-button lm-button-outline" type="submit">Publish quiz (locks questions)</button></form>
<?php endif; ?></div></div><?php endforeach; ?>
</div></section>