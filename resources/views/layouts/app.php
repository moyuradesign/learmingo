<?php $user = auth_user(); ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <meta name="description" content="Learn practical skills with self-paced courses and expert instructors on Learmingo.">
  <title>Learmingo — Learn with purpose</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/learmingo-premium.css?v=1.0.0" rel="stylesheet">
</head>
<body>
<header class="lm-nav">
  <div class="lm-container lm-nav-inner">
    <a class="lm-brand" href="/" aria-label="Learmingo home">
      <span class="lm-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.3"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M6 9v6c3.5 3 8.5 3 12 0V9"/><path d="M21 7v8"/></svg></span>Learmingo
    </a>
    <nav class="lm-nav-links" aria-label="Main navigation">
      <a href="/courses">Explore courses</a>
      <a href="/#how-it-works">How it works</a>
      <?php if ($user): ?><a href="/dashboard">My learning</a><?php endif; ?>
    </nav>
    <div class="lm-nav-actions">
      <?php if ($user): ?>
        <a class="lm-button lm-button-outline" href="/dashboard">Dashboard</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?><a href="/admin" class="lm-button lm-button-outline">Admin</a><a href="/admin/courses" class="lm-button lm-button-outline">Course review</a><?php endif; ?>
        <?php if (in_array($user['role'] ?? '', ['admin', 'instructor'], true)): ?><a href="/instructor" class="lm-button lm-button-outline">Instructor</a><?php endif; ?>
        <form action="/logout" method="post"><?= csrf_field() ?><button type="submit" class="lm-button lm-button-primary">Sign out</button></form>
      <?php else: ?>
        <a href="/login" class="lm-button lm-button-outline">Sign in</a>
        <a href="/register" class="lm-button lm-button-primary">Get started <span aria-hidden="true">→</span></a>
      <?php endif; ?>
    </div>
    <div class="lm-mobile-menu">
      <details><summary>Menu <span aria-hidden="true">☰</span></summary>
        <nav class="lm-mobile-panel" aria-label="Mobile navigation">
          <a href="/courses">Explore courses</a><a href="/#how-it-works">How it works</a>
          <?php if ($user): ?>
            <a href="/dashboard">My learning</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?><a href="/admin">Admin</a><a href="/admin/courses">Course review</a><?php endif; ?>
            <?php if (in_array($user['role'] ?? '', ['admin', 'instructor'], true)): ?><a href="/instructor">Instructor</a><?php endif; ?>
            <form action="/logout" method="post"><?= csrf_field() ?><button type="submit">Sign out</button></form>
          <?php else: ?><a href="/login">Sign in</a><a href="/register">Create an account</a><?php endif; ?>
        </nav>
      </details>
    </div>
  </div>
</header>
<main id="main-content" class="lm-main"><?= $content ?></main>
<footer class="lm-footer">
  <div class="lm-container">
    <div class="lm-footer-top">
      <div><a class="lm-brand" href="/"><span class="lm-brand-mark" aria-hidden="true">L</span>Learmingo</a><p class="lm-footer-about">Practical learning for people who want to keep growing. Discover courses, build new skills and progress at your own pace.</p></div>
      <div><h2 class="lm-footer-title">Discover</h2><nav class="lm-footer-links" aria-label="Explore"><a href="/courses">Browse courses</a><a href="/#how-it-works">How it works</a><a href="/register">Start learning</a></nav></div>
      <div><h2 class="lm-footer-title">Account & support</h2><nav class="lm-footer-links" aria-label="Account and support"><a href="/login">Sign in</a><a href="/dashboard">My learning</a><a href="https://tonsamith.com/contact" rel="noopener noreferrer">Contact support</a></nav></div>
    </div>
    <div class="lm-footer-bottom"><span>© <?= date('Y') ?> Learmingo. All rights reserved.</span><span>Crafted by <a href="https://tonsamith.com" rel="noopener noreferrer">Ton Samith</a> · v0.0.1</span></div>
  </div>
</footer>
<script src="/assets/js/learmingo-ui.js?v=1.0.0" defer></script>
</body>
</html>
