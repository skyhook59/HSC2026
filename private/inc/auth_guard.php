<?php
function auth_required() {
  if (empty($_SESSION['user_id'])) {
    redirect('index.php');
  }
}

function admin_required() {
  if (empty($_SESSION['user_id'])) {
    redirect('index.php');
  }
  if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin access required']);
    exit;
  }
}
