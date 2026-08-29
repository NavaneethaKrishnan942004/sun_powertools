<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin()
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin()
{
    requireLogin();

    if (
        empty($_SESSION['role']) ||
        strtolower($_SESSION['role']) !== 'admin'
    ) {
        http_response_code(403);
        exit('Access Denied');
    }
}