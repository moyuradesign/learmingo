<section class="lm-page-hero"><div class="lm-container"><h1 class="lm-page-title">My learning records</h1><p class="lm-page-subtitle">Course progress, assessment results and verifiable certificates.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container"><h2 class="lm-section-heading mb-3">Enrollments</h2>
<div class="lm-panel mb-4"><div class="table-responsive"><table class="table lm-table"><thead><tr><th>Course</th><th>Progress</th><th>Status</th><th>Certificate</th></tr></thead><tbody>
<?php foreach ($courses as $row): ?><tr><td><a href="/learn?course=<?= urlencode($row['slug']) ?>"><?= e($row['title']) ?></a></td><td><?= e($row['progress']) ?>%</td><td><?= e($row['status']) ?></td>
<td><?php if ($row['status']==='completed'): ?><form method="post" action="/certificate/issue"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$row['course_id'] ?>"><button type="submit" class="lm-button lm-button-outline">Request certificate</button></form><?php else: ?><span class="lm-muted">Complete lessons first</span><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$courses): ?><tr><td colspan="4">No enrollments yet.</td></tr><?php endif; ?></tbody></table></div></div>
<h2 class="lm-section-heading mb-3">Assessment grades</h2><div class="lm-panel mb-4"><div class="table-responsive"><table class="table lm-table"><thead><tr><th>Course</th><th>Assessment</th><th>Score</th><th>Result</th></tr></thead><tbody>
<?php foreach ($attempts as $item): ?><tr><td><?= e($item['course_title']) ?></td><td><?= e($item['quiz_title']) ?></td><td><?= e($item['score_percent']) ?>%</td><td><?= $item['passed']?'Passed':'Not passed' ?></td></tr><?php endforeach; ?>
<?php if (!$attempts): ?><tr><td colspan="4">No grades recorded.</td></tr><?php endif; ?></tbody></table></div></div>
<h2 class="lm-section-heading mb-3">Certificates</h2><div class="lm-panel"><div class="lm-panel-body">
<?php foreach ($certificates as $cert): ?><p><?= e($cert['course_title']) ?> · <a href="/certificate/verify?code=<?= e($cert['credential']) ?>">Verify certificate</a> · <?= e($cert['issued_at']) ?></p><?php endforeach; ?>
<?php if (!$certificates): ?><p class="lm-muted">No certificates issued yet. Complete the course and pass a published assessment.</p><?php endif; ?>
</div></div></div></section>