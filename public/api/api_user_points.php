<?php
// api_user_points.php
// Returns JSON: { labels: ["W1","W2",...], values: [points...] }
require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/auth_guard.php';
require __DIR__ . '/../../private/inc/week.php';
auth_required();

header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$season  = isset($_GET['season']) ? intval($_GET['season']) : null; // kept as 'season' query param for compatibility (maps to season_year)

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid user_id']);
    exit;
}

try {
    // Try to infer current season if not provided
    if ($season === null) {
        [$AUTO_SEASON, $AUTO_WEEK, $status] = current_season_week($db);
        $season = $AUTO_SEASON;
    }

    // Correct schema:
    // results(user_id BIGINT, season_year INT, week_number INT, points DECIMAL)
    $stmt = $db->prepare("
        SELECT week_number, points
        FROM results
        WHERE user_id = :uid AND season_year = :season_year
        ORDER BY week_number ASC
    ");
    $stmt->execute([':uid' => $user_id, ':season_year' => $season]);

    $labels = [];
    $values = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $labels[] = 'W' . (int)$row['week_number'];
        $values[] = (float)$row['points'];
    }
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
