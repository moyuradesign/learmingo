<section class="lm-page-hero"><div class="lm-container"><a class="small fw-semibold text-decoration-none" href="/admin">← Admin dashboard</a><span class="lm-eyebrow d-flex mt-4">Content moderation</span><h1 class="lm-page-title">Course review</h1><p class="lm-page-subtitle">Approve complete curricula or return submissions to draft for further editing.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container"><div class="lm-panel"><div class="table-responsive"><table class="table lm-table align-middle"><thead><tr><th>Course</th><th>Instructor</th><th>Lessons</th><th>Status</th><th>Review</th></tr></thead><tbody>
<?php foreach ($courses as $course): ?><tr>
<td class="fw-semibold"><?= e($course['title']) ?></td><td><?= e($course['instructor']) ?></td><td><?= (int)$course['lessons'] ?></td>
<td><span class="lm-status <?= $course['status']==='published' ? 'lm-status-active' : '' ?>"><?= e(ucfirst((string)$course['status'])) ?></span></td>
<td><?php if ($course['status']==='pending'): ?>
<div class="d-flex flex-wrap gap-2">
  <form method="post" action="/admin/courses/review"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>"><input type="hidden" name="decision" value="approve"><button type="submit" class="lm-button lm-button-primary">Approve</button></form>
  <form method="post" action="/admin/courses/review"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>"><input type="hidden" name="decision" value="reject"><button type="submit" class="lm-button lm-button-outline">Return to draft</button></form>
</div>
<?php elseif ($course['status']==='published'): ?><a href="/course?slug=<?= urlencode($course['slug']) ?>">View published</a>
<?php else: ?><span class="lm-muted small">No review action</span><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$courses): ?><tr><td colspan="5" class="text-center lm-muted py-4">No courses to review yet.</td></tr><?php endif; ?>
</tbody></table></div></div></div></section>
