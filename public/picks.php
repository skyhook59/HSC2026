<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/week.php';
auth_required();

// Make the current_user variable available in the global scope
global $current_user;

[$AUTO_SEASON, $AUTO_WEEK, $status] = current_season_week($db);

// Get the current user ID, checking if the current_user variable is not null
$userId = $current_user ? $current_user['id'] : null;

// Check if the user has already submitted picks for the current week
$hasSubmitted = false;
if ($userId !== null) {
  $stmt = $db->prepare("SELECT COUNT(*) FROM picks WHERE season_year = ? AND week_number = ? AND user_id = ?");
  $stmt->execute([$AUTO_SEASON, $AUTO_WEEK, $userId]);
  $hasSubmitted = $stmt->fetchColumn() > 0;
}

// This flag determines if the form should be shown. It's false if they've submitted.
$canSubmit = !$hasSubmitted;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Enter Picks — HSC</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    html, body { overflow-x: hidden; }
    .header__bar { flex-wrap: wrap; }
    .header__center { min-width: 0; flex: 1 1 auto; justify-content: center; }
    .brand, .header__right { flex: 0 0 auto; }

    @media (max-width: 420px){
      .icon-btn { width:28px; height:28px; }
      .week-switch { font-size: 14px; }
      .badge { padding: 3px 7px; }
      .container { padding: 12px; }
    }

    /* layout */
    .matchup { display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:nowrap; }
    .team-chip { min-width: clamp(96px, 32vw, 140px); padding: 8px 10px; }
    .spread-pill { min-width: clamp(64px, 20vw, 90px); padding: 8px 12px; font-size: clamp(14px, 4vw, 18px); }
    #cards { display:grid; gap:16px; grid-template-columns:1fr; }
    .note { color: var(--muted); font-size: 14px; margin-top: 4px; }
    .picks-container { display: flex; flex-direction: column; }
    .picks-form { display: block; }
    .picks-message { text-align: center; font-size: 1.25rem; font-weight: 600; color: var(--text-color); margin-top: 2rem; }
    .picks-submessage { text-align: center; color: var(--muted); font-size: 0.875rem; }
    .hidden { display: none; }
  </style>
