<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/week.php';
require __DIR__ . '/../private/inc/ats.php';
require __DIR__ . '/../private/inc/maintenance.php';
auth_required();

// This will keep scores fresh, but no more than once every 5 minutes
maintenance_maybe_run_scores($db, 300);

// Call the function and get the season, week, and status
[$AUTO_SEASON, $AUTO_WEEK, $status] = current_season_week($db);

// Determine which week's standings to display
$WEEK_TO_SHOW = $AUTO_WEEK;
if ($status === 'prelock' && $AUTO_WEEK > 1) {
    $WEEK_TO_SHOW = $AUTO_WEEK - 1;
}
echo $WEEK_TO_SHOW;
$sqlOverall = "
    SELECT u.id AS user_id, u.name, s.total_wins, s.total_losses, s.total_pushes, s.total_points, s.last_updated_utc
    FROM standings s
    JOIN users u ON u.id = s.user_id
    WHERE s.season_year = ?
    ORDER BY s.total_points DESC, s.total_wins DESC, s.total_pushes DESC";
$stmtOverall = $db->prepare($sqlOverall);
$stmtOverall->execute([$AUTO_SEASON]);
$overallStandings = $stmtOverall->fetchAll(PDO::FETCH_ASSOC);

$sqlWeekly = "
    SELECT u.id AS user_id, u.name, r.wins, r.losses, r.pushes, r.points
    FROM results r
    JOIN users u ON u.id = r.user_id
    WHERE r.season_year = ? AND r.week_number = ?
    ORDER BY r.points DESC, r.wins DESC, r.pushes DESC";
$stmtWeekly = $db->prepare($sqlWeekly);
$stmtWeekly->execute([$AUTO_SEASON, $WEEK_TO_SHOW]);
$weeklyStandings = $stmtWeekly->fetchAll(PDO::FETCH_ASSOC);

$sqlPicks = "
    SELECT
        ps.game_id,
        ps.team_abbr AS picked_team,
        p.user_id,
        l.fav_team,
        l.dog_team,
        l.spread,
        g.state,
        g.home_team,
        g.away_team,
        g.home_score,
        g.away_score
    FROM pick_selections ps
    JOIN picks p ON p.id = ps.pick_id
    JOIN users u ON u.id = p.user_id
    JOIN `lines` l ON l.game_id = ps.game_id
    JOIN games g ON g.id = l.game_id
    WHERE p.season_year = ? AND p.week_number = ?";
