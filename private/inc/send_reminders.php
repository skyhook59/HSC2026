<?php
// private/inc/send_reminders.php
// Include the database connection and the email helper function
require __DIR__ . '/db.php';
require __DIR__ . '/email.php';
require __DIR__ . '/week.php';

// Suppress output to prevent cron job emails
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Get the current week and season
[$AUTO_SEASON, $AUTO_WEEK, $status] = current_season_week($db);

// Only send reminders if the week is "prelock"
if ($status !== 'prelock') {
    die("Weekly picks are locked. No reminders will be sent.");
}

// 1. Get a list of all users and their email addresses
$allUsers = $db->query("SELECT id, email FROM users")->fetchAll(PDO::FETCH_ASSOC);
$unsubmittedUsers = [];

if ($allUsers) {
    // 2. Get a list of users who have already submitted their picks for the current week
    $stmt = $db->prepare("SELECT user_id FROM picks WHERE season_year = ? AND week_number = ?");
    $stmt->execute([$AUTO_SEASON, $AUTO_WEEK]);
    $submittedUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $submittedUserIds = array_map('intval', $submittedUserIds);

    // 3. Find the users who have not yet submitted
    foreach ($allUsers as $user) {
        if (!in_array((int)$user['id'], $submittedUserIds)) {
            $unsubmittedUsers[] = $user;
        }
    }
}

// 4. Send a reminder email to each unsubmitted user
foreach ($unsubmittedUsers as $user) {
    $to = $user['email'];
    $subject = "Reminder: Your picks for Week {$AUTO_WEEK} are due!";
    $body = "Hi,<br><br>This is a reminder that you have not yet submitted your picks for Week {$AUTO_WEEK} of the season. The deadline is Saturday at 11:59pm PT.<br><br>Don't miss out! <a href='https://hsc.mcph.ee'>Submit your picks here</a>.<br><br>Good luck with your picks!<br>Helga";

    send_email($to, $subject, $body);
}

echo "Reminders sent to " . count($unsubmittedUsers) . " people.\n";
