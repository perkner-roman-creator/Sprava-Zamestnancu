<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
requireRole([1, 2]);
// Column configuration
$availableColumns = [
    'first_name' => ['label' => 'Jméno', 'field' => 'first_name'],
    'last_name' => ['label' => 'Příjmení', 'field' => 'last_name'],
    'email' => ['label' => 'Email', 'field' => 'email'],
    'phone' => ['label' => 'Telefon', 'field' => 'phone'],
    'department' => ['label' => 'Oddělení', 'field' => 'department'],
    'salary' => ['label' => 'Plat (Kč)', 'field' => 'salary'],
    'hire_date' => ['label' => 'Nástup', 'field' => 'hire_date'],
    'status' => ['label' => 'Stav', 'field' => 'status'],
];

// Get selected columns or use all if not specified
$selectedColumns = $_GET['columns'] ?? array_keys($availableColumns);
if (!is_array($selectedColumns)) {
    $selectedColumns = [$selectedColumns];
}

// Filter to only valid columns
$selectedColumns = array_filter($selectedColumns, function($col) use ($availableColumns) {
    return isset($availableColumns[$col]);
});

// If no valid columns selected, use all
if (empty($selectedColumns)) {
    $selectedColumns = array_keys($availableColumns);
}


$format = $_GET['export'] ?? '';
if (!in_array($format, ['csv', 'pdf'], true)) {
    redirect('list.php');
}

$search = trim($_GET['search'] ?? '');
$department = trim($_GET['department'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = ' WHERE 1=1 ';
$params = [];

if ($search !== '') {
    $where .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) ';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if ($department !== '') {
    $where .= ' AND department = ? ';
    $params[] = $department;
}
if ($status !== '') {
    $where .= ' AND status = ? ';
    $params[] = $status;
}

// Build SELECT clause with selected columns
$selectFields = array_map(function($col) use ($availableColumns) {
    return $availableColumns[$col]['field'];
}, $selectedColumns);

$selectClause = 'id, ' . implode(', ', $selectFields);

$stmt = $pdo->prepare('SELECT ' . $selectClause . ' FROM employees' . $where . ' ORDER BY last_name ASC, first_name ASC');
$stmt->execute($params);
$employees = $stmt->fetchAll();

logAction($pdo, 'EXPORT', 'employees', null, [], [
    'format' => $format,
    'count' => count($employees),
    'filters' => ['search' => $search, 'department' => $department, 'status' => $status],
]);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zaměstnanci_' . date('Y-m-d') . '.csv"');

    echo "\xEF\xBB\xBF";
    
    // Build headers from selected columns
    $headers = array_map(function($col) use ($availableColumns) {
        return $availableColumns[$col]['label'];
    }, $selectedColumns);
    
    echo implode(',', $headers) . "\n";

    foreach ($employees as $emp) {
        $row = [];
        
        foreach ($selectedColumns as $col) {
            $field = $availableColumns[$col]['field'];
            $value = $emp[$field] ?? '';
            
            // Format based on field type
            if ($field === 'salary') {
                $value = str_replace(' ', '', formatSalary($value));
            } elseif ($field === 'hire_date') {
                $value = formatDate($value);
            } elseif ($field === 'status') {
                $statuses = getStatuses();
                $value = $statuses[$value] ?? $value;
            } elseif (in_array($field, ['first_name', 'last_name', 'email', 'phone', 'department'])) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            
            $row[] = $value;
        }
        
        echo implode(',', $row) . "\n";
    }
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>PDF report - Zaměstnanci</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { margin: 0 0 6px; }
        .meta { color: #4b5563; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()">Tisk / Uložit jako PDF</button>
    </div>
    <h1>Seznam zaměstnanců</h1>
    <div class="meta">Vygenerováno: <?= date('d.m.Y H:i') ?> | Počet záznamů: <?= count($employees) ?></div>
    <table>
        <thead>
            <tr>
                <?php foreach ($selectedColumns as $col): ?>
                    <th><?= escape($availableColumns[$col]['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <?php foreach ($selectedColumns as $col): ?>
                        <?php 
                            $field = $availableColumns[$col]['field'];
                            $value = $emp[$field] ?? '';
                            
                            if ($field === 'salary') {
                                $value = formatSalary($value);
                            } elseif ($field === 'hire_date') {
                                $value = formatDate($value);
                            } elseif ($field === 'status') {
                                $statuses = getStatuses();
                                $value = $statuses[$value] ?? $value;
                            }
                        ?>
                        <td><?= escape($value) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
