<?php
declare(strict_types=1);

/**
 * Helpers for determining whether a given season/week is locked.
 * Lock is based solely on weeks.lock_at_utc (UTC timestamp).
 */

/**
 * Get lock status for a given season/week.
 *
 * @return array{
 *   locked: bool,
 *   lock_at_utc: ?DateTimeImmutable,
 *   now_utc: DateTimeImmutable
 * }
 */
function week_lock_status(PDO $db, int $season, int $week): array
{
    $stmt = $db->prepare("
        SELECT lock_at_utc
        FROM weeks
        WHERE season_year = ?
          AND week_number = ?
        LIMIT 1
    ");
    $stmt->execute([$season, $week]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if (!$row || empty($row['lock_at_utc'])) {
        // No lock configured: treat as unlocked
        return [
            'locked'      => false,
            'lock_at_utc' => null,
            'now_utc'     => $nowUtc,
        ];
    }

    $lockAtUtc = new DateTimeImmutable($row['lock_at_utc'], new DateTimeZone('UTC'));
    $locked    = ($nowUtc >= $lockAtUtc);

    return [
        'locked'      => $locked,
        'lock_at_utc' => $lockAtUtc,
        'now_utc'     => $nowUtc,
    ];
}

/**
 * Convenience wrapper: is this week locked?
 */
function is_week_locked(PDO $db, int $season, int $week): bool
{
    $s = week_lock_status($db, $season, $week);
    return $s['locked'];
}