<?php
$frontend = \Learmingo\Services\FrontendSettings::all();
$user = auth_user();
$admin = is_array($user) && ($user['role'] ?? '') === 'admin';
$brand = $frontend['brand'] !== '' ? $frontend['brand'] : 'Learmingo';
$svgSearch = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="7.3"/><path d="m16.2 16.2 5 5"/></svg>';
$svgUser = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.4 21c.1-4.6 3.2-7 7.6-7s7.5 2.4 7.6 7"/></svg>';
$svgTheme = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M20.5 14.4A9 9 0 0 1 9.6 3.5 9 9 0 1 0 20.5 14.4Z"/></svg>';
$logoUrl = $frontend['logo_light'];
?>
<!doctype html>
<html lang="en" data-default-theme="<?= e($frontend['default_theme']) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($frontend['hero_subtitle']) ?>">
<meta name="theme-color" content="#ffffff">
<title><?= e($brand) ?> — Learn with purpose</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/learmingo-premium.css?v=1.0.0">
<link rel="stylesheet" href="/learmingo-ui/style?v=1.0.2">
<script src="/learmingo-ui/script?v=1.0.2" defer></script>
</head>
<body class="lm-v2">
<a class="lm-skip" href="#main-content">Skip to content</a>
<header class="lm2-header<?= $frontend['header_sticky']==='1' ? ' lm2-header-sticky' : '' ?>" role="banner">
 <div class="lm2-header-inner lm-container">
  <a class="lm2-brand" href="/" aria-label="<?= e($brand) ?> homepage"><span class="lm2-brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="24" height="24" fill="none"><path d="M3 10.5 16 4l13 6.5L16 17 3 10.5Z" fill="currentColor"/><path d="M8 16v7c5 4 11 4 16 0v-7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><path d="M29 11v10" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></span>
  <?php if ($logoUrl !== ''): ?><img class="lm2-logo-img lm2-logo-light" src="<?= e($logoUrl) ?>" alt="<?= e($brand) ?>"><?php if ($frontend['logo_dark'] !== ''): ?><img class="lm2-logo-img lm2-logo-dark" src="<?= e($frontend['logo_dark']) ?>" alt="<?= e($brand) ?>"><?php endif; ?><?php else: ?><span class="lm2-brand-name"><?= e($brand) ?></span><?php endif; ?></a>
  <?php if ($frontend['header_search']==='1'): ?><form class="lm2-header-search" role="search" action="/courses" method="get"><label class="visually-hidden" for="global-course-search">Search courses</label><?= $svgSearch ?><input id="global-course-search" name="q" type="search" maxlength="150" autocomplete="off" placeholder="<?= e($frontend['search_hint']) ?>" value="<?= e(is_string($_GET['q'] ?? null) ? substr($_GET['q'],0,150) : '') ?>"><button type="submit">Search</button></form><?php endif; ?>
  <nav class="lm2-nav" aria-label="Primary navigation"><a href="<?= e($frontend['courses_url']) ?>"<?= strtok($_SERVER['REQUEST_URI'] ?? '', '?') === '/courses' ? ' aria-current="page"' : '' ?>><?= e($frontend['courses_label']) ?></a><a href="<?= e($frontend['categories_url']) ?>"<?= strtok($_SERVER['REQUEST_URI'] ?? '', '?') === '/categories' ? ' aria-current="page"' : '' ?>><?= e($frontend['categories_label']) ?></a></nav>
  <details class="lm2-user-menu" data-user-menu><summary class="lm2-user-trigger" aria-label="Account menu"><?= $svgUser ?><span class="lm2-user-chevron" aria-hidden="true">⌄</span></summary>
   <div class="lm2-user-popover"><div class="lm2-popover-heading"><?= $user ? e((string)$user['name']) : 'Your account' ?></div>
   <?php if ($user): ?><a href="/dashboard">My dashboard</a><?php if ($admin): ?><a href="/admin">Administration</a><a href="/dashboard/appearance">Frontend appearance</a><?php endif; ?><?php if (in_array($user['role'] ?? '', ['admin','instructor'], true)): ?><a href="/instructor">Instructor center</a><?php endif; ?><form action="/logout" method="post"><?= csrf_field() ?><button type="submit">Sign out</button></form>
   <?php else: ?><a href="/login"><?= e($frontend['sign_in_label']) ?></a><a href="/register"><?= e($frontend['sign_up_label']) ?></a><?php endif; ?>
   <div class="lm2-popover-divider"></div><button class="lm2-theme-button" type="button" data-theme-toggle><?= $svgTheme ?><span>Switch to dark mode</span><span class="lm2-toggle-indicator" aria-hidden="true"></span></button>
   </div>
  </details>
  <details class="lm2-mobile-menu" data-mobile-menu><summary aria-label="Open navigation"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary><nav aria-label="Mobile navigation"><a href="<?= e($frontend['courses_url']) ?>"><?= e($frontend['courses_label']) ?></a><a href="<?= e($frontend['categories_url']) ?>"><?= e($frontend['categories_label']) ?></a><?php if ($user): ?><a href="/dashboard">Dashboard</a><?php if ($admin): ?><a href="/dashboard/appearance">Frontend appearance</a><?php endif; ?><?php else: ?><a href="/login">Sign in</a><a href="/register">Sign up</a><?php endif; ?></nav></details>
 </div>
 <?php if ($frontend['header_search']==='1'): ?><div class="lm2-mobile-search lm-container"><form action="/courses" method="get" role="search"><?= $svgSearch ?><label class="visually-hidden" for="mobile-course-search">Search courses</label><input id="mobile-course-search" name="q" placeholder="<?= e($frontend['search_hint']) ?>" maxlength="150"><button type="submit">Go</button></form></div><?php endif; ?>
