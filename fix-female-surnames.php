<?php
/**
 * Opraví ženská příjmení - přidá koncovku -ová nebo změní -ý na -á
 */
require_once 'config/database.php';

// Ženská křestní jména pro identifikaci pohlaví
$femaleNames = ['Eva', 'Lucie', 'Jana', 'Marie', 'Veronika', 'Anna', 'Petra', 'Kateřina', 'Lenka', 'Tereza', 'Martina', 'Hana'];

function makeFemaleLastName($maleName) {
    // Už ženská forma - přeskoč
    if (mb_substr($maleName, -3) === 'ová') {
        return $maleName;
    }
    
    // -ý na -á (Novotný → Novotná, Černý → Černá, Veselý → Veselá)
    if (mb_substr($maleName, -1) === 'ý') {
        return mb_substr($maleName, 0, -1) . 'á';
    }
    
    // Příjmení končící na -a: změň -a na -ová (Kučera → Kučerová, Procházka → Procházková)
    if (mb_substr($maleName, -1) === 'a') {
        return mb_substr($maleName, 0, -1) . 'ová';
    }
    
    // Pro všechny ostatní přidej -ová
    return $maleName . 'ová';
}

// Načti všechny zaměstnance
$stmt = $pdo->query('SELECT id, first_name, last_name FROM employees');
$employees = $stmt->fetchAll();

$updated = 0;

foreach ($employees as $emp) {
    $isFemale = in_array($emp['first_name'], $femaleNames, true);
    
    if ($isFemale) {
        $newLastName = makeFemaleLastName($emp['last_name']);
        
        // Pokud se příjmení změnilo, aktualizuj
        if ($newLastName !== $emp['last_name']) {
            $updateStmt = $pdo->prepare('UPDATE employees SET last_name = ?, updated_at = datetime(\'now\') WHERE id = ?');
            $updateStmt->execute([$newLastName, $emp['id']]);
            $updated++;
            echo "Opraveno: {$emp['first_name']} {$emp['last_name']} → {$emp['first_name']} {$newLastName}\n";
        }
    }
}

echo "\nCelkem opraveno záznamů: {$updated}" . PHP_EOL;
echo "Ženská příjmení byla úspěšně upravena." . PHP_EOL;
