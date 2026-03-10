<?php
require_once 'config/database.php';

echo "=== Vzorka emailů ženských zaměstnankyň ===\n\n";

$stmt = $pdo->query("SELECT first_name, last_name, email FROM employees WHERE first_name IN ('Eva', 'Jana', 'Marie', 'Lucie', 'Veronika', 'Anna') ORDER BY last_name LIMIT 15");
$employees = $stmt->fetchAll();

foreach ($employees as $emp) {
    echo $emp['first_name'] . ' ' . $emp['last_name'] . ' - ' . $emp['email'] . PHP_EOL;
}
