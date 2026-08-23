<?php
// Include necessary files to get the week info
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/week.php';
auth_required();
[$AUTO_SEASON, $AUTO_WEEK] = current_season_week($db);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Entries — HSC</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/styles/gridiron.css') ?>" />
  <style>
    .submission-list {
        list-style-type: none;
        padding: 0;
        margin-top: 16px;
    }
    .submission-list li {
        padding: 8px 12px;
        background-color: var(--card-bg);
        border-bottom: 1px solid var(--line);
    }
    .submission-list li:last-child {
        border-bottom: none;
    }
    .submission-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 16px;
        padding: 12px 16px;
        background-color: var(--primary);
        color: var(--on-primary);
        border-radius: 8px;
        font-weight: 600;
        text-align: center;
    }
    .submission-stats__item {
        flex: 1;
        padding: 0 8px;
    }
    .submission-stats__label {
        font-size: 12px;
        opacity: 0.8;
    }
    .submission-stats__value {
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
    }
  </style>
</head>
<body class="app">
  <header class="header">
    <div class="container header__bar">
      <div class="brand"><div class="brand__logo"></div> HSC</div>
      <div class="header__center"></div>
      <div class="header__right"></div>
    </div>
  </header>

  <main class="container mt-24">
    <section class="card">
      <div class="card__head">Picks Entries: Week <?= (int)$AUTO_WEEK ?></div>
      <div class="card__body">
        <div class="submission-stats">
          <div class="submission-stats__item">
            <div class="submission-stats__value" id="submitted-count"></div>
            <div class="submission-stats__label">Submitted</div>
          </div>
          <div class="submission-stats__item">
            <div class="submission-stats__value" id="total-count"></div>
            <div class="submission-stats__label">Total Users</div>
          </div>
        </div>
        <h3 class="mt-24">Who Hasn't Submitted:</h3>
        <ul class="submission-list" id="not-submitted-list"></ul>
        <p id="no-pending" class="hidden text-center mt-16">Everyone has submitted!</p>
      </div>
    </section>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      try {
        const response = await fetch('/api/entries.php');
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        // Update counts
        document.getElementById('submitted-count').textContent = data.submitted_count;
        document.getElementById('total-count').textContent = data.total_users;

        // Render the "Not Submitted" list
        const list = document.getElementById('not-submitted-list');
        if (data.not_submitted.length > 0) {
          data.not_submitted.forEach(name => {
            const li = document.createElement('li');
            li.textContent = name;
            list.appendChild(li);
          });
        } else {
          // Show "Everyone has submitted!" message
          document.getElementById('no-pending').classList.remove('hidden');
        }

      } catch (e) {
        console.error('Error fetching data:', e);
        document.getElementById('not-submitted-list').innerHTML = `<li>Error loading data. Please try again.</li>`;
      }
    });
  </script>
</body>
</html>
