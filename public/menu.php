<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
auth_required();

$me_name = $_SESSION['user']['name'] ?? $_SESSION['user']['email'] ?? 'You';
$is_admin = false;
if (function_exists('is_admin')) {
  $is_admin = is_admin();
} else {
  try {
    if (isset($_SESSION['user']['id'])) {
      $stmt = $db->prepare("SELECT is_admin FROM users WHERE id=?");
      $stmt->execute([$_SESSION['user']['id']]);
      $is_admin = (bool)($stmt->fetchColumn());
    }
  } catch (Throwable $e) { }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Menu — HSC</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    html, body { overflow-x: hidden; }
    .header__bar { flex-wrap: wrap; }
    .brand, .header__right { flex: 0 0 auto; }
    .hero {
      display:flex; align-items:center; justify-content:space-between;
      padding: 18px 20px; border-radius: 16px;
      background: radial-gradient(100% 120% at 0% 0%, rgba(255,184,0,0.12) 0%, rgba(0,0,0,0) 50%) , #0B1220;
      border:1px solid var(--line);
      margin-top: 18px;
    }
    .hero__title { font-size: 20px; font-weight: 800; }
    .hero__meta { color: var(--muted); font-size: 14px; }
    .tiles {
      margin-top: 18px;
      display:grid; gap: 14px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (max-width: 560px){ .tiles { grid-template-columns: 1fr; } }
    .tile {
      display:flex; align-items:center; justify-content:space-between;
      padding: 16px; border-radius: 16px; border:1px solid var(--line);
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0));
      transition: transform .06s ease, box-shadow .15s ease, border-color .15s ease;
      text-decoration:none; color:inherit;
    }
    .tile:hover { transform: translateY(-2px); border-color: var(--accent); box-shadow: 0 8px 28px rgba(245,158,11,0.12); }
    .tile__left { display:flex; align-items:center; gap:12px; }
    .tile__icon {
      width:36px; height:36px; border-radius:10px;
      display:grid; place-items:center; font-size:18px; font-weight:800;
      background: radial-gradient(120% 120% at 30% 20%, rgba(255,184,0,.34), rgba(24,34,54,1));
      border:1px solid var(--line);
    }
    .tile__label { font-weight: 800; font-size: 16px; letter-spacing: .3px; }
    .tile__chev { width:28px; height:28px; border-radius: 999px; border:1px dashed #2B384A; display:grid; place-items:center; }
    .footer-note { color: var(--muted); font-size: 13px; margin-top: 18px; }
    .right-actions { display:flex; gap:8px; }
    .btn-small { padding:6px 10px; border:1px solid var(--line); border-radius:10px; color:inherit; text-decoration:none; }
    .btn-small:hover { border-color: var(--accent); }
  </style>
</head>
<body class="app">
  <header class="header">
    <div class="container header__bar">
      <div class="brand"><div class="brand__logo"></div> HSC</div>
      <div class="header__center"></div>
      <div class="header__right right-actions">
        <a href="<?= url('week_picks.php') ?>" class="btn-small">This Week</a>
        <a href="<?= url('logout.php') ?>" class="btn-small">Logout</a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="hero">
      <div>
        <div class="hero__title">Hey, <?php echo htmlspecialchars($me_name); ?> 👋</div>
        <div class="hero__meta">Welcome to Helga’s Super Contest dashboard.</div>
      </div>
    </div>

    <div class="tiles">
      <a class="tile" href="<?= url('picks.php') ?>">
        <div class="tile__left">
          <div class="tile__icon">🎯</div>
          <div class="tile__label">Enter Picks</div>
        </div>
        <div class="tile__chev">›</div>
      </a>

      <a class="tile" href="<?= url('week_picks.php') ?>">
        <div class="tile__left">
          <div class="tile__icon">👥</div>
          <div class="tile__label">Everyone's Picks</div>
        </div>
        <div class="tile__chev">›</div>
      </a>

      <a class="tile" href="<?= url('standings.php') ?>">
        <div class="tile__left">
          <div class="tile__icon">🏆</div>
          <div class="tile__label">Standings</div>
        </div>
        <div class="tile__chev">›</div>
      </a>

<!-- ✅ NEW: Quarterly Leaders button -->
      <a class="tile" href="<?= url('quarterly.php') ?>">
         <div class="tile__left">
           <div class="tile__icon">🥈</div>
           <div class="tile__label">Quarterly Winners</div>
        </div>
		<div class="tile__chev">›</div>
      </a>

      <?php if ($is_admin): ?>
      <a class="tile" href="<?= url('admin.php') ?>">
        <div class="tile__left">
          <div class="tile__icon">🛠</div>
          <div class="tile__label">Admin</div>
        </div>
        <div class="tile__chev">›</div>
      </a>
      <?php endif; ?>
    </div>

    <div class="footer-note">Tip: Picks lock Saturday 11:59pm PT. Thursday games must be submitted before kickoff.</div>
  </main>
</body>
</html>
