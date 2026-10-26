<?php $user = auth_user(); ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Learmingo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --lm-ink:#10233f; --lm-muted:#66758a; --lm-primary:#246bfe; --lm-border:#e5ebf3; --lm-surface:#fff; }
        html,body { min-height:100%; }
        body { min-height:100vh; display:flex; flex-direction:column; color:var(--lm-ink); background:#f6f8fc; }
        .lm-nav { background:rgba(255,255,255,.94); backdrop-filter:blur(14px); border-bottom:1px solid var(--lm-border); }
        .lm-brand { color:var(--lm-ink); letter-spacing:-.03em; }
        .lm-main { flex:1 0 auto; display:flex; flex-direction:column; }
        .lm-auth-page { flex:1; display:flex; align-items:center; justify-content:center; padding:clamp(2rem,6vw,5rem) 1rem; background:radial-gradient(circle at 15% 10%,rgba(36,107,254,.11),transparent 34%),linear-gradient(135deg,#f8fbff 0%,#f3f6fb 100%); }
        .lm-auth-card { width:min(100%,470px); border:1px solid rgba(218,226,238,.9); border-radius:24px; background:var(--lm-surface); box-shadow:0 24px 70px rgba(25,55,95,.13); }
        .lm-auth-card .form-control { min-height:52px; border-radius:12px; border-color:#d9e2ee; padding:.75rem .95rem; }
        .lm-auth-card .form-control:focus { border-color:var(--lm-primary); box-shadow:0 0 0 .24rem rgba(36,107,254,.14); }
        .lm-auth-card .btn-primary { min-height:52px; border:0; border-radius:12px; background:linear-gradient(135deg,#246bfe,#1554d8); box-shadow:0 10px 20px rgba(36,107,254,.2); }
        .lm-auth-card .btn-primary:hover { background:linear-gradient(135deg,#155fe9,#1047bc); }
        .lm-eyebrow { color:var(--lm-primary); font-size:.75rem; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
        .lm-auth-title { letter-spacing:-.04em; }
        .lm-muted { color:var(--lm-muted); }
        .lm-footer { flex:0 0 auto; border-top:1px solid var(--lm-border); background:#fff; }
        .lm-password-wrap { position:relative; }
        .lm-password-wrap .form-control { padding-right:4.5rem; }
        .lm-password-toggle { position:absolute; top:50%; right:.65rem; transform:translateY(-50%); border:0; background:transparent; color:var(--lm-muted); font-size:.82rem; font-weight:700; }
        @media (max-width:575.98px) { .lm-auth-page { align-items:flex-start; padding-top:2rem; } .lm-auth-card { border-radius:18px; } }
    </style>
</head>
<body>
<nav class="navbar lm-nav py-3"><div class="container"><a class="navbar-brand fw-bold lm-brand" href="/">Learmingo</a><div class="d-flex gap-3 align-items-center">
    <?php if ($user): ?>
        <a href="/dashboard" class="text-decoration-none">Dashboard</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?><a href="/admin" class="text-decoration-none">Admin</a><?php endif; ?>
        <?php if (in_array($user['role'] ?? '', ['admin','instructor'], true)): ?><a href="/instructor" class="text-decoration-none">Instructor</a><?php endif; ?>
        <form method="post" action="/logout" class="m-0"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm">Logout</button></form>
    <?php else: ?><a href="/login" class="btn btn-primary px-3">Login</a><?php endif; ?>
</div></div></nav>
<main class="lm-main"><?= $content ?></main>
<footer class="lm-footer"><div class="container py-4 text-muted small">Learmingo LMS · v0.0.1 · Ton Samith</div></footer>
<script>
document.querySelectorAll('[data-password-toggle]').forEach(function (button) { button.addEventListener('click', function () { const input=document.getElementById(button.dataset.passwordToggle); const visible=input.type==='text'; input.type=visible?'password':'text'; button.textContent=visible?'Show':'Hide'; button.setAttribute('aria-pressed', String(!visible)); }); });
</script>
</body>
</html>
