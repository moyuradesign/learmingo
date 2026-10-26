<?php
/** LEARMINGO_COURSE_FIRST_HOME_V104 — catalog-first homepage. */
$front = \Learmingo\Services\FrontendSettings::all();
$categories = $categories ?? [];
$isDemo = !empty($previewMode);
$heroTitle = trim((string)($front['hero_title'] ?? 'Learn skills that move you forward.'));
if ($heroTitle === 'Learn something remarkable.') { $heroTitle = 'Learn skills that move you forward.'; }
$highlight = $heroTitle === 'Learn skills that move you forward.';
?>
<div class="lm4-home" data-release="LEARMINGO_COURSE_FIRST_HOME_V104">
  <section class="lm4-hero" aria-labelledby="lm4-hero-heading">
    <div class="lm-container lm4-hero-grid">
      <div class="lm4-hero-content">
        <span class="lm4-hero-eyebrow"><span aria-hidden="true">●</span><?= e($front['hero_eyebrow']) ?></span>
        <h1 id="lm4-hero-heading"><?php if ($highlight): ?>Learn skills that<br>move you <span>forward.</span><?php else: ?><?= e($heroTitle) ?><?php endif; ?></h1>
        <p><?= e($front['hero_subtitle']) ?></p>
        <div class="lm4-hero-actions">
          <a class="lm4-button lm4-button-primary" href="<?= e($front['hero_button_url']) ?>"><?= e($front['hero_button']) ?> <span aria-hidden="true">→</span></a>
          <a class="lm4-button lm4-button-secondary" href="/categories">Browse categories <span aria-hidden="true">→</span></a>
        </div>
        <ul class="lm4-hero-highlights" aria-label="Explore Learmingo">
          <li><span class="lm4-hi-icon" aria-hidden="true">✧</span><span><b>Learn practical skills</b><small>Find your next course</small></span></li>
          <li><span class="lm4-hi-icon" aria-hidden="true">◷</span><span><b>Flexible learning</b><small>Progress at your pace</small></span></li>
          <li><span class="lm4-hi-icon" aria-hidden="true">▤</span><span><b>Build your future</b><small>Explore by subject</small></span></li>
        </ul>
      </div>
      <div class="lm4-hero-media">
        <img src="/learmingo-hero-art?v=1.0.4" width="900" height="486" alt="Learner holding a laptop, with illustrations representing new skills and progress" decoding="async" fetchpriority="high">
      </div>
    </div>
  </section>
  <section class="lm4-featured" id="featured-courses" aria-labelledby="lm4-featured-heading">
    <div class="lm-container">
      <div class="lm4-section-header">
        <div><span class="lm4-section-eyebrow">Explore learning</span><h2 id="lm4-featured-heading"><?= e($front['featured_title'] === 'Find your next opportunity' ? 'Featured Courses' : $front['featured_title']) ?></h2><p><?= e($front['featured_subtitle'] === 'A thoughtful collection of courses to help you move forward.' ? 'Discover courses to build practical skills.' : $front['featured_subtitle']) ?></p></div>
        <a class="lm4-text-button" href="/courses">View all courses <span aria-hidden="true">→</span></a>
      </div>
      <?php if ($isDemo): ?><p class="lm4-demo-disclaimer"><span aria-hidden="true">ⓘ</span> Example courses for layout preview only. Enrollment is not available.</p><?php endif; ?>
      <?php if (!$courses): ?>
        <div class="lm4-empty"><h3>Our course library is growing.</h3><p>Explore the categories or return when new courses are published.</p><a class="lm4-button lm4-button-primary" href="/courses">Browse courses</a></div>
      <?php else: ?>
        <div class="lm4-course-grid">
          <?php foreach ($courses as $course): ?>
          <article class="lm4-course-card">
            <a class="lm4-course-image" href="/course?slug=<?= rawurlencode((string)$course['slug']) ?>" aria-label="View <?= e((string)$course['title']) ?>">
              <img src="/learmingo-course-cover?slug=<?= rawurlencode((string)$course['slug']) ?>" alt="<?= e((string)($course['category_name'] ?? 'Course')) ?> course artwork" width="640" height="360" loading="lazy" decoding="async">
              <span class="lm4-course-category"><?= e((string)($course['category_name'] ?? 'Course')) ?></span>
            </a>
            <div class="lm4-course-body">
              <h3><a href="/course?slug=<?= rawurlencode((string)$course['slug']) ?>"><?= e((string)$course['title']) ?></a></h3>
              <p class="lm4-course-desc"><?= e((string)($course['description'] ?? '')) ?></p>
              <div class="lm4-course-instructor"><span class="lm4-avatar" aria-hidden="true"><?= e(strtoupper(substr((string)($course['instructor'] ?? 'L'),0,1))) ?></span><span><?= e((string)($course['instructor'] ?? 'Learmingo')) ?></span></div>
              <div class="lm4-course-bottom"><span class="lm4-course-meta"><?php if (!empty($course['lessons'])): ?><?= e((string)$course['lessons']) ?> · <?php endif; ?><?= e((string)($course['level'] ?? 'Self-paced')) ?></span><strong><?= (float)($course['price'] ?? 0) <= 0 ? 'Free' : '$'.e(number_format((float)$course['price'],2)) ?></strong></div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <section class="lm4-categories" id="browse-categories" aria-labelledby="lm4-categories-heading">
    <div class="lm-container">
      <div class="lm4-section-header"><div><span class="lm4-section-eyebrow">Find your subject</span><h2 id="lm4-categories-heading">Browse by Category</h2><p>Explore courses by topic and find what fits your goals.</p></div><a class="lm4-text-button" href="/categories">View all categories <span aria-hidden="true">→</span></a></div>
      <?php if ($categories): ?>
        <div class="lm4-category-grid">
          <?php $icons = ['web-development'=>'⌘','design'=>'✿','business'=>'▥','data-science'=>'▤','mobile-development'=>'▦','technology'=>'☁','personal-development'=>'♡','languages'=>'◎','creative-arts'=>'◈','photography'=>'▧']; ?>
          <?php foreach ($categories as $category): ?>
            <a class="lm4-category-card" href="/courses?category=<?= rawurlencode((string)$category['slug']) ?>">
              <span class="lm4-cat-icon" aria-hidden="true"><?= e($icons[$category['slug']] ?? '✧') ?></span>
              <span class="lm4-cat-copy"><strong><?= e((string)$category['name']) ?></strong><small><?= (int)($category['course_count'] ?? $category['total'] ?? 0) ?> <?= (int)($category['course_count'] ?? $category['total'] ?? 0) === 1 ? 'course' : 'courses' ?></small></span>
              <span class="lm4-cat-arrow" aria-hidden="true">↗</span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?><p class="lm4-category-empty">Categories will appear here as courses are added.</p><?php endif; ?>
    </div>
  </section>
</div>
