<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
if ($user) {
    auditLog('logout', 'auth', $user['id'], 'خروج کاربر ' . $user['username']);
}
$_SESSION = [];
session_destroy();
redirect(BASE_URL . '/login.php');
