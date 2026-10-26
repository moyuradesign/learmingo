<?php $editable = $course['status'] === 'draft'; ?>
<section class="lm-page-hero">
  <div class="lm-container">
    <a class="small fw-semibold text-decoration-none" href="/instructor">← Instructor dashboard</a>
    <span class="lm-eyebrow d-flex mt-4">Curriculum builder</span>
    <h1 class="lm-page-title"><?= e($course['title']) ?></h1>
    <p class="lm-page-subtitle">Course status: <strong><?= e(ucfirst((string) $course['status'])) ?></strong>. <?= $editable ? 'Organize your sections and lessons, then submit for review.' : 'The curriculum is locked while pending review or published.' ?></p>
  </div>
</section>
<section class="lm-section lm-section-soft">
  <div class="lm-container lm-form-page">
    <div class="lm-panel mb-4"><div class="lm-panel-body">
      <h2 class="lm-panel-title">Course details</h2>
      <p class="lm-muted mb-2">URL: <code><?= e($course['slug']) ?></code> · <?= (float)$course['price'] <= 0 ? 'Free' : '$'.e(number_format((float)$course['price'], 2)) ?></p>
      <p class="lm-muted small mb-2">Live lessons remain locked. Proposed updates are edited and reviewed in detached revision drafts, so enrolled learners keep their version.</p>
      <a class="lm-button lm-button-outline" href="/instructor/quizzes?course=<?= (int)$course['id'] ?>">Manage assessments</a>
      <?php if ($course['status']==='published'): ?>
        <form class="mt-3" method="post" action="/instructor/revisions/create"><?= csrf_field() ?>
        <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
        <button class="lm-button lm-button-primary" type="submit">Create detached course revision</button></form>
      <?php endif; ?>
    </div></div>
    <?php if ($editable): ?>
      <div class="lm-form-card mb-4">
        <h2 class="lm-panel-title mb-3">Add a section</h2>
        <form method="post" action="/instructor/sections/store">
          <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
          <label class="form-label" for="section-title">Section title</label>
          <input class="form-control mb-3" id="section-title" name="title" maxlength="200" required placeholder="e.g. Getting started">
          <button class="lm-button lm-button-primary" type="submit">Add section</button>
        </form>
      </div>
    <?php endif; ?>
    <h2 class="lm-section-heading mb-3">Course curriculum</h2>
    <?php if (!$sections): ?>
      <div class="lm-empty mb-4"><h3>No sections yet</h3><p>Add a section to begin structuring your lessons.</p></div>
    <?php endif; ?>
    <?php foreach ($sections as $section): ?>
      <div class="lm-panel mb-4"><div class="lm-panel-body">
        <h3 class="lm-panel-title"><?= e($section['title']) ?></h3>

        <?php if ($editable): ?>
          <details class="mb-3"><summary class="fw-semibold">Section settings, order and removal</summary>
            <form method="post" action="/instructor/curriculum/change" class="mt-3 mb-2">
              <?= csrf_field() ?>
              <input type="hidden" name="operation" value="rename_section">
              <input type="hidden" name="item_id" value="<?= (int)$section['id'] ?>">
              <label class="form-label">Section title</label>
              <div class="d-flex gap-2">
                <input class="form-control" name="title" maxlength="200" value="<?= e($section['title']) ?>" required>
                <button type="submit" class="lm-button lm-button-outline">Rename</button>
              </div>
            </form>
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach (['up' => '↑ Move up', 'down' => '↓ Move down'] as $direction => $label): ?>
                <form method="post" action="/instructor/curriculum/change">
                  <?= csrf_field() ?><input type="hidden" name="operation" value="move_section">
                  <input type="hidden" name="item_id" value="<?= (int)$section['id'] ?>">
                  <input type="hidden" name="direction" value="<?= e($direction) ?>">
                  <button type="submit" class="lm-button lm-button-outline"><?= e($label) ?></button>
                </form>
              <?php endforeach; ?>
            </div>
            <form class="mt-3" method="post" action="/instructor/curriculum/change">
              <?= csrf_field() ?>
              <input type="hidden" name="operation" value="delete_section">
              <input type="hidden" name="item_id" value="<?= (int)$section['id'] ?>">
              <label class="form-label">Delete this section and all its lessons (permanent). Type DELETE.</label>
              <input class="form-control mb-2" name="confirm" pattern="DELETE" autocomplete="off" required>
              <button class="lm-button lm-button-outline" type="submit">Delete section</button>
            </form>
          </details>
        <?php endif; ?>

        <?php if (!$section['lessons']): ?><p class="lm-muted small">This section has no lessons yet.</p><?php endif; ?>
        <?php foreach ($section['lessons'] as $lesson): ?>
          <details class="lm-lesson-card mb-2">
            <summary class="fw-semibold"><?= e($lesson['title']) ?></summary>
            <div class="p-3">
              <?php if ($editable): ?>
                <form method="post" action="/instructor/lessons/update">
                  <?= csrf_field() ?>
                  <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                  <input type="hidden" name="lesson_id" value="<?= (int)$lesson['id'] ?>">
                  <label class="form-label">Lesson title</label>
                  <input class="form-control mb-3" name="title" maxlength="200" value="<?= e($lesson['title']) ?>" required>
                  <label class="form-label">Lesson content</label>
                  <textarea class="form-control mb-3" name="content" maxlength="100000" rows="4"><?= e((string)$lesson['content']) ?></textarea>
                  <label class="form-label">Video URL (optional)</label>
                  <input class="form-control mb-3" name="video_url" maxlength="500" type="url" value="<?= e((string)$lesson['video_url']) ?>" placeholder="https://...">
                  <button class="lm-button lm-button-primary" type="submit">Save lesson</button>
                </form>

                <div class="d-flex gap-2 flex-wrap mt-3">
                  <?php foreach (['up'=>'↑ Lesson up','down'=>'↓ Lesson down'] as $direction=>$label): ?>
                    <form method="post" action="/instructor/curriculum/change">
                      <?= csrf_field() ?><input type="hidden" name="operation" value="move_lesson">
                      <input type="hidden" name="item_id" value="<?= (int)$lesson['id'] ?>">
                      <input type="hidden" name="direction" value="<?= e($direction) ?>">
                      <button class="lm-button lm-button-outline" type="submit"><?= e($label) ?></button>
                    </form>
                  <?php endforeach; ?>
                </div>
                <form class="mt-3" method="post" action="/instructor/curriculum/change">
                  <?= csrf_field() ?>
                  <input type="hidden" name="operation" value="delete_lesson">
                  <input type="hidden" name="item_id" value="<?= (int)$lesson['id'] ?>">
                  <label class="form-label">Delete lesson permanently (only before enrollment). Type DELETE.</label>
                  <input class="form-control mb-2" name="confirm" pattern="DELETE" required autocomplete="off">
                  <button class="lm-button lm-button-outline" type="submit">Delete lesson</button>
                </form>

              <?php else: ?>
                <p class="lm-muted"><?= e(mb_strimwidth((string)$lesson['content'], 0, 240, '…')) ?></p>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
        <?php if ($editable): ?>
          <details class="mt-3"><summary class="fw-semibold">+ Add lesson to this section</summary>
            <form class="pt-3" method="post" action="/instructor/lessons/store">
              <?= csrf_field() ?>
              <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
              <input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>">
              <label class="form-label">Lesson title</label>
              <input class="form-control mb-3" name="title" maxlength="200" required>
              <label class="form-label">Lesson content</label>
              <textarea class="form-control mb-3" name="content" maxlength="100000" rows="5"></textarea>
              <label class="form-label">Video URL (optional)</label>
              <input class="form-control mb-3" name="video_url" maxlength="500" type="url" placeholder="https://...">
              <button class="lm-button lm-button-primary" type="submit">Add lesson</button>
            </form>
          </details>
        <?php endif; ?>
      </div></div>
    <?php endforeach; ?>
    <?php if ($editable): ?>
      <div class="lm-panel"><div class="lm-panel-body">
        <h2 class="lm-panel-title">Submit for publication</h2>
        <p class="lm-muted small">The course must contain at least one lesson. After submission the curriculum is frozen until an administrator reviews it.</p>
        <form method="post" action="/instructor/courses/submit">
          <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
          <button class="lm-button lm-button-primary" type="submit">Submit for review</button>
        </form>
      </div></div>
    <?php endif; ?>
  </div>
</section>