</header>
<main class="lm-main lm2-main" id="main-content"><?= $content ?></main>
<footer class="lm2-footer">
 <div class="lm-container">
  <div class="lm2-footer-grid lm2-footer-cols-<?= (int)$frontend['footer_columns'] ?>">
  <?php for ($col=1; $col <= (int)$frontend['footer_columns']; $col++): ?>
  <div class="lm2-footer-col"><h2><?= e($frontend['footer_title_'.$col]) ?></h2><?php if ($col===1): ?><p class="lm2-footer-about"><?= e($frontend['footer_about']) ?></p><?php endif; ?>
  <?php $links=\Learmingo\Services\FrontendSettings::links($frontend['footer_links_'.$col]); if ($links): ?><nav aria-label="<?= e($frontend['footer_title_'.$col]) ?> links"><?php foreach ($links as $item): ?><a href="<?= e($item['url']) ?>"<?= str_starts_with($item['url'],'https://') ? ' rel="noopener noreferrer"' : '' ?>><?= e($item['title']) ?></a><?php endforeach; ?></nav><?php endif; ?>
  </div><?php endfor; ?></div>
  <div class="lm2-footer-bottom"><p><?= e($frontend['copyright']) ?></p><div class="lm2-socials" aria-label="Social media">
  <?php foreach (['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','youtube'=>'YouTube'] as $slug=>$label): $active=$frontend['social_'.$slug] !== ''; ?><?php if ($active): ?><a class="lm2-social" href="<?= e($frontend['social_'.$slug]) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($label) ?>"><?php else: ?><span class="lm2-social lm2-social-muted" aria-label="<?= e($label) ?> — not configured" title="Configure <?= e($label) ?> in appearance settings"><?php endif; ?><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php if ($slug==='facebook'): ?><path d="M15 21v-8h3l.5-4H15V7.2c0-1 .3-1.7 1.7-1.7H19V2.1A26 26 0 0 0 16 2c-3.1 0-5 1.8-5 5v2H8v4h3v8"/><?php elseif ($slug==='instagram'): ?><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1" fill="currentColor" stroke="none"/><?php elseif ($slug==='linkedin'): ?><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 10v7M8 7v.01M12 17v-7h3v1a3 3 0 0 1 5 2v4"/><?php else: ?><rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3Z"/><?php endif; ?></svg><?php if ($active): ?></a><?php else: ?></span><?php endif; ?><?php endforeach; ?>
  </div></div>
 </div>
</footer>
<script src="/assets/js/learmingo-ui.js?v=1.0.0" defer></script>
</body></html>
