<?php
require_once 'config/database.php';

echo "=== Vzorka příjmení v databázi ===\n\n";

$stmt = $pdo->query('SELECT first_name, last_name FROM employees ORDER BY last_name, first_name LIMIT 30');
$employees = $stmt->fetchAll();

foreach ($employees as $emp) {
    echo $emp['first_name'] . ' ' . $emp['last_name'] . PHP_EOL;
}

echo "\n=== Statistika koncovek ===\n";
$stats = $pdo->query("
    SELECT 
        CASE 
            WHEN last_name LIKE '%ová' THEN 'končí na -ová'
            WHEN last_name LIKE '%á' THEN 'končí na -á'
            WHEN last_name LIKE '%aová' THEN 'CHYBA: aová'
            ELSE 'jiné'
        END as typ,
        COUNT(*) as pocet
    FROM employees 
    GROUP BY typ
")->fetchAll();

foreach ($stats as $s) {
    echo $s['typ'] . ': ' . $s['pocet'] . PHP_EOL;
}
