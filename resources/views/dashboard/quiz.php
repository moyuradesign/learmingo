<section class="lm-page-hero"><div class="lm-container"><h1 class="lm-page-title"><?= e($quiz['title']) ?></h1><p class="lm-page-subtitle">Pass mark: <?= (int)$quiz['passing_percent'] ?>% · Maximum <?= (int)$quiz['max_attempts'] ?> attempts. All answers are graded on the server.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page"><div class="lm-form-card">
<form method="post" action="/quiz/submit"><?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
<?php foreach ($questions as $i=>$question): ?><fieldset class="mb-4"><legend class="lm-panel-title"><?= (int)($i+1) ?>. <?= e($question['prompt']) ?></legend>
<p class="lm-muted small"><?= (int)$question['points'] ?> points</p>

<?php $kind=$question['format']??'single'; ?>
<?php if ($kind==='multi_select'): ?>
 <p class="lm-muted small">Select all correct answers. You must choose at least two options.</p>
 <?php foreach (['a','b','c','d'] as $option): ?>
 <label class="d-block mb-2"><input type="checkbox" name="answers[<?= (int)$question['id'] ?>][]" value="<?= $option ?>"> <?= e($question['option_'.$option]) ?></label>
 <?php endforeach; ?>
<?php else: ?>
 <?php foreach ($kind==='true_false'?['a','b']:['a','b','c','d'] as $option): ?>
 <label class="d-block mb-2"><input type="radio" required name="answers[<?= (int)$question['id'] ?>]" value="<?= $option ?>"> <?= e($question['option_'.$option]) ?></label>
 <?php endforeach; ?>
<?php endif; ?>
</fieldset><?php endforeach; ?><button class="lm-button lm-button-primary" type="submit">Submit all answers</button></form>
</div></div></section>