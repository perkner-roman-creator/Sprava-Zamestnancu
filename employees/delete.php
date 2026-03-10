<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
requireRole([1, 2]); // Jen Admin (1) a Manager (2) mohou mazat

$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST';
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

function respondDelete($success, $message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }

    setFlash($success ? 'success' : 'error', $message);
    redirect('list.php');
}

if ($id > 0) {
    try {
        // Kontrola existence zaměstnance
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            // Smazání zaměstnance
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$id]);

            logAction($pdo, 'DELETE', 'employee', $id, $employee, []);
            respondDelete(true, "Zamestnanec {$employee['first_name']} {$employee['last_name']} byl uspesne smazan", $isAjax);
        } else {
            respondDelete(false, 'Zamestnanec nenalezen', $isAjax);
        }
    } catch (PDOException $e) {
        respondDelete(false, 'Chyba pri mazani: ' . $e->getMessage(), $isAjax);
    }
} else {
    respondDelete(false, 'Neplatne ID zamestnance', $isAjax);
}
