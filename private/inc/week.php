<?php
// /private/inc/week.php
function current_season_week(PDO $db): array {
  // Use the most recent season in `weeks`, or current year if empty
  $season = (int)($db->query("SELECT MAX(season_year) FROM weeks")->fetchColumn() ?: date('Y'));
  $stmt = $db->prepare("SELECT week_number, lock_at_utc FROM weeks WHERE season_year=? ORDER BY week_number ASC");
  $stmt->execute([$season]);
  $rows = $stmt->fetchAll();
  if (!$rows) {
    // No rows yet — fall back safely
    return [$season, 1, 'fallback'];
  }
  $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  foreach ($rows as $r) {
    $lock = new DateTimeImmutable($r['lock_at_utc'], new DateTimeZone('UTC'));
    if ($now < $lock) {
      return [$season, (int)$r['week_number'], 'prelock'];
    }
  }
  // All locks passed — show the final week in the table
  $last = end($rows);
  return [$season, (int)$last['week_number'], 'postlock'];
}


/**
 * Picks-visible week helper.
 *
 * Returns [season_year, week_number, 'postlock'|'prelock'] where:
 *  - 'postlock' means the returned week is already past its lock time (so picks should be visible).
 *  - 'prelock' means no week is locked yet (season start), so we fall back to the next upcoming week.
 *
 * This avoids the "off-by-one week" UX where Sunday morning defaults to NEXT week
 * (which is not locked yet) and thus only shows your own picks.
 */
function latest_locked_week(PDO $db): array {
  // Use the latest season in `weeks`, or current year if empty
  $season = (int)($db->query("SELECT MAX(season_year) FROM weeks")->fetchColumn() ?: date('Y'));

  $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  $nowStr = $nowUtc->format('Y-m-d H:i:s');

  // Find the most recent week whose lock time has passed
  $stmt = $db->prepare("
    SELECT week_number
    FROM weeks
    WHERE season_year = ? AND lock_at_utc <= ?
    ORDER BY week_number DESC
    LIMIT 1
  ");
  $stmt->execute([$season, $nowStr]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($row && isset($row['week_number'])) {
    return [$season, (int)$row['week_number'], 'postlock'];
  }

  // If nothing is locked yet, fall back to the next upcoming week
  return current_season_week($db); // will return [season, next_week, 'prelock']
}