$stmtPicks = $db->prepare($sqlPicks);
$stmtPicks->execute([$AUTO_SEASON, $WEEK_TO_SHOW]);
$picksData = $stmtPicks->fetchAll(PDO::FETCH_ASSOC);
$picksByUserId = [];
foreach ($picksData as $pick) {
    // Compute per-pick outcome on the server to keep logic consistent everywhere
    $game = [
        'home_team'  => $pick['home_team'],
        'away_team'  => $pick['away_team'],
        'home_score' => $pick['home_score'],
        'away_score' => $pick['away_score'],
        'state'      => $pick['state'] ?? 'final',
    ];
    $line = [
        'fav_team' => $pick['fav_team'],
        'dog_team' => $pick['dog_team'],
        'spread'   => $pick['spread'],
    ];
    $pickWithOutcome = $pick;
    $pickWithOutcome['outcome'] = ats_outcome(['picked_team' => $pick['picked_team']], $line, $game);
    $pickWithOutcome['spread_display'] = ats_format_spread_for_pick($pick['picked_team'], $pick['fav_team'], (float)$pick['spread']);
    $picksByUserId[$pick['user_id']][] = $pickWithOutcome;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Standings — HSC</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    .accordion-toggle {
        cursor: pointer;
        display: block;
        padding: 8px 0;
        color: #FFFFFF;
        text-decoration: underline;
    }
    .accordion-content {
        display: none;
        overflow: hidden;
        border-top: 1px solid var(--line);
    }
    .accordion-content.open {
        display: table-row;
    }
    .pick-result {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
        color: #fff;
    }
    .result-win { background-color: #10B981; }
    .result-loss { background-color: #EF4444; }
    .result-push { background-color: #FBBF24; }
    
    @media (max-width: 600px) {
      .table .th, .table .td {
        font-size: 14px;
        padding: 8px 6px;
      }
    }
    /* Styles for the new chart accordion to prevent conflicts */
    .chart-accordion-content {
      overflow: hidden;
    }
    .chart-accordion-row {
        display: none;
    }
    .chart-accordion-row.open {
        display: table-row;
    }
  </style>
</head>
<body class="app">
  <header class="header">
    <div class="container header__bar">
      <div class="brand"><div class="brand__logo"></div> HSC</div>
      <div class="header__center"></div>
      <div class="header__right"><a href="<?= url('menu.php') ?>" class="badge">Menu</a></div>
    </div>
  </header>

  <main class="container mt-24">
    <section class="card">
      <div class="card__head">Overall Standings</div>
      <div class="card__body">
        <table class="table w-full" id="overall-tbl"></table>
      </div>
    </section>

    <section class="card mt-24">
      <div class="card__head">Week <?= (int)$WEEK_TO_SHOW ?> Standings</div>
      <div class="card__body">
        <table class="table w-full" id="weekly-tbl"></table>
      </div>
    </section>
  </main>

<script src="<?= url('assets/linechart.js') ?>"></script> 
  <script>
    const overallStandings = <?= json_encode($overallStandings) ?>;
    const weeklyStandings = <?= json_encode($weeklyStandings) ?>;
    const picksByUserId = <?= json_encode($picksByUserId) ?>;



    function pickOutcomeATS(pick) { return pick.outcome || 'pending'; }

    function formatSpread(pickedTeam, favTeam, spread) {
        const s = Number(spread || 0);
        if (Object.is(s, -0) || s === 0) return '0';
        const mag = Math.abs(s).toFixed(1);
        return pickedTeam === favTeam ? `-${mag}` : `+${mag}`;
    }

    function renderOverallStandings() {
        const tbl = document.getElementById('overall-tbl');
        if (!Array.isArray(overallStandings) || !overallStandings.length) {
            tbl.innerHTML = '<tr class="tr"><td class="td">No standings yet.</td></tr>';
            return;
        }
        let html = '<tr class="tr"><th class="th rank">#</th><th class="th">Player</th><th class="th">W-L-P</th><th class="th">Pts</th></tr>';
        overallStandings.forEach((r, i) => {
            const trClass = i === 0 ? 'tr tr--leader' : 'tr';
            html += `<tr class="${trClass}"><td class="td rank">${i + 1}</td><td class="td"><a href="#" class="accordion-toggle" data-user-id="${r.user_id}">${r.name}</a></td><td class="td">${r.total_wins}-${r.total_losses}-${r.total_pushes}</td><td class="td"><strong>${Number(r.total_points).toFixed(1)}</strong></td></tr>`;
        });
        tbl.innerHTML = html;
        // The original code had redundant stamping logic, which is removed here.
        // The new, simpler JS below will handle the click listeners.
    }

    function renderWeeklyStandings() {
        const tbl = document.getElementById('weekly-tbl');
        if (!Array.isArray(weeklyStandings) || !weeklyStandings.length) {
            tbl.innerHTML = '<tr class="tr"><td class="td">No standings yet.</td></tr>';
            return;
        }

        let html = '<tr class="tr"><th class="th rank">#</th><th class="th">Player</th><th class="th">W-L-P</th><th class="th">Pts</th></tr>';
        weeklyStandings.forEach((r, i) => {
            const trClass = i === 0 ? 'tr tr--leader' : 'tr';
            html += `<tr class="${trClass}"><td class="td rank">${i + 1}</td><td class="td"><a href="#" class="accordion-toggle" data-user-id="${r.user_id}">${r.name}</a></td><td class="td">${r.wins}-${r.losses}-${r.pushes}</td><td class="td"><strong>${Number(r.points).toFixed(1)}</strong></td></tr>`;
            
            const userPicks = picksByUserId[r.user_id] || [];
            let picksHtml = '<table class="table w-full">';
            picksHtml += '<tr class="tr"><th class="th">Pick/Line</th><th class="th">Game</th><th class="th">Score</th><th class="th">Result</th></tr>';
            userPicks.forEach(p => {
                const outcome = pickOutcomeATS(p);
                let outcomeClass = '';
                let outcomeText = '';
                if (outcome === 'win') { outcomeClass = 'result-win'; outcomeText = 'WIN'; }
                else if (outcome === 'loss') { outcomeClass = 'result-loss'; outcomeText = 'LOSS'; }
                else if (outcome === 'push') { outcomeClass = 'result-push'; outcomeText = 'PUSH'; }
                else { outcomeClass = ''; outcomeText = 'PENDING'; }

                picksHtml += `
                    <tr>
                        <td class="td"><strong>${p.picked_team}</strong> (${p.spread_display})</td>
                        <td class="td">${p.home_team} vs ${p.away_team}</td>
                        <td class="td">${p.home_score}-${p.away_score}</td>
                        <td class="td"><span class="pick-result ${outcomeClass}">${outcomeText}</span></td>
                    </tr>
                `;
            });
            picksHtml += '</table>';
            
            html += `<tr class="accordion-content" data-accordion-for="${r.user_id}"><td colspan="4" class="td">${picksHtml}</td></tr>`;
        });
        tbl.innerHTML = html;

        document.querySelectorAll('.accordion-toggle').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const userId = el.dataset.userId;
                const accordionContent = document.querySelector(`[data-accordion-for="${userId}"]`);
                if(accordionContent) {
                  accordionContent.classList.toggle('open');
                }
            });
        });
    }

    // Call the rendering functions here
    renderOverallStandings();
    renderWeeklyStandings();
  </script>