</head>
<body class="app">
  <header class="header">
    <div class="container header__bar">
      <div class="brand"><div class="brand__logo"></div> HSC</div>
      <div class="header__center">
        <button class="icon-btn" id="week-prev">‹</button>
        <div class="week-switch"><span id="week-label">Week</span></div>
        <button class="icon-btn" id="week-next">›</button>
      </div>
      <div class="header__right"><a href="<?= url('menu.php') ?>" class="badge">Menu</a></div>
    </div>
  </header>

  <main class="container mt-24">
    <div class="grid grid-2">
      <section class="card">
        <div class="card__head">Enter Picks</div>
        <div class="card__body picks-container">
          <div id="picks-form" class="picks-form">
            <div id="cards" class="grid"></div>
            <div class="space-between mt-24">
              <div class="row"><strong id="count">0</strong>/5 selected</div>
              <button id="submit" class="btn btn--primary" disabled>Submit All 5</button>
            </div>
            <p id="error" class="error mt-8 hidden"></p>
          </div>
          <div id="picks-message" class="hidden">
            <p class="picks-message">You have already submitted your picks for this week.</p>
            <p class="picks-submessage">Picks can only be changed via the admin page.</p>
          </div>
        </div>
      </section>

      <aside class="card slip">
        <div class="slip__title">Your Card <span class="count-pill" id="slip-count">0/5</span></div>
        <div class="slip__list" id="slip"></div>
        <div class="card__foot">Deadline: <strong>Sat 11:59pm PT</strong></div>
      </aside>
    </div>
  </main>

  <script>
    const season = <?= (int)$AUTO_SEASON ?>;
    const weekParam = new URLSearchParams(location.search).get('week');
    let week = parseInt(weekParam || '<?= (int)$AUTO_WEEK ?>', 10);
    document.getElementById('week-label').textContent = `Week ${week}`;
    document.getElementById('week-prev').onclick = () => { if (week>1){ week--; location.search = `?week=${week}`; } };
    document.getElementById('week-next').onclick = () => { week++; location.search = `?week=${week}`; };
    
    // Add logic to hide the submit button if the week is locked
    const status = '<?= $status ?>';
    const canSubmit = <?= json_encode($canSubmit) ?>;
    
    if (!canSubmit) {
      document.getElementById('picks-form').classList.add('hidden');
      document.getElementById('picks-message').classList.remove('hidden');
    }

    const LOGO_PATH = '/assets/logos';
    const selections = new Map();
    const linesByGame = new Map();
    const slip = document.getElementById('slip');
    const errEl = document.getElementById('error');
    const now = new Date(); // Current time

    function showError(msg){ errEl.classList.remove('hidden'); errEl.textContent = msg; setTimeout(()=>errEl.classList.add('hidden'), 6000); }

    function renderSlip(){
      slip.innerHTML = '';
      for (const [gid, team] of selections.entries()){
        const el = document.createElement('div');
        el.className = 'slip__item';
        el.innerHTML = `<div class="row"><img src="${LOGO_PATH}/${team}.png" onerror="this.style.display='none'" width="20" height="20" alt=""><span class="slip__abbr">${team}</span></div><button class="slip__remove" data-gid="${gid}">✕</button>`;
        slip.appendChild(el);
      }
      document.getElementById('slip-count').textContent = `${selections.size}/5`;
      document.getElementById('count').textContent = selections.size;
      // Also check if the week is locked before enabling the submit button
      document.getElementById('submit').disabled = selections.size !== 5 || status === 'postlock';
      slip.querySelectorAll('.slip__remove').forEach(b => b.onclick = () => { selections.delete(Number(b.dataset.gid)); renderSlip(); paintSelections(); });
    }

    function teamButton(gameId, team) {
      const btn = document.createElement('button');
      btn.className = 'team-chip';
      btn.dataset.gameId = gameId; btn.dataset.team = team;
      btn.innerHTML = `<img src="${LOGO_PATH}/${team}.png" onerror="this.style.display='none'" alt=""><span class="team-chip__abbr">${team}</span>`;
      btn.onclick = () => togglePick(gameId, team);
      return btn;
    }

    function togglePick(gameId, team) {
      const already = selections.get(gameId);
      if (already === team) selections.delete(gameId);
      else {
        if (!already && selections.size === 5) return;
        selections.set(gameId, team);
      }
      renderSlip(); paintSelections();
    }

    function paintSelections(){
      document.querySelectorAll('.team-chip').forEach(el=>{
        const picked = selections.get(Number(el.dataset.gameId)) === el.dataset.team;
        el.classList.toggle('team-chip--selected', picked);
      });
    }

    function formatKickoff(utcStr){
      const d = new Date(utcStr.replace(' ','T')+'Z');
      const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sept','Oct','Nov','Dec'];
      const day = days[d.getDay()];
      const mon = months[d.getMonth()];
      const date = d.getDate();
      let h = d.getHours(), m = d.getMinutes();
      const ampm = h >= 12 ? 'pm' : 'am';
      h = h % 12; if (h === 0) h = 12;
      const mm = String(m).padStart(2,'0');
      return `${day} ${mon} ${date} ${h}:${mm}${ampm}`;
    }

    function displayFavSpread(spread){
      const s = Number(spread || 0);
      if (Object.is(s, -0) || s === 0) return '0'; // PK
      return '-' + Math.abs(s).toFixed(1);
    }

    async function loadLines(){
      const res = await fetch(`/api/lines.php?season=${season}&week=${week}`);
      const lines = await res.json();
      const container = document.getElementById('cards'); 
      container.innerHTML = '';
      let hasGames = false;

      lines.forEach(row => {
        const gameKickoff = new Date(row.kickoff_utc.replace(' ','T')+'Z');
        
        // Only show games that have not started yet
        if (now < gameKickoff) {
            hasGames = true;
            linesByGame.set(Number(row.game_id), row);

            // Determine favorite and underdog order
            const fav = row.fav_team;
            const dog = (fav === row.home_team) ? row.away_team : row.home_team;

            // Spread always negative for favorite (0 for PK)
            const spreadEl = document.createElement('div'); spreadEl.className='spread-pill';
            spreadEl.textContent = displayFavSpread(row.spread);

            const card = document.createElement('div'); card.className = 'card card--flat';
            const inner = document.createElement('div'); inner.className='matchup';

            const left  = teamButton(row.game_id, fav);
            const right = teamButton(row.game_id, dog);

            inner.append(left, spreadEl, right);

            const meta = document.createElement('div'); meta.className='card__foot meta';
            meta.innerHTML = `<span>${formatKickoff(row.kickoff_utc)}</span><span>Home: ${row.home_team}</span>`;

            card.append(inner, meta);
            container.append(card);
        }
      });
      
      if (!hasGames) {
          container.innerHTML = '<p class="text-center">All games for this week have started.</p>';
          document.getElementById('submit').disabled = true;
          document.getElementById('submit').textContent = 'Submissions Closed';
      }
    }

    document.getElementById('submit').onclick = async () => {
      // Build array of team codes only (server expects {teams: [...]})
      const teams = [];
      for (const [gid, team] of selections.entries()){
        const line = linesByGame.get(Number(gid));
        if (!line){ return showError('Game data missing. Refresh and try again.'); }
        const t = String(team || '').toUpperCase();
        // Validate picked team is part of the game (defensive)
        if (t !== line.home_team && t !== line.away_team){
          return showError(`Invalid selection for game ${gid}.`);
        }
        teams.push(t);
      }
      if (teams.length !== 5){ return showError('You must select exactly 5 picks.'); }

      try{
       const res = await fetch('/api/submit_picks.php', {
		  method: 'POST',
		  headers: {'Content-Type':'application/json'},
		  credentials: 'same-origin',
		  body: JSON.stringify({
		    season,
		    week,
 		    teams,
		    echo: false   // <— use this instead of ?echo=1
 	        })
        });
        const txt = await res.text();
        let data = null; try{ data = JSON.parse(txt); }catch(_){}
        if (!res.ok){
          console.error('submit error', res.status, txt);
          const reasons = (data && data.result && data.result.errors) ? data.result.errors : [];
          showError((reasons.length ? reasons.join(', ') : (data && data.error)) || `Submit failed (${res.status})`);
          return;
        }
       location.href = `/week_picks.php?season=${season}&week=${week}`;
      }catch(e){
        console.error(e);
        showError('Network error submitting picks.');
      }
    };

    loadLines();
  </script>
</body>
</html>
