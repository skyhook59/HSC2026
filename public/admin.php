<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/csrf.php';
auth_required();
if (empty($_SESSION['is_admin'])) { http_response_code(403); echo "Admins only"; exit; }

// Fetch all users to populate the dropdown
try {
    $stmt = $db->query("SELECT id, name FROM users ORDER BY name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle database error gracefully
    error_log('Admin user-list load failed: ' . $e->getMessage());
    $users = [];
    $error_message = "Failed to load user list.";
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin — Gridiron Slate</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    .form{display:grid; gap:12px}
    .label{font-size:14px; color:var(--muted)}
    .input, .select{height:44px; padding:0 12px; border-radius:12px; border:1px solid var(--line); background:#0B1220; color:var(--text); width:100%}
    .select{appearance:none; -webkit-appearance:none; -moz-appearance:none; cursor:pointer;}
    .textarea{min-height:92px; padding:10px 12px; border-radius:12px; border:1px solid var(--line); background:#0B1220; color:var(--text)}
    .section-title{font-weight:800; margin-bottom:8px}
    .help{font-size:13px; color:var(--muted)}
    .notice{background:#0b1220;border:1px solid var(--line);padding:10px 12px;border-radius:10px;color:var(--muted);font-size:13px}
  </style>
</head>
<body class="app">
<header class="header">
  <div class="container header__bar">
    <div class="brand"><div class="brand__logo"></div> Super Picks</div>
    <div class="header__right"><a href="<?= url('menu.php') ?>" class="btn btn--ghost">Menu</a></div>
  </div>
</header>

<main class="container mt-24">
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(360px,1fr));">
    <section class="card">
      <div class="card__head">Submit Picks on Behalf</div>
      <div class="card__body">
        <?php if (isset($error_message)): ?>
            <div class="error-message notice mb-16"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= url('api/admin_submit_on_behalf.php') ?>" class="form">
          <?= csrf_field() ?>
          <div>
            <div class="label">User Name</div>
            <select class="select w-full" name="user_id" required>
                <?php if (empty($users)): ?>
                    <option value="" disabled selected>No users found</option>
                <?php else: ?>
                    <option value="" disabled selected>Select a user</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['name']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
          </div>
          <div class="row">
            <div style="flex:1">
              <div class="label">Season</div>
              <input class="input w-full" name="season" placeholder="e.g., 2025" required>
            </div>
            <div style="flex:1">
              <div class="label">Week</div>
              <input class="input w-full" name="week" placeholder="e.g., 1" required>
            </div>
          </div>
          <div>
            <div class="label">Team Abbreviations (comma-separated, 5 total)</div>
            <input class="input w-full" name="picks_abbr" placeholder="e.g., KC, PHI, SF, DAL, BUF" required>
            <div class="help">Use 3-letter codes (KC, PHI, SF, DAL, BUF, etc). Case-insensitive. Time checks are skipped.</div>
          </div>
          <button class="btn btn--primary">Submit</button>
        </form>
        <div class="notice mt-16">
          Rules enforced: valid team codes, exactly 5 teams, no duplicates, and you can’t pick both sides of the same game.
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card__head">Score a Week</div>
      <div class="card__body">
        <form method="post" action="<?= url('api/admin/score_week.php') ?>" class="form">
          <?= csrf_field() ?>
          <div class="row">
            <div style="flex:1">
              <div class="label">Season</div>
              <input class="input w-full" name="season" placeholder="e.g., 2025" required>
            </div>
            <div style="flex:1">
              <div class="label">Week</div>
              <input class="input w-full" name="week" placeholder="e.g., 1" required>
            </div>
          </div>
          <button class="btn btn--primary">Score Now</button>
        </form>
      </div>
      <div class="card__foot subtle">Safe to run multiple times; only FINAL games count.</div>
    </section>
<p>
  <a href="<?= url('admin_import_lines.php') ?>">Import SuperContest Lines (from PDF)</a>
</p>
  </div>
</main>
</body>
</html>