<script>
// --- Safe add-on for top table accordion & chart (non-destructive) ---
(function(){
  function stampOverallUserIds(){
    try {
      const tbl = document.getElementById('overall-tbl');
      if (!tbl) return;
      const rows = Array.from(tbl.querySelectorAll('tr'));
      if (!rows.length) return;
      // assume header in first row
      for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.children || [];
        const playerCell = cells[1]; // rank, Player, W-L-P, Pts
        if (!playerCell) continue;
        // Prefer data from rendered attributes if present
        if (!playerCell.getAttribute('data-user-id')) {
          // Try to derive from a link in the cell
          const a = playerCell.querySelector('a');
          let uid = a && a.getAttribute('data-user-id');
          if (!uid && a && a.href) {
            const href = a.href;
            let m = href.match(/[?&](?:user_id|id)=(\d+)/i) ||
                    href.match(/\/users?\/(\d+)/i) ||
                    href.match(/(\d+)(?:\/)?$/);
            if (m) uid = m[1];
          }
          // As a fallback, try to map from a global data array if present
          if (!uid && typeof overallStandings !== 'undefined' && overallStandings[i-1]) {
            uid = overallStandings[i-1].user_id || overallStandings[i-1].id;
          }
          if (uid) {
            playerCell.setAttribute('data-user-id', uid);
            playerCell.classList.add('user-toggle');
            playerCell.style.cursor = 'pointer';
          }
        }
      }
    } catch (e) { /* swallow */ }
  }

  function bindOverallChartAccordions(){
    try {
      const tbl = document.getElementById('overall-tbl');
      if (!tbl) return;
      const targets = Array.from(tbl.querySelectorAll('td[data-user-id]'));
      targets.forEach(el => {
        if (el.__chartBound) return;
        el.__chartBound = true;
        el.addEventListener('click', function(e){
          e.preventDefault();
          const userId = parseInt(el.getAttribute('data-user-id'), 10);
          const row = el.closest('tr');
          if (!row || !userId) return;
          const accRowClass = 'chart-accordion-row';
          let accRow = row.nextElementSibling;
          if (!accRow || !accRow.classList.contains(accRowClass)) {
            accRow = document.createElement('tr');
            accRow.className = accRowClass;
            const cell = document.createElement('td');
            cell.colSpan = row.children.length || 1;
            const content = document.createElement('div');
            content.className = 'chart-accordion-content'; // Unique class here
            content.setAttribute('data-accordion-for', String(userId));
            const container = document.createElement('div');
            container.className = 'chart-container';
            container.id = 'chart-user-' + userId;
            content.appendChild(container);
            cell.appendChild(content);
            accRow.appendChild(cell);
            row.parentNode && row.parentNode.insertBefore(accRow, row.nextSibling);
            // After creating the row, add the 'open' class to it
            accRow.classList.add('open');
            // load chart
            fetch('/api/api_user_points.php?user_id=' + userId, { credentials: 'same-origin' })
              .then(r => r.json())
              .then(data => {
                if (data && Array.isArray(data.values)) {
                  drawSimpleLineChart(container, { labels: data.labels || [], values: data.values || [] }, { height: 240 });
                  observeResize(container, function(){
                    drawSimpleLineChart(container, { labels: data.labels || [], values: data.values || [] }, { height: 240 });
                  });
                } else {
                  container.textContent = 'No data available.';
                }
              })
              .catch(function(){ container.textContent = 'Error loading chart.'; });
          } else {
            // If the row already exists, just toggle the 'open' class
            accRow.classList.toggle('open');
          }
        });
      });
    } catch (e) { /* swallow */ }
  }

  function init(){
    stampOverallUserIds();
    bindOverallChartAccordions();
  }

  // Run now and on mutations to the overall table (to handle re-renders)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  const tbl = document.getElementById('overall-tbl');
  if (tbl) {
    const mo = new MutationObserver(function(){
      stampOverallUserIds();
      bindOverallChartAccordions();
    });
    mo.observe(tbl, { childList: true, subtree: true });
  }
})();
</script>

</body>
</html>