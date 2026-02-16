<?php
declare(strict_types=1);

require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/week.php';
require __DIR__ . '/../private/inc/week_lock_helpers.php';
require __DIR__ . '/../private/inc/ats.php';
require __DIR__ . '/../private/inc/maintenance.php';

auth_required();

// Run maintenance (update scores + score_week) at most once every 5 minutes
maintenance_maybe_run_scores($db, 300);

// Current auto season/week (used only as default if query params are missing)
[$AUTO_SEASON, $AUTO_WEEK, $AUTO_STATUS] = current_season_week($db);

// Explicitly take season/week from query string if present
$SEASON = isset($_GET['season']) ? (int)$_GET['season'] : $AUTO_SEASON;
$WEEK   = isset($_GET['week'])   ? (int)$_GET['week']   : $AUTO_WEEK;

// Clamp week to 1–18 just in case
if ($WEEK < 1)  $WEEK = 1;
if ($WEEK > 18) $WEEK = 18;

// For navigation: allow moving up to the current (auto) week for this season
$MAX_WEEK = ($SEASON === $AUTO_SEASON) ? $AUTO_WEEK : 18;

$prevWeek = max(1, $WEEK - 1);
$nextWeek = min($MAX_WEEK, $WEEK + 1);

// Logged-in user info
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$ME_ID   = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id']   : null;
$ME_NAME = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';

// Determine if THIS season/week is locked
$locked = is_week_locked($db, $SEASON, $WEEK);

// ----------------------------------------
// Fetch picks for this season/week
// Before lock: only my picks
// After lock:  everyone’s picks
// ----------------------------------------

$sqlPicks = "
    SELECT
        ps.game_id,
        ps.team_abbr   AS picked_team,
        p.user_id,
        u.name         AS user_name,
        l.fav_team,
        l.dog_team,
        l.spread,
        g.state,
        g.kickoff_utc,
        g.home_team,
        g.away_team,
        g.home_score,
        g.away_score
    FROM pick_selections ps
    JOIN picks p      ON p.id = ps.pick_id
    JOIN users u      ON u.id = p.user_id
    JOIN `lines` l      ON l.game_id = ps.game_id
    JOIN games g      ON g.id = ps.game_id
    WHERE p.season_year = ?
      AND p.week_number = ?
";

$params = [$SEASON, $WEEK];

if (!$locked && $ME_ID !== null) {
    // Pre-lock: only show the logged-in user's picks
    $sqlPicks .= " AND p.user_id = ?";
    $params[] = $ME_ID;
}

$sqlPicks .= "
    ORDER BY
      u.name ASC,
      g.kickoff_utc ASC,
      g.id ASC
";

$stmtPicks = $db->prepare($sqlPicks);
$stmtPicks->execute($params);
$picksRows = $stmtPicks->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Group by user for rendering
$byUser = [];
foreach ($picksRows as $row) {
    $uid = (int)$row['user_id'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = [
            'user_id'   => $uid,
            'user_name' => $row['user_name'],
            'picks'     => [],
        ];
    }
    $byUser[$uid]['picks'][] = $row;
}

// Sort users alphabetically
usort($byUser, function ($a, $b) {
    return strcmp($a['user_name'], $b['user_name']);
});

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Week <?= htmlspecialchars((string)$WEEK) ?> Picks – HSC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  .result { font-weight:600; padding:2px 6px; border-radius:4px; }
  .result--win  { background:#d1fae5; color:#065f46; }
  .result--loss { background:#fee2e2; color:#991b1b; }
  .result--push { background:#fef9c3; color:#92400e; }
  .result--pending { color:#6b7280; }

  .card__head-bar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }
  .week-nav a {
    margin-left:4px;
  }
</style>
</head>
<body class="app">
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
<section class="card">
  <div class="card__head card__head-bar">
    <div>
      Week <?= htmlspecialchars((string)$WEEK) ?> Picks
    </div>
    <div class="week-nav">
      <?php if ($prevWeek < $WEEK): ?>
        <a
          href="<?= url('week_picks.php?season=' . (int)$SEASON . '&week=' . (int)$prevWeek) ?>"
          class="badge"
        >
          &larr; Week <?= (int)$prevWeek ?>
        </a>
      <?php endif; ?>

      <?php if ($nextWeek > $WEEK): ?>
        <a
          href="<?= url('week_picks.php?season=' . (int)$SEASON . '&week=' . (int)$nextWeek) ?>"
          class="badge"
        >
          Week <?= (int)$nextWeek ?> &rarr;
        </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <p class="text-subtle">
      Season <?= htmlspecialchars((string)$SEASON) ?> ·
      <?= $locked
          ? 'All picks are now visible.'
          : 'Only your picks are visible until lock.' ?>
    </p>
  </div>
</section>

  <?php if (empty($byUser)): ?>
    <section class="card mt-24">
      <div class="card__body">
        <p>No picks have been submitted yet for Week <?= htmlspecialchars((string)$WEEK) ?>.</p>
      </div>
    </section>
  <?php else: ?>
    <?php foreach ($byUser as $userBlock): ?>
      <section class="card mt-24">
        <div class="card__head">
          <?= htmlspecialchars($userBlock['user_name']) ?>
        </div>
        <div class="card__body">
          <table class="table w-full">
            <thead>
              <tr class="tr">
                <th class="th">Matchup</th>
                <th class="th">Pick</th>
                <th class="th">Line</th>
                <th class="th">Result</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($userBlock['picks'] as $p): ?>
              <?php
                $home  = $p['home_team'];
                $away  = $p['away_team'];
                $hs    = (int)$p['home_score'];
                $as    = (int)$p['away_score'];
                $state = $p['state'];
                $picked= $p['picked_team'];
                $fav   = $p['fav_team'];
                $dog   = $p['dog_team'];
                $spread= $p['spread'];

				// Build a line array the way ats.php expects it
				$line = [
				    'fav_team' => $fav,
				    'dog_team' => $dog,
				    'spread'   => $spread,
				];

				$lineDisplay = ats_format_spread_for_pick($picked, $fav, (float)$spread);

               // ATS outcome (win/loss/push/pending)
				$outcome = ats_outcome(
				    ['picked_team' => $picked],
				    $line,
				    [
				        'home_team'  => $home,
				        'away_team'  => $away,
				        'home_score' => $hs,
				        'away_score' => $as,
				        'state'      => $state,
				    ]                
				);

                switch ($outcome) {
                    case 'win':  $outLabel = '<span class="result result--win">Win</span>'; break;
                    case 'loss': $outLabel = '<span class="result result--loss">Loss</span>'; break;
                    case 'push': $outLabel = '<span class="result result--push">Push</span>'; break;
                    default:
                        $outLabel = ($state === 'in_progress')
                            ? '<span class="result result--pending">In Progress</span>'
                            : '<span class="result result--pending">Not Started</span>';
                        break;
                }

                $matchup = $away . ' @ ' . $home;
              ?>
              <tr class="tr">
                <td class="td"><?= htmlspecialchars($matchup) ?></td>
                <td class="td"><?= htmlspecialchars($picked) ?></td>
                <td class="td"><?= htmlspecialchars($lineDisplay) ?></td>
                <td class="td"><?= $outLabel ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</body>
</html>