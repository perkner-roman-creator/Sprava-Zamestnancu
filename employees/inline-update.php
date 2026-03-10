<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
requireRole([1, 2]); // Pouze Admin a Manager

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda není povolena']);
    exit;
}

$id = $_POST['id'] ?? 0;
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

// Povolené fieldy pro inline editing
$allowedFields = ['salary', 'department', 'status'];

if (!in_array($field, $allowedFields, true)) {
    echo json_encode(['success' => false, 'message' => 'Nepovolené pole pro editaci']);
    exit;
}

// Načtení současného zaměstnance
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    echo json_encode(['success' => false, 'message' => 'Zaměstnanec nenalezen']);
    exit;
}

// Validace podle typu pole
$errors = [];

if ($field === 'salary') {
    if (!isValidSalary($value)) {
        $errors[] = 'Neplatný formát platu';
    }
    if ($value < 0) {
        $errors[] = 'Plat nemůže být záporný';
    }
}

if ($field === 'department') {
    $allowedDepartments = array_keys(getDepartments());
    if (!in_array($value, $allowedDepartments, true)) {
        $errors[] = 'Neplatné oddělení';
    }
}

if ($field === 'status') {
    $allowedStatuses = array_keys(getStatuses());
    if (!in_array($value, $allowedStatuses, true)) {
        $errors[] = 'Neplatný status';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Uložení změny
try {
    $updateStmt = $pdo->prepare("UPDATE employees SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$value, $id]);
    
    // Log změny
    $oldValues = [$field => $employee[$field]];
    $newValues = [$field => $value];
    logAction($pdo, 'UPDATE', 'employee', $id, $oldValues, $newValues);
    
    // Formátování hodnoty pro odpověď
    $displayValue = $value;
    if ($field === 'salary') {
        $displayValue = formatSalary($value);
    } elseif ($field === 'department') {
        $departments = getDepartments();
        $displayValue = $departments[$value] ?? $value;
    } elseif ($field === 'status') {
        $statuses = getStatuses();
        $displayValue = $statuses[$value] ?? $value;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Úspěšně uloženo',
        'value' => $value,
        'display_value' => $displayValue
    ]);
    
} catch (PDOException $e) {
    error_log('Inline update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při ukládání do databáze']);
}
