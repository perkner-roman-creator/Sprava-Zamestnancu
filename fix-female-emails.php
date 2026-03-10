<?php
/**
 * Opraví emailové adresy ženských zaměstnankyň - přidá koncovku -ova/-a do emailu
 */
require_once 'config/database.php';

$femaleNames = ['Eva', 'Lucie', 'Jana', 'Marie', 'Veronika', 'Anna', 'Petra', 'Kateřina', 'Lenka', 'Tereza', 'Martina', 'Hana'];

function makeEmailLastName($displayLastName) {
    // Převeď na lowercase a odstraň diakritiku pro email
    $transliteration = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
        'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
        'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        'Á' => 'a', 'Č' => 'c', 'Ď' => 'd', 'É' => 'e', 'Ě' => 'e',
        'Í' => 'i', 'Ň' => 'n', 'Ó' => 'o', 'Ř' => 'r', 'Š' => 's',
        'Ť' => 't', 'Ú' => 'u', 'Ů' => 'u', 'Ý' => 'y', 'Ž' => 'z',
    ];
    
    $emailName = strtr($displayLastName, $transliteration);
    return strtolower($emailName);
}

// Načti všechny ženské zaměstnankyně
$stmt = $pdo->query("SELECT id, first_name, last_name, email FROM employees");
$employees = $stmt->fetchAll();

$updated = 0;

foreach ($employees as $emp) {
    $isFemale = in_array($emp['first_name'], $femaleNames, true);
    
    if ($isFemale) {
        // Vytvoř novou emailovou adresu na základě aktuálního příjmení
        $emailLastName = makeEmailLastName($emp['last_name']);
        $firstName = makeEmailLastName($emp['first_name']);
        
        // Extrahuj číslo z původního emailu (např. "eva.novak8@firma.cz" → "8")
        if (preg_match('/(\d+)@/', $emp['email'], $matches)) {
            $number = $matches[1];
            $newEmail = $firstName . '.' . $emailLastName . $number . '@firma.cz';
            
            // Pokud se email změnil, aktualizuj
            if ($newEmail !== $emp['email']) {
                $updateStmt = $pdo->prepare('UPDATE employees SET email = ? WHERE id = ?');
                $updateStmt->execute([$newEmail, $emp['id']]);
                $updated++;
                echo "Opraveno: {$emp['email']} → {$newEmail}\n";
            }
        }
    }
}

echo "\nCelkem opraveno emailů: {$updated}" . PHP_EOL;
echo "Emailové adresy byla úspěšně upraveny." . PHP_EOL;
