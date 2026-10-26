<section class="lm-page-hero"><div class="lm-container"><a href="/instructor/course?course=<?= (int)$draft['course_id'] ?>" class="fw-semibold small text-decoration-none">← Course builder</a><span class="lm-eyebrow d-flex mt-4">Independent revision draft</span><h1 class="lm-page-title"><?= e($draft['title']) ?></h1><p class="lm-page-subtitle">Revision #<?= (int)$draft['id'] ?> · Status <?= e($draft['state']) ?>. Changes here do not alter published lessons.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page">
<?php foreach ($draft['lessons'] as $index=>$lesson): ?><div class="lm-form-card mb-3"><p class="lm-muted small"><?= e($lesson['section_title']) ?> · Lesson <?= (int)($index+1) ?></p>
<?php if ($draft['state']==='draft'): ?><form method="post" action="/instructor/revision/edit"><?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= (int)$draft['id'] ?>"><input type="hidden" name="index" value="<?= (int)$index ?>">
<label class="form-label">Lesson title</label><input class="form-control mb-2" name="title" maxlength="200" value="<?= e($lesson['title']) ?>" required>
<label class="form-label">Content</label><textarea class="form-control mb-2" name="content" maxlength="100000" rows="5"><?= e($lesson['content']) ?></textarea>
<label class="form-label">Video URL</label><input class="form-control mb-2" name="video_url" type="url" maxlength="500" value="<?= e($lesson['video_url']) ?>">
<button type="submit" class="lm-button lm-button-outline">Save draft lesson</button></form>
<?php else: ?><h3 class="lm-panel-title"><?= e($lesson['title']) ?></h3><?php endif; ?></div><?php endforeach; ?>
<?php if ($draft['state']==='draft'): ?><div class="lm-panel"><div class="lm-panel-body"><p class="lm-muted small">Submitting freezes this revision until the administrator approves or rejects it.</p>
<form method="post" action="/instructor/revision/submit"><?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= (int)$draft['id'] ?>"><button class="lm-button lm-button-primary" type="submit">Submit revision for approval</button></form></div></div><?php endif; ?>
</div></section>