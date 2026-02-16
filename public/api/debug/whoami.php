<?php
// /public/api/debug/whoami.php
ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

echo json_encode([
  'ok' => true,
  'session_status' => session_status(),
  'session_user_id' => $_SESSION['user_id'] ?? null,
  'is_admin' => $_SESSION['is_admin'] ?? null,
  'session_id' => session_id(),
  'cookies_present' => !empty($_COOKIE),
]);
