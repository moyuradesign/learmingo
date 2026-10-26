<section class="lm-page-hero"><div class="lm-container"><h1 class="lm-page-title">Curriculum version control</h1><p class="lm-page-subtitle">Create a baseline snapshot before allowing instructor revisions. Existing enrollments stay pinned to their original version.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container"><div class="lm-panel mb-4"><div class="table-responsive"><table class="table lm-table"><thead><tr><th>Published course</th><th>Latest version</th><th>Action</th></tr></thead><tbody>
<?php foreach ($courses as $course): ?><tr><td><?= e($course['title']) ?></td><td><?= $course['version']?(int)$course['version']:'Not versioned' ?></td>
<td><?php if (!$course['version']): ?><form method="post" action="/admin/course-versions/baseline"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>"><button type="submit" class="lm-button lm-button-primary">Establish baseline</button></form><?php else: ?><span class="lm-status lm-status-active">Versioned</span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div><h2 class="lm-section-heading">Pending published-course revisions</h2>
<?php if (!$revisions): ?><p class="lm-muted">No revisions awaiting review.</p><?php endif; ?>
<?php foreach ($revisions as $revision): ?><div class="lm-panel mb-3"><div class="lm-panel-body">
<h3 class="lm-panel-title"><?= e($revision['title']) ?> · <?= e($revision['instructor']) ?></h3><p class="lm-muted small">Revision #<?= (int)$revision['id'] ?> · <?= e($revision['created_at']) ?></p>
<?php foreach (['approve'=>'Approve and publish new version','reject'=>'Reject revision'] as $decision=>$label): ?><form class="d-inline-block me-2" method="post" action="/admin/revisions/review">
<?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= (int)$revision['id'] ?>"><input type="hidden" name="decision" value="<?= e($decision) ?>">
<button class="lm-button <?= $decision==='approve'?'lm-button-primary':'lm-button-outline' ?>" type="submit"><?= e($label) ?></button></form><?php endforeach; ?>
</div></div><?php endforeach; ?></div></section>