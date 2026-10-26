<section class="lm-page-hero"><div class="lm-container">
  <a href="/courses" class="text-decoration-none fw-semibold small">← All courses</a>
  <div class="mt-4"><span class="lm-eyebrow">Self-paced course</span><h1 class="lm-page-title mt-3"><?= e($course['title']) ?></h1><p class="lm-page-subtitle">Learn at your own pace with guidance from <?= e($course['instructor']) ?>.</p></div>
</div></section>
<section class="lm-section"><div class="lm-container lm-detail-grid">
  <article><h2 class="lm-section-heading mb-3">About this course</h2><div class="lm-detail-copy"><?= e($course['description']) ?></div><div class="lm-panel mt-4"><div class="lm-panel-body"><h3 class="lm-panel-title mb-2">Meet your instructor</h3><p class="lm-muted mb-0"><?= e($course['instructor']) ?></p></div></div></article>
  <aside class="lm-detail-card" aria-label="Course enrollment">
    <div class="lm-course-art rounded-3 mb-4"><span class="lm-course-art-icon" aria-hidden="true">✦</span></div>
    <div class="lm-course-kicker">Ready to begin?</div><div class="lm-detail-price mt-2"><?= (float)$course['price'] <= 0 ? 'Free' : '$' . e(number_format((float)$course['price'], 2)) ?></div>
    <ul class="lm-detail-benefits"><li>Learn at your own pace</li><li>Track your progress</li><li>Access your learning dashboard</li></ul>
    <?php if ((float)$course['price'] > 0): ?>
      <p class="lm-muted small mb-0">Paid course enrollment will be available when secure checkout is enabled.</p>
    <?php elseif (auth_user()): ?>
      <form method="post" action="/enroll"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= e((string)$course['id']) ?>"><button type="submit" class="lm-button lm-button-primary w-100">Enroll for free →</button></form>
    <?php else: ?><a href="/login" class="lm-button lm-button-primary w-100">Sign in to enroll for free →</a><p class="lm-muted small text-center mt-3 mb-0">New here? <a href="/register">Create an account</a></p><?php endif; ?>
  </aside>
</div></section>
