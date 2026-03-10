<?php
/**
 * Opraví česká příjmení a jména v databázi - doplní diakritiku
 */
require_once 'config/database.php';

$replacements = [
    // Příjmení
    'Novak' => 'Novák',
    'Dvorak' => 'Dvořák',
    'Novotny' => 'Novotný',
    'Cerny' => 'Černý',
    'Prochazka' => 'Procházka',
    'Kucera' => 'Kučera',
    'Vesela' => 'Veselá',
    'Pospisil' => 'Pospíšil',
    // Jména
    'Tomas' => 'Tomáš',
];

$updated = 0;

foreach ($replacements as $old => $new) {
    // Oprava příjmení
    $stmt = $pdo->prepare('UPDATE employees SET last_name = ?, updated_at = datetime(\'now\') WHERE last_name = ?');
    $stmt->execute([$new, $old]);
    $updated += $stmt->rowCount();
    
    // Oprava jmen
    $stmt = $pdo->prepare('UPDATE employees SET first_name = ?, updated_at = datetime(\'now\') WHERE first_name = ?');
    $stmt->execute([$new, $old]);
    $updated += $stmt->rowCount();
}

echo "Opraveno záznamů: {$updated}" . PHP_EOL;
echo "Diakritika byla úspěšně doplněna." . PHP_EOL;
