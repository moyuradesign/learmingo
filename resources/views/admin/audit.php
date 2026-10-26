<section class="lm-page-hero"><div class="lm-container"><a class="small fw-semibold text-decoration-none" href="/admin">← Administrator</a><span class="lm-eyebrow d-flex mt-4">Operational audit</span><h1 class="lm-page-title">Learning activity log</h1><p class="lm-page-subtitle">Append-only changes to curricula, reviews and learning progress. Administrative access only.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container"><div class="lm-panel"><div class="table-responsive">
<table class="table lm-table align-middle"><thead><tr><th>Event</th><th>Actor ID</th><th>Subject</th><th>Recorded at</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr>
<td class="fw-semibold"><?= e((string)$event['action']) ?></td>
<td><?= (int)$event['actor_id'] ?></td>
<td><?= e((string)$event['subject_type']) ?> #<?= (int)$event['subject_id'] ?></td>
<td><?= e((string)$event['created_at']) ?></td>
</tr><?php endforeach; ?>
<?php if (!$events): ?><tr><td colspan="4" class="lm-muted text-center py-4">No audit events on this page.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="lm-panel-body d-flex gap-3 align-items-center">
<?php if ($page > 1): ?><a class="lm-button lm-button-outline" href="/admin/audit?page=<?= (int)($page-1) ?>">← Previous</a><?php endif; ?>
<span class="lm-muted small">Page <?= (int)$page ?></span>
<?php if ($hasMore): ?><a class="lm-button lm-button-outline" href="/admin/audit?page=<?= (int)($page+1) ?>">Next →</a><?php endif; ?>
</div></div></div></section>
