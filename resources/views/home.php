<section class="lm-hero">
  <div class="lm-container lm-hero-grid">
    <div>
      <span class="lm-eyebrow">Learning that fits your life</span>
      <h1>Grow your skills.<br><span>Unlock what’s next.</span></h1>
      <p class="lm-hero-description">Discover practical courses built for real progress. Learn from instructors, move at your own pace, and turn curiosity into confidence.</p>
      <div class="lm-hero-actions"><a class="lm-button lm-button-primary" href="/courses">Explore courses <span aria-hidden="true">↗</span></a><a class="lm-button lm-button-outline" href="/#how-it-works">See how it works <span aria-hidden="true">→</span></a></div>
      <div class="lm-hero-caption"><span>Learn at your own pace</span><span>Practical, focused lessons</span></div>
    </div>
    <div class="lm-showcase" aria-label="Illustration of the Learmingo learning experience">
      <div class="lm-preview">
        <div class="lm-preview-top"><div class="d-flex gap-3 align-items-center"><span class="lm-preview-icon" aria-hidden="true">✦</span><div><span class="lm-preview-kicker">Your learning space</span><h3>Make progress every day</h3></div></div><span class="lm-preview-tag">Self-paced</span></div>
        <div class="lm-preview-panel">
          <h4>A learning journey, made simple.</h4>
          <div class="lm-preview-lesson"><span class="lm-preview-play" aria-hidden="true">▶</span><div><strong>Discover a skill you love</strong><small>Find your next course</small></div></div>
          <div class="lm-preview-lesson"><span class="lm-preview-play" aria-hidden="true">✎</span><div><strong>Learn one step at a time</strong><small>Move at a pace that works for you</small></div></div>
          <div class="lm-preview-lesson"><span class="lm-preview-play" aria-hidden="true">★</span><div><strong>Build practical confidence</strong><small>Put new knowledge to work</small></div></div>
        </div>
        <div class="lm-preview-progress"><div class="d-flex justify-content-between"><strong class="small">Your next chapter starts here</strong><span class="small lm-muted">Keep going</span></div><div class="lm-preview-progress-track"><span></span></div></div>
      </div>
      <div class="lm-float-chip"><span aria-hidden="true">✦</span> Small steps. Real growth.</div>
    </div>
  </div>
</section>
<section class="lm-section" id="how-it-works">
  <div class="lm-container">
    <div class="text-center mb-5"><span class="lm-eyebrow">Why Learmingo</span><h2 class="lm-section-heading mt-3">Everything you need to keep learning.</h2><p class="lm-section-description mx-auto mt-3">A thoughtfully simple experience that helps you focus on the skills that matter.</p></div>
    <div class="lm-feature-grid">
      <article class="lm-feature-card"><span class="lm-feature-icon" aria-hidden="true">⌕</span><h3>Find your next skill</h3><p>Explore courses from instructors and discover subjects aligned with your goals.</p></article>
      <article class="lm-feature-card"><span class="lm-feature-icon" aria-hidden="true">▷</span><h3>Learn on your schedule</h3><p>Learn at your own pace and return to your courses whenever you’re ready.</p></article>
      <article class="lm-feature-card"><span class="lm-feature-icon" aria-hidden="true">↗</span><h3>Keep moving forward</h3><p>Follow your enrolled courses and see how far your learning journey has come.</p></article>
    </div>
  </div>
</section>
<section class="lm-section lm-section-soft">
  <div class="lm-container">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-4"><div><span class="lm-eyebrow">Explore learning</span><h2 class="lm-section-heading mt-3 mb-2">Featured courses</h2><p class="lm-section-description mb-0">Discover what you can learn next.</p></div><a class="lm-button lm-button-outline" href="/courses">View all courses <span aria-hidden="true">→</span></a></div>
    <?php if (!$courses): ?>
      <div class="lm-empty"><div class="lm-empty-icon" aria-hidden="true">▤</div><h3>Exciting courses are on their way.</h3><p>Our course collection is being prepared. Explore the catalogue again soon.</p><a class="lm-button lm-button-outline mt-2" href="/courses">Browse the catalogue</a></div>
    <?php else: ?><div class="lm-course-grid">
      <?php foreach ($courses as $course): ?><article class="lm-course-card">
        <div class="lm-course-art"><span class="lm-course-art-icon" aria-hidden="true">✦</span></div><div class="lm-course-content"><span class="lm-course-kicker">Self-paced course</span><h3 class="lm-course-title"><a href="/course?slug=<?= urlencode($course['slug']) ?>"><?= e($course['title']) ?></a></h3><p class="small lm-muted mb-2">By <?= e($course['instructor']) ?></p><p class="lm-course-description"><?= e(substr((string)$course['description'], 0, 120)) ?></p><div class="lm-course-meta"><strong><?= (float)$course['price'] <= 0 ? 'Free' : '$' . e(number_format((float)$course['price'], 2)) ?></strong><a href="/course?slug=<?= urlencode($course['slug']) ?>">View course →</a></div></div>
      </article><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</section>
<section class="lm-section"><div class="lm-container"><div class="lm-cta"><div><h2>Your next skill is waiting.</h2><p>Start exploring today and make room for your next achievement.</p></div><a href="/register" class="lm-button lm-button-light">Create a free account <span aria-hidden="true">→</span></a></div></div></section>
