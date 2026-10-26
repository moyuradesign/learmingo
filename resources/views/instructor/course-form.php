<section class="lm-page-hero"><div class="lm-container"><a href="/instructor" class="fw-semibold small text-decoration-none">← Instructor dashboard</a><span class="lm-eyebrow d-flex mt-4">Course builder</span><h1 class="lm-page-title">Create a new course.</h1><p class="lm-page-subtitle">Add the essential details. You can continue building your learning experience as more tools become available.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page"><div class="lm-form-card">
  <h2 class="lm-panel-title mb-2">Course information</h2><p class="lm-muted small mb-4">Fields marked * are required.</p>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/instructor/courses/store"><?= csrf_field() ?>
    <div class="mb-4"><label class="form-label" for="course-title">Course title *</label><input id="course-title" class="form-control" name="title" required maxlength="255" placeholder="e.g. Introduction to web design"></div>
    <div class="mb-4"><label class="form-label" for="course-slug">Course URL slug</label><input id="course-slug" class="form-control" name="slug" placeholder="introduction-to-web-design" pattern="[a-z0-9-]+"><p class="lm-muted small mt-2 mb-0">Optional — we will generate one from the title.</p></div>
    <div class="mb-4"><label class="form-label" for="course-description">Course description</label><textarea id="course-description" class="form-control" name="description" rows="6" placeholder="What will learners discover in this course?"></textarea></div>
    <div class="mb-4"><label class="form-label" for="course-price">Price (USD)</label><input id="course-price" class="form-control" type="number" name="price" min="0" step="0.01" value="0"><p class="lm-muted small mt-2 mb-0">Use 0 to offer this course for free.</p></div>
    <div class="d-flex flex-wrap align-items-center gap-3"><button type="submit" class="lm-button lm-button-primary">Save course →</button><a href="/instructor" class="lm-button lm-button-outline">Cancel</a></div>
  </form>
</div></div></section>
