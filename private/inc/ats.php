<?php
/**
 * Shared ATS grading helpers (server-side only).
 * Centralizes outcome logic so all pages use the same formula.
 *
 * Favorite covers iff (favorite margin - spread) > 0
 * Push iff == 0
 * Else dog covers.
 */
function ats_outcome(array $pick, array $line, array $game): string {
    $state = strtolower($game['state'] ?? 'pre');
    if ($state !== 'final') return 'pending';

    $fav  = strtoupper($line['fav_team']);
    $dog  = strtoupper($line['dog_team']);
    $spr  = (float)$line['spread']; // positive number
    $home = strtoupper($game['home_team']);
    $away = strtoupper($game['away_team']);
    $hs   = (int)$game['home_score'];
    $as   = (int)$game['away_score'];
    $picked = strtoupper($pick['picked_team']);

    $favScore = ($fav === $home) ? $hs : $as;
    $dogScore = ($dog === $home) ? $hs : $as;

    // Correct ATS check: favorite covers if (favorite margin − spread) > 0
    $atsDiff = ($favScore - $dogScore) - $spr;

    if (abs($atsDiff) < 1e-9) return 'push';
    $covered = ($atsDiff > 0) ? $fav : $dog;
    return ($picked === $covered) ? 'win' : 'loss';
}

/**
 * Helper to format the spread from the perspective of the picked team,
 * for UI display like "CIN (-5.0)" or "CLE (+5.0)".
 */
function ats_format_spread_for_pick(string $pickedTeam, string $favTeam, float $spread): string {
    $pickedTeam = strtoupper($pickedTeam);
    $favTeam = strtoupper($favTeam);
    if ($spread == 0.0) return '0';
    $mag = number_format(abs($spread), 1);
    return ($pickedTeam === $favTeam) ? "-{$mag}" : "+{$mag}";
}
