<section class="lm-page-hero"><div class="lm-container"><h1 class="lm-page-title">Instructor applications</h1><p class="lm-page-subtitle">Review the teaching statements before granting instructor access.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container"><?php if (!$applications): ?><div class="lm-empty"><h3>No pending applications.</h3></div><?php endif; ?>
<?php foreach ($applications as $item): ?><div class="lm-panel mb-3"><div class="lm-panel-body"><h2 class="lm-panel-title"><?= e($item['name']) ?></h2><p class="lm-muted small"><?= e($item['email']) ?> · <?= e($item['submitted_at']) ?></p>
<p style="white-space:pre-wrap"><?= e($item['statement']) ?></p>
<?php foreach (['approve'=>'Approve','reject'=>'Reject'] as $decision=>$label): ?>
<form method="post" action="/admin/instructor-applications/review" class="mb-2"><?= csrf_field() ?>
<input type="hidden" name="application_id" value="<?= (int)$item['id'] ?>">
<input type="hidden" name="decision" value="<?= e($decision) ?>">
<label class="form-label">Review note (optional)</label><input class="form-control mb-2" name="note" maxlength="500">
<button class="lm-button <?= $decision==='approve'?'lm-button-primary':'lm-button-outline' ?>" type="submit"><?= e($label) ?></button>
</form><?php endforeach; ?></div></div><?php endforeach; ?></div></section>