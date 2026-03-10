<?php
require_once 'config/database.php';

echo "=== Finální kontrola emailových adres ===\n\n";

// Kontrola všech ženských příjmení a emailů
$stmt = $pdo->query("
    SELECT first_name, last_name, email 
    FROM employees 
    WHERE first_name IN ('Eva', 'Jana', 'Marie', 'Lucie', 'Veronika', 'Anna')
    ORDER BY last_name, first_name
    LIMIT 20
");
$employees = $stmt->fetchAll();

$allOk = true;
foreach ($employees as $emp) {
    $lastName = strtolower(strtr($emp['last_name'], [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
        'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
        'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
    ]));
    
    $firstName = strtolower(strtr($emp['first_name'], [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ý' => 'y',
    ]));
    
    $matches = strpos($emp['email'], $firstName . '.' . $lastName) === 0;
    $status = $matches ? '✓' : '✗';
    
    echo "{$status} {$emp['first_name']} {$emp['last_name']} - {$emp['email']}\n";
    
    if (!$matches) {
        $allOk = false;
    }
}

echo "\n";
if ($allOk) {
    echo "✓ Všechny emailové adresy jsou v pořádku!\n";
} else {
    echo "✗ Některé emailové adresy nesouhlasí s příjmením.\n";
}

// Statistika
echo "\n=== Statistika ===\n";
$stats = $pdo->query("
    SELECT 
        CASE 
            WHEN first_name IN ('Eva', 'Jana', 'Marie', 'Lucie', 'Veronika', 'Anna') THEN 'Ženy'
            ELSE 'Muži'
        END as pohlavi,
        COUNT(*) as pocet
    FROM employees 
    GROUP BY pohlavi
")->fetchAll();

foreach ($stats as $s) {
    echo $s['pohlavi'] . ': ' . $s['pocet'] . PHP_EOL;
}
