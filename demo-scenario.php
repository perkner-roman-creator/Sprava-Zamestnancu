<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();
requireRole([1, 2]);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    $pdo->beginTransaction();

    $demoLogs = [
        ['IMPORT', 'employees', null, ['note' => 'Demo import CSV: 5 zaznamu']],
        ['UPDATE', 'employee', 3, ['field' => 'salary', 'from' => 42000, 'to' => 45000]],
        ['EXPORT', 'employees', null, ['format' => 'csv', 'count' => 12]],
        ['UPDATE', 'employee', 7, ['field' => 'status', 'from' => 'active', 'to' => 'vacation']],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO action_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($demoLogs as $item) {
        [$action, $entityType, $entityId, $newValues] = $item;
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            null,
            json_encode($newValues, JSON_UNESCAPED_UNICODE),
            $ipAddress,
        ]);
    }

    $pdo->commit();
    setFlash('success', 'Demo scenar byl spusten. Do audit logu byly pridany ukazkove akce.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', 'Demo scenar se nepodarilo spustit.');
}

redirect('index.php');
