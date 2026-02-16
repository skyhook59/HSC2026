<?php
require_once __DIR__ . '/ats.php';
/**
 * Scoring helpers for ATS (against the spread)
 * Requires: db.php already included to provide $db (PDO)
 */

function score_week(PDO $db, int $season, int $week): array {
    $sqlLines = "
      SELECT l.game_id, l.fav_team, l.dog_team, l.spread,
             g.home_team, g.away_team, g.home_score, g.away_score, g.state
      FROM `lines` l
      JOIN games g ON g.id = l.game_id
      WHERE g.season_year = ? AND g.week_number = ?";
    $stmt = $db->prepare($sqlLines);
    $stmt->execute([$season, $week]);
    $linesByGameId = [];
    foreach ($stmt->fetchAll() as $r) {
        $linesByGameId[(int)$r['game_id']] = $r;
    }

    $sqlPicks = "
      SELECT p.user_id, ps.game_id, ps.team_abbr AS picked_team
      FROM picks p
      INNER JOIN pick_selections ps ON p.id = ps.pick_id
      WHERE p.season_year = ? AND p.week_number = ?";
    $ps = $db->prepare($sqlPicks);
    $ps->execute([$season, $week]);
    $picksByUser = [];
    foreach ($ps->fetchAll() as $p) {
        $picksByUser[(int)$p['user_id']][] = $p;
    }

    $winsTotal = $lossesTotal = $pushesTotal = 0;

    foreach ($picksByUser as $userId => $userPicks) {
        $wins = $losses = $pushes = 0;

        foreach ($userPicks as $pick) {
            $gid = (int)$pick['game_id'];
            $line = $linesByGameId[$gid] ?? null;
            if (!$line) continue;

            $game = [
                'home_team'  => $line['home_team'],
                'away_team'  => $line['away_team'],
                'home_score' => $line['home_score'],
                'away_score' => $line['away_score'],
                'state'      => $line['state'],
            ];

            $outcome = ats_outcome($pick, $line, $game);
            if ($outcome === 'win')   $wins++;
            if ($outcome === 'loss')  $losses++;
            if ($outcome === 'push')  $pushes++;
        }

        $points = $wins * 1.0 + $pushes * 0.5;

        $ins = $db->prepare("
          INSERT INTO results (season_year, week_number, user_id, wins, losses, pushes, points)
          VALUES (?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE wins=VALUES(wins), losses=VALUES(losses), pushes=VALUES(pushes), points=VALUES(points)
        ");
        $ins->execute([$season, $week, $userId, $wins, $losses, $pushes, $points]);

        $winsTotal   += $wins;
        $lossesTotal += $losses;
        $pushesTotal += $pushes;
    }

    $sum = $db->prepare("
      SELECT user_id,
             COALESCE(SUM(wins),0)   AS w,
             COALESCE(SUM(losses),0) AS l,
             COALESCE(SUM(pushes),0) AS p,
             COALESCE(SUM(points),0) AS pts
      FROM results
      WHERE season_year = ?
      GROUP BY user_id
    ");
    $sum->execute([$season]);
    $rows = $sum->fetchAll();

    foreach ($rows as $r) {
        $upd = $db->prepare("
          INSERT INTO standings (season_year, user_id, total_wins, total_losses, total_pushes, total_points, last_updated_utc)
          VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
          ON DUPLICATE KEY UPDATE
            total_wins=VALUES(total_wins),
            total_losses=VALUES(total_losses),
            total_pushes=VALUES(total_pushes),
            total_points=VALUES(total_points),
            last_updated_utc=VALUES(last_updated_utc)
        ");
        $upd->execute([$season, (int)$r['user_id'], (int)$r['w'], (int)$r['l'], (int)$r['p'], (float)$r['pts']]);
    }

    return [
        'users_scored' => count($picksByUser),
        'wins' => $winsTotal,
        'losses' => $lossesTotal,
        'pushes' => $pushesTotal,
    ];
}