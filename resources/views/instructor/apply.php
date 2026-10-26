<section class="lm-page-hero"><div class="lm-container"><span class="lm-eyebrow">Instructor development</span><h1 class="lm-page-title">Become an instructor</h1><p class="lm-page-subtitle">Tell us about your teaching experience and the courses you plan to offer.</p></div></section>
<section class="lm-section lm-section-soft"><div class="lm-container lm-form-page"><div class="lm-form-card"><h2 class="lm-panel-title">Application</h2>
<?php if ($application): ?><p class="lm-muted">Status: <strong><?= e(ucfirst((string)$application['status'])) ?></strong></p>
<?php if ($application['review_note']): ?><p><?= e((string)$application['review_note']) ?></p><?php endif; ?><?php endif; ?>
<?php if (!$application || $application['status']==='rejected'): ?>
<form method="post" action="/instructor/apply"><?= csrf_field() ?>
<label class="form-label" for="statement">Teaching statement (30–3000 characters)</label>
<textarea class="form-control mb-3" id="statement" name="statement" rows="7" minlength="30" maxlength="3000" required><?= e((string)($application['statement']??'')) ?></textarea>
<button type="submit" class="lm-button lm-button-primary">Submit application</button></form>
<?php else: ?><p class="lm-muted">Your application has been received. Check here for its result.</p><?php endif; ?>
</div></div></section>