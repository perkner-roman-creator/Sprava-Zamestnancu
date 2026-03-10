<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

// Kontrola narozenin (zaměstnanci, kteří mají narozeniny dnes nebo v následujících 7 dnech)
$stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM employees
    WHERE strftime('%m-%d', hire_date) BETWEEN 
        strftime('%m-%d', 'now') AND 
        strftime('%m-%d', 'now', '+7 days')
    AND status = 'active'
");

$result = $stmt->fetch();

echo json_encode([
    'count' => (int) $result['count'],
    'message' => $result['count'] > 0 
        ? "Máte {$result['count']} výročí nástupu" 
        : 'Žádné nadcházející události'
]);
