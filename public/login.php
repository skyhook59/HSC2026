<?php
require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/csrf.php';
require __DIR__ . '/../private/inc/rate_limit.php';

// CSRF protection
if (!csrf_verify()) {
    redirect('index.php?err=Invalid+request');
}

// Rate limiting: 5 login attempts per 5 minutes per IP
$clientIp = get_client_ip();
rate_limit($db, 'login_' . $clientIp, 5, 300);

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';
if (!$email || !$pass) { redirect('index.php?err=Missing+fields'); }
$stmt = $db->prepare("SELECT id, name, password_hash, is_admin FROM users WHERE email=?");
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user || !password_verify($pass, $user['password_hash'])) {
  redirect('index.php?err=Invalid+login');
}

// Successful login - reset rate limit
rate_limit_reset($db, 'login_' . $clientIp);

$_SESSION['user_id'] = $user['id'];
$_SESSION['name'] = $user['name'];
$_SESSION['is_admin'] = (int)$user['is_admin'];
redirect('menu.php');
