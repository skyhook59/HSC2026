<?php
require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/auth_guard.php';
api_auth_required();

$season = (int)($_GET['season'] ?? date('Y'));
$stmt = $db->prepare("SELECT s.*, u.name FROM standings s JOIN users u ON u.id=s.user_id WHERE s.season_year=? ORDER BY s.total_points DESC, s.total_wins DESC, u.name");
$stmt->execute([$season]);
header('Content-Type: application/json'); echo json_encode($stmt->fetchAll());
