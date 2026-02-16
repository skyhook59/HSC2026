<?php
/**
 * Core ESPN score update logic
 * Extracted from public/api/admin/update_scores.php to avoid circular dependencies
 * Requires: $db (PDO) to be available
 */

/**
 * Map ESPN team abbreviations to HSC standard abbreviations
 */
function map_abbr(string $abbr): string {
  $a = strtoupper(trim($abbr));
  static $map = ['WSH'=>'WAS','JAC'=>'JAX','LA'=>'LAR'];
  return $map[$a] ?? $a;
}

/**
 * Map ESPN game state to HSC ENUM values ('pre', 'in_progress', 'final')
 */
function map_state(?string $espn): string {
  $s = strtolower(trim((string)$espn));
  if (in_array($s, ['post','postgame','final','ended','complete','completed'], true)) return 'final';
  if (in_array($s, ['in','inprogress','in_progress','live','playing'], true)) return 'in_progress';
  return 'pre';
}

/**
 * Fetch JSON from URL using cURL or file_get_contents
 */
function fetch_json_any(string $url): array {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_USERAGENT => 'HSC-ScoreUpdate/1.0',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code >= 400 || !$body) {
      throw new RuntimeException("fetch_failed code=$code err=$err url=$url");
    }
  } else {
    $ctx = stream_context_create(['http'=>[
      'method'=>'GET','timeout'=>25,'header'=>"User-Agent: HSC-ScoreUpdate/1.0\r\n"
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
      throw new RuntimeException("fetch_failed (no_curl) url=$url");
    }
  }
  $data = json_decode($body, true);
  if (!is_array($data)) throw new RuntimeException("bad_json url=$url");
  return $data;
}

/**
 * Update scores for a single week from ESPN API
 *
 * @param PDO $db Database connection
 * @param int $season Year (e.g., 2025)
 * @param int $week Week number (1-18)
 * @param int $seasontype ESPN season type (1=preseason, 2=regular, 3=postseason)
 * @param bool $createIfMissing Create game records if they don't exist
 * @return array ['updated'=>int, 'inserted'=>int, 'skipped'=>int, 'events'=>int]
 */
function update_week_scores(PDO $db, int $season, int $week, int $seasontype, bool $createIfMissing): array {
  $base = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard";
  $url  = $base . "?seasontype={$seasontype}&week={$week}&dates={$season}";
  $data = fetch_json_any($url);

  $events = $data['events'] ?? [];
  $upd=0; $ins=0; $skp=0;

  $sel = $db->prepare("SELECT id FROM games WHERE season_year=? AND week_number=? AND home_team=? AND away_team=?");

  $insStmt = $db->prepare("
    INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_utc, state, home_score, away_score, winner_team, period, clock_seconds, last_update_utc)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
  ");

  $updStmt = $db->prepare("
    UPDATE games SET
      kickoff_utc = COALESCE(?, kickoff_utc),
      state = ?,
      home_score = ?,
      away_score = ?,
      winner_team = ?,
      period = ?,
      clock_seconds = ?,
      last_update_utc = UTC_TIMESTAMP()
    WHERE id = ?
  ");

  foreach ($events as $ev) {
    $comp = $ev['competitions'][0] ?? null;
    if (!$comp) { $skp++; continue; }

    // teams, scores, winner
    $home = null; $away = null; $homeScore = null; $awayScore = null; $winner = null;
    foreach (($comp['competitors'] ?? []) as $c) {
      $abbr = map_abbr($c['team']['abbreviation'] ?? '');
      $ha   = strtolower((string)($c['homeAway'] ?? ''));
      $scr  = (isset($c['score']) && $c['score'] !== '') ? (int)$c['score'] : null;
      $win  = !empty($c['winner']);
      if ($ha === 'home') { $home = $abbr; $homeScore = $scr; if ($win) $winner = $abbr; }
      if ($ha === 'away') { $away = $abbr; $awayScore = $scr; if ($win) $winner = $abbr; }
    }
    if (!$home || !$away) { $skp++; continue; }

    // date → kickoff_utc
    $kickUtc = null;
    $dateIso = $comp['date'] ?? null;
    if (is_string($dateIso) && $dateIso !== '') {
      try {
        $dt = new DateTime($dateIso);
        $dt->setTimezone(new DateTimeZone('UTC'));
        $kickUtc = $dt->format('Y-m-d H:i:s');
      } catch (Throwable $e) { $kickUtc = null; }
    }

    // status
    $state  = map_state($comp['status']['type']['state'] ?? null); // 'pre'|'in_progress'|'final'
    $period = isset($comp['status']['period']) ? (int)$comp['status']['period'] : null;

    // ESPN clock can be "12:34" or "0:05.2" — keep only mm:ss
    $clockSecs = null;
    $clockRaw = $comp['status']['displayClock'] ?? null;
    if (is_string($clockRaw)) {
      $clockRaw = trim($clockRaw);
      if (strpos($clockRaw, ':') !== false) {
        $t = explode(':', $clockRaw, 2);
        $m = (int)preg_replace('~\D~', '', $t[0]);
        $s = (int)preg_replace('~\D~', '', $t[1]);
        $clockSecs = $m*60 + $s;
      }
    }

    // find game
    $sel->execute([$season,$week,$home,$away]);
    $id = (int)$sel->fetchColumn();

    if ($id > 0) {
      $updStmt->execute([$kickUtc, $state, $homeScore, $awayScore, $winner, $period, $clockSecs, $id]);
      $upd++;
    } elseif ($createIfMissing) {
      $insStmt->execute([$season,$week,$home,$away,$kickUtc,$state,$homeScore,$awayScore,$winner,$period,$clockSecs]);
      $ins++;
    } else {
      $skp++;
    }
  }

  return ['updated'=>$upd,'inserted'=>$ins,'skipped'=>$skp,'events'=>count($events)];
}
