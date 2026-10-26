<section class="lm-page-hero"><div class="lm-container">
  <span class="lm-eyebrow">Explore Learmingo</span><h1 class="lm-page-title">Find a course worth learning.</h1>
  <p class="lm-page-subtitle">Browse practical courses from instructors and discover your next opportunity to grow.</p>
  <form class="lm-search-form" method="get" action="/courses" role="search"><label class="visually-hidden" for="course-search">Search courses</label><input id="course-search" class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by topic or skill…"><button type="submit" class="lm-button lm-button-primary">Search courses</button></form>
</div></section>
<section class="lm-section lm-section-soft"><div class="lm-container">
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-4"><div><h2 class="lm-section-heading mb-1"><?= $search !== '' ? 'Search results' : 'All available courses' ?></h2><p class="lm-muted mb-0"><?= $search !== '' ? 'Showing courses matching your search.' : 'Learn something new at your own pace.' ?></p></div><?php if ($search !== ''): ?><a class="lm-button lm-button-outline" href="/courses">Clear search</a><?php endif; ?></div>
  <?php if (!$courses): ?><div class="lm-empty"><div class="lm-empty-icon" aria-hidden="true">⌕</div><h3><?= $search !== '' ? 'No matching courses yet.' : 'Courses are coming soon.' ?></h3><p><?= $search !== '' ? 'Try another keyword or explore all available courses.' : 'Our course catalogue is being prepared. Come back soon.' ?></p><?php if ($search !== ''): ?><a href="/courses" class="lm-button lm-button-outline mt-2">Browse all courses</a><?php endif; ?></div>
  <?php else: ?><div class="lm-course-grid">
  <?php foreach ($courses as $course): ?><article class="lm-course-card">
    <div class="lm-course-art"><span class="lm-course-art-icon" aria-hidden="true">✦</span></div>
    <div class="lm-course-content"><span class="lm-course-kicker">Course</span><h3 class="lm-course-title"><a href="/course?slug=<?= urlencode($course['slug']) ?>"><?= e($course['title']) ?></a></h3><p class="lm-muted small mb-2">By <?= e($course['instructor']) ?></p><p class="lm-course-description"><?= e(substr((string)$course['description'], 0, 145)) ?></p><div class="lm-course-meta"><strong><?= (float)$course['price'] <= 0 ? 'Free' : '$' . e(number_format((float)$course['price'], 2)) ?></strong><a href="/course?slug=<?= urlencode($course['slug']) ?>">View course →</a></div></div>
  </article><?php endforeach; ?></div><?php endif; ?>
</div></section>
