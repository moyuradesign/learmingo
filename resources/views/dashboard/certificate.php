
<style>
.lm-certificate-sheet{padding:4rem 3rem;margin:2rem auto;background:#fff;color:#172a44;border:12px double #d4b36b;max-width:980px;text-align:center;box-shadow:0 14px 40px rgba(0,0,0,.06)}
.lm-certificate-sheet .lm-certificate-label{letter-spacing:.22em;text-transform:uppercase;color:#997436;font-weight:700;font-size:.9rem}
.lm-certificate-sheet h2{font-size:clamp(2rem,5vw,3.7rem);font-weight:700;margin:1rem 0}
.lm-certificate-sheet .lm-certificate-name{font-size:clamp(1.8rem,4vw,3rem);border-bottom:1px solid #d4b36b;display:inline-block;padding:.5rem 1.4rem}
.lm-certificate-sheet .lm-certificate-course{font-weight:700;font-size:1.5rem}
.lm-certificate-sheet .lm-credential{font:12px/1.4 monospace;overflow-wrap:anywhere}
@media print {
 .lm-nav,.lm-footer,.lm-print-actions,.lm-certificate-heading{display:none !important}
 html,body,main{margin:0 !important;background:#fff !important}
 .lm-certificate-sheet{max-width:none;box-shadow:none;margin:0;page-break-inside:avoid;min-height:170mm}
 @page{size:A4 landscape;margin:10mm}
}
</style>
<?php if ($certificate): ?>
<section class="lm-container lm-certificate-heading"><span class="lm-eyebrow d-block pt-4">Official record</span><h1 class="lm-page-title">Verified certificate of completion</h1></section>
<article class="lm-certificate-sheet">
  <p class="lm-certificate-label">Learmingo · Certificate of Completion</p>
  <h2>Certificate of Achievement</h2>
  <p>This certifies that</p>
  <p class="lm-certificate-name"><?= e($certificate['learner_name']) ?></p>
  <p class="mt-4">has successfully completed</p>
  <p class="lm-certificate-course"><?= e($certificate['course_title']) ?></p>
  <p class="mt-4">Issued: <?= e($certificate['issued_at']) ?></p>
  <p class="lm-muted small mt-4">Verify this credential on Learmingo using the unique verification link.</p>
  <p class="lm-credential"><?= e((string)($_GET['code'] ?? '')) ?></p>
  <p class="lm-certificate-label mt-4">Verified · Valid at the time of lookup</p>
</article>
<div class="lm-container lm-print-actions text-center mb-5">
  <button class="lm-button lm-button-primary" type="button" onclick="window.print()">Print / Save as PDF</button>
  <a class="lm-button lm-button-outline" href="/student/records">My records</a>
</div>
<?php else: ?>
<section class="lm-page-hero"><div class="lm-container"><h1 class="lm-page-title">Certificate not found or revoked</h1>
<p class="lm-page-subtitle">This credential is not valid. Check the original verification link or contact support.</p></div></section>
<?php endif; ?>