<section class="lm-page-hero"><div class="lm-container"><a href="/dashboard" class="fw-semibold small text-decoration-none">← My learning</a><span class="lm-eyebrow d-flex mt-4">Course classroom</span><h1 class="lm-page-title"><?= e($course['title']) ?></h1><p class="lm-page-subtitle">Work through the lessons at your own pace.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page"><div class="lm-panel mb-4"><div class="lm-panel-body">
  <?php $progress = max(0, min(100, (float)$course['progress'])); ?>
  <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="lm-panel-title mb-0">Course progress</h2><span class="fw-semibold small"><?= e((string)$progress) ?>%</span></div>
  <div class="lm-progress" role="progressbar" aria-label="Course progress" aria-valuenow="<?= e((string)$progress) ?>" aria-valuemin="0" aria-valuemax="100"><div style="width:<?= e((string)$progress) ?>%"></div></div>
</div></div>
<h2 class="lm-section-heading mb-4">Course lessons</h2>
<?php if (!$lessons): ?><div class="lm-empty"><div class="lm-empty-icon" aria-hidden="true">▤</div><h3>Lessons are being prepared.</h3><p>Your instructor hasn't published lesson content yet. Check back soon.</p><a href="/dashboard" class="lm-button lm-button-outline">Back to my learning</a></div>
<?php else: ?><div class="lm-lesson-list"><?php foreach ($lessons as $i => $lesson): ?>
  <details class="lm-lesson-card"><summary><span class="lm-lesson-number"><?= e((string)($i + 1)) ?></span><span><span class="lm-lesson-section"><?= e($lesson['section_title']) ?></span><?= e($lesson['title']) ?></span></summary>
    <div class="lm-lesson-body"><?php if (trim((string)($lesson['content'] ?? '')) !== ''): ?><p><?= nl2br(e((string)$lesson['content'])) ?></p><?php else: ?><p class="lm-muted">Lesson material will be added soon.</p><?php endif; ?>
    <?php $video = (string)($lesson['video_url'] ?? ''); $scheme = strtolower((string)parse_url($video, PHP_URL_SCHEME)); ?>
    <?php if (filter_var($video, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true)): ?><a class="lm-button lm-button-outline" href="<?= e($video) ?>" target="_blank" rel="noopener noreferrer">Open lesson video ↗</a><?php endif; ?>
    </div>
  </details>
<?php endforeach; ?></div><?php endif; ?></div></section>
