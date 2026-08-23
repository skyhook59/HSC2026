<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/csrf.php';
if (!empty($_SESSION['user_id'])) { redirect('menu.php'); }
$err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign in — Gridiron Slate</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    .auth-card{max-width:420px; margin:48px auto}
    .label{font-size:14px; color:var(--muted)}
    .input{height:44px; padding:0 12px; border-radius:12px; border:1px solid var(--line); background:#0B1220; color:var(--text); width:100%}
    .help{font-size:13px; color:var(--muted)}
    .error-banner{background:#2a0f12; border:1px solid #7f1d1d; color:#fecaca; padding:10px 12px; border-radius:10px; margin-bottom:10px}
  </style>
</head>
<body class="app">
<header class="header">
  <div class="container header__bar">
    <div class="brand"><div class="brand__logo"></div>HSC</div>
  </div>
</header>

<main class="container auth-card">
  <section class="card">
    <div class="card__head">Sign in</div>
    <div class="card__body">
      <?php if($err): ?>
        <div class="error-banner"><?=htmlspecialchars($err)?></div>
      <?php endif; ?>
      <form method="post" action="<?= url('login.php') ?>" class="form" style="display:grid; gap:12px">
        <?= csrf_field() ?>
        <div>
          <div class="label">Email</div>
          <input name="email" type="email" required class="input">
        </div>
        <div>
          <div class="label">Password</div>
          <input name="password" type="password" required class="input">
        </div>
        <button class="btn btn--primary" style="width:100%">Sign in</button>
      </form>
      <div class="help mt-16">Need an account? Contact the pool administrator.</div>
    </div>
  </section>
</main>
</body>
</html>
