<?php
// Set the Content-Type header to JSON
header('Content-Type: application/json');

// Include necessary files
require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/auth_guard.php';
require __DIR__ . '/../../private/inc/week.php';
api_auth_required();

// Get the current season and week from the helper function
[$AUTO_SEASON, $AUTO_WEEK] = current_season_week($db);

// Get a list of all registered users
$sqlAllUsers = "
    SELECT id, name
    FROM users
    ORDER BY name ASC";
$stmtAllUsers = $db->prepare($sqlAllUsers);
$stmtAllUsers->execute();
$allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

// Get a list of users who have submitted picks for the current week
$sqlSubmittedPicks = "
    SELECT DISTINCT user_id
    FROM picks
    WHERE season_year = ? AND week_number = ?";
$stmtSubmittedPicks = $db->prepare($sqlSubmittedPicks);
$stmtSubmittedPicks->execute([$AUTO_SEASON, $AUTO_WEEK]);
$submittedPicks = $stmtSubmittedPicks->fetchAll(PDO::FETCH_COLUMN);

// Create a list of submitted user IDs for quick lookup
$submittedUserIds = array_flip($submittedPicks);
$notSubmittedUsers = [];

// Determine who has not submitted
foreach ($allUsers as $user) {
    if (!isset($submittedUserIds[$user['id']])) {
        $notSubmittedUsers[] = $user['name'];
    }
}

// Prepare the final response array
$response = [
    'season' => $AUTO_SEASON,
    'week' => $AUTO_WEEK,
    'total_users' => count($allUsers),
    'submitted_count' => count($submittedPicks),
    'not_submitted' => $notSubmittedUsers,
];

// Return the JSON response
echo json_encode($response);
?>
