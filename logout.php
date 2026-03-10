<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Logování odhlášení
if (isset($_SESSION['user_id'])) {
    try {
        logAction($pdo, 'LOGOUT', 'user', $_SESSION['user_id']);
    } catch (Exception $e) {
        // Ignorovat chyby logování
    }
}

// Zničení session
$_SESSION = [];
session_destroy();

// Přesměrování na login
header('Location: login.php?logout=1');
exit;

