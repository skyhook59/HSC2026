<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/csrf.php';
$err = '';
if (!empty($_POST)) {
  // CSRF protection
  if (!csrf_verify()) {
    $err = 'Invalid request. Please try again.';
  } else {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $name  = trim($_POST['name'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($email && $name && $pass) {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $db->prepare("INSERT INTO users (email, name, password_hash) VALUES (?,?,?)");
      try { $stmt->execute([$email,$name,$hash]); redirect('index.php'); }
      catch(Throwable $e){ $err='Email already used.'; }
    } else { $err='All fields required.'; }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register — Gridiron Slate</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    .auth-card{max-width:480px; margin:48px auto}
    .label{font-size:14px; color:var(--muted)}
    .input{height:44px; padding:0 12px; border-radius:12px; border:1px solid var(--line); background:#0B1220; color:var(--text); width:100%}
    .error-banner{background:#2a0f12; border:1px solid #7f1d1d; color:#fecaca; padding:10px 12px; border-radius:10px; margin-bottom:10px}
  </style>
</head>
<body class="app">
<header class="header">
  <div class="container header__bar">
    <div class="brand"><div class="brand__logo"></div> HSC</div>
    <div class="header__right"><a href="<?= url('index.php') ?>" class="btn btn--ghost">Sign in</a></div>
  </div>
</header>

<main class="container auth-card">
  <section class="card">
    <div class="card__head">Create account</div>
    <div class="card__body">
      <?php if(!empty($err)): ?><div class="error-banner"><?=htmlspecialchars($err)?></div><?php endif; ?>
      <form method="post" class="form" style="display:grid; gap:12px">
        <?= csrf_field() ?>
        <div>
          <div class="label">Username</div>
          <input name="name" required class="input">
        </div>
        <div>
          <div class="label">Email</div>
          <input name="email" type="email" required class="input">
        </div>
        <div>
          <div class="label">Password</div>
          <input name="password" type="password" required class="input">
        </div>
        <button class="btn btn--primary" style="width:100%">Register</button>
      </form>
    </div>
  </section>
</main>
</body>
</html>
