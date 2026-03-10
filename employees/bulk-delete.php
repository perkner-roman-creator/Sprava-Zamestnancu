<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
requireRole([1, 2]); // Admin & Manager

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Nejsou vybrány žádné záznamy']);
    exit;
}

// Sanitize IDs
$ids = array_map('intval', $ids);
$placeholders = str_repeat('?,', count($ids) - 1) . '?';

try {
    $pdo->beginTransaction();
    
    // Smazat zaměstnance
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $deleted = $stmt->rowCount();
    
    // Logovat akci
    logAction($pdo, 'BULK_DELETE', 'employees', null, [], [
        'count' => $deleted,
        'ids' => $ids
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Úspěšně smazáno {$deleted} zaměstnanců"
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Chyba při mazání: ' . $e->getMessage()
    ]);
}
