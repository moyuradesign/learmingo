<section class="lm-page-hero"><div class="lm-container"><h1 class="lm-page-title">Certificate Registry</h1><p class="lm-page-subtitle">Review credentials and revoke invalid awards with a recorded reason. Revocation is permanent.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container"><div class="lm-panel"><div class="table-responsive"><table class="table lm-table"><thead><tr><th>Learner</th><th>Course</th><th>Issued</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($certificates as $cert): ?><tr><td><?= e($cert['learner_name']) ?></td><td><?= e($cert['course_title']) ?></td><td><?= e($cert['issued_at']) ?></td><td><?= $cert['revoked_at']===null?'Valid':'Revoked' ?></td><td>
<?php if ($cert['revoked_at']===null): ?><form method="post" action="/admin/certificates/revoke">
<?= csrf_field() ?><input type="hidden" name="certificate_id" value="<?= (int)$cert['id'] ?>">
<label class="form-label">Reason for revocation</label><input class="form-control mb-2" name="reason" required minlength="10" maxlength="500">
<button type="submit" class="lm-button lm-button-outline">Revoke certificate</button></form><?php else: ?><span class="lm-muted small">No further action</span><?php endif; ?>
</td></tr><?php endforeach; ?>
<?php if (!$certificates): ?><tr><td colspan="5">No certificates issued.</td></tr><?php endif; ?>
</tbody></table></div></div></div></section>