<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/maintenance.php';
auth_required();

// This will keep scores fresh, but no more than once every 5 minutes
maintenance_maybe_run_scores($db, 300);


/* Determine latest season (same approach as standings.php) */
$stmtSeason = $db->query("SELECT MAX(season_year) AS season_year FROM weeks");
$rowSeason  = $stmtSeason->fetch(PDO::FETCH_ASSOC);
$SEASON     = $rowSeason && $rowSeason['season_year'] ? (int)$rowSeason['season_year'] : (int)date('Y');

/* Define the four “quarters” */
$quarters = [
    [ 'label' => 'Quarter 1 (Weeks 1–4)',   'start_week' => 1,  'end_week' => 4 ],
    [ 'label' => 'Quarter 2 (Weeks 5–8)',   'start_week' => 5,  'end_week' => 8 ],
    [ 'label' => 'Quarter 3 (Weeks 9–13)',  'start_week' => 9,  'end_week' => 13 ],
    [ 'label' => 'Quarter 4 (Weeks 14–18)', 'start_week' => 14, 'end_week' => 18 ],
];

/* Fetch top N for a given quarter */
function fetch_quarter_leaders(PDO $db, int $season, int $startWeek, int $endWeek, int $limit = 4): array {
    $sql = "
        SELECT
            u.id   AS user_id,
            u.name AS name,
            SUM(r.wins)   AS wins,
            SUM(r.losses) AS losses,
            SUM(r.pushes) AS pushes,
            SUM(r.points) AS points
        FROM results r
        JOIN users u ON u.id = r.user_id
        WHERE r.season_year = :season
          AND r.week_number BETWEEN :sw AND :ew
        GROUP BY u.id, u.name
        ORDER BY points DESC, wins DESC, pushes DESC, u.name ASC
        LIMIT :limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, PDO::PARAM_INT);
    $stmt->bindValue(':sw', $startWeek, PDO::PARAM_INT);
    $stmt->bindValue(':ew', $endWeek, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['wins']   = (int)($row['wins'] ?? 0);
        $row['losses'] = (int)($row['losses'] ?? 0);
        $row['pushes'] = (int)($row['pushes'] ?? 0);
        $row['points'] = isset($row['points']) ? (float)$row['points'] : 0.0;
    }
    unset($row);

    return $rows;
}

/* Build data for all four quarters */
$quarterStandings = [];
foreach ($quarters as $idx => $q) {
    $quarterStandings[$idx] = fetch_quarter_leaders(
        $db,
        $SEASON,
        $q['start_week'],
        $q['end_week'],
        4
    );
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Quarterly Standings — HSC</title>

  <!-- same includes as standings.php -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
</head>
<body class="app">

  <!-- SAME HEADER BAR AS standings.php -->
  <header class="header">
    <div class="container header__bar">
      <div class="brand">
        <div class="brand__logo"></div>
        HSC
      </div>
      <div class="header__center"></div>
      <div class="header__right">
        <a href="<?= url('menu.php') ?>" class="badge">Menu</a>
      </div>
    </div>
  </header>

  <main class="container mt-24">

    <!-- Page title card (mirrors Overall Standings card) -->
    <section class="card">
      <div class="card__head">
        Quarterly Contest Winners
      </div>
      <div class="card__body">
        <p class="text-subtle">
          Season <?= htmlspecialchars((string)$SEASON, ENT_QUOTES, 'UTF-8') ?> &mdash;
          (Weeks 1&ndash;4, 5&ndash;8, 9&ndash;13, 14&ndash;18)
        </p>
      </div>
    </section>

    <!-- One card per quarter, same card/table styling as standings -->
    <?php foreach ($quarters as $index => $q): ?>
      <?php $rows = $quarterStandings[$index] ?? []; ?>

      <section class="card mt-24">
        <div class="card__head">
          <?= htmlspecialchars($q['label'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="card__body">
          <?php if (empty($rows)): ?>
            <p class="text-subtle">No results yet for this quarter.</p>
          <?php else: ?>
            <table class="table w-full">
              <thead>
                <tr class="tr">
                  <!-- IMPORTANT: class="rank" so you get the orange pill from gridiron.css -->
                  <th class="th rank">#</th>
                  <th class="th">Player</th>
                  <th class="th">W-L-P</th>
                  <th class="th">Pts</th>
                </tr>
              </thead>
              <tbody>
                <?php $rank = 1; ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                    // First place row can get the same highlight treatment if desired
                    $trClass = ($rank === 1) ? 'tr tr--leader' : 'tr';
                  ?>
                  <tr class="<?= $trClass ?>">
                    <td class="td rank">
                      <?= $rank ?>
                    </td>
                    <td class="td">
                      <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="td">
                      <?= $row['wins'] ?>-<?= $row['losses'] ?>-<?= $row['pushes'] ?>
                    </td>
                    <td class="td">
                      <strong><?= number_format($row['points'], 1) ?></strong>
                    </td>
                  </tr>
                  <?php $rank++; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>

  </main>
</body>
</html>