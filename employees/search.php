<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
requireRole([1, 2]); // Admin a Manager

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, department, salary
        FROM employees
        WHERE 
            first_name LIKE ? OR
            last_name LIKE ? OR
            email LIKE ? OR
            department LIKE ?
        ORDER BY first_name, last_name
        LIMIT 20
    ");
    
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll();
    
    echo json_encode(['results' => $results]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
