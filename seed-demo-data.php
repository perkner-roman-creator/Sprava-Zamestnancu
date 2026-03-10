<?php
require_once 'config/database.php';

$maleNames = ['Jan', 'Petr', 'Karel', 'Tomáš', 'David', 'Martin'];
$femaleNames = ['Eva', 'Lucie', 'Jana', 'Marie', 'Veronika', 'Anna'];
$lastNamesMale = ['Novák', 'Svoboda', 'Dvořák', 'Novotný', 'Černý', 'Procházka', 'Kučera', 'Veselý', 'Marek', 'Pospíšil'];

function makeFemaleLastName($maleName) {
    $rules = [
        'ý' => 'á',      // Novotný → Novotná, Černý → Černá, Veselý → Veselá
    ];
    
    // Aplikuj pravidlo pro -ý → -á
    foreach ($rules as $from => $to) {
        if (mb_substr($maleName, -1) === $from) {
            return mb_substr($maleName, 0, -1) . $to;
        }
    }
    
    // Příjmení na -a: změň -a na -ová (Kučera → Kučerová, Procházka → Procházková)
    if (mb_substr($maleName, -1) === 'a') {
        return mb_substr($maleName, 0, -1) . 'ová';
    }
    
    // Pro všechny ostatní přidej -ová
    return $maleName . 'ová';
}

function removeAccents($string) {
    $transliteration = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
        'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
        'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        'Á' => 'a', 'Č' => 'c', 'Ď' => 'd', 'É' => 'e', 'Ě' => 'e',
        'Í' => 'i', 'Ň' => 'n', 'Ó' => 'o', 'Ř' => 'r', 'Š' => 's',
        'Ť' => 't', 'Ú' => 'u', 'Ů' => 'u', 'Ý' => 'y', 'Ž' => 'z',
    ];
    return strtolower(strtr($string, $transliteration));
}

$departments = ['IT', 'HR', 'Prodej', 'Marketing', 'Finance', 'Logistika'];
$statuses = ['active', 'active', 'active', 'inactive', 'vacation', 'sick_leave'];

$insert = $pdo->prepare('INSERT INTO employees (first_name, last_name, email, phone, department, salary, hire_date, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\', ?), datetime(\'now\'))');

$created = 0;
for ($i = 1; $i <= 60; $i++) {
    // Náhodně vyber pohlaví
    $isFemale = (bool) rand(0, 1);
    $first = $isFemale ? $femaleNames[array_rand($femaleNames)] : $maleNames[array_rand($maleNames)];
    $lastMale = $lastNamesMale[array_rand($lastNamesMale)];
    $last = $isFemale ? makeFemaleLastName($lastMale) : $lastMale;
    $email = removeAccents($first) . '.' . removeAccents($last) . $i . '@firma.cz';
    $phone = '+420 7' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999);
    $dept = $departments[array_rand($departments)];
    $salary = rand(32000, 82000);
    $hireDate = date('Y-m-d', strtotime('-' . rand(20, 1400) . ' days'));
    $status = $statuses[array_rand($statuses)];
    $createdAgo = '-' . rand(1, 120) . ' days';

    try {
        $insert->execute([$first, $last, $email, $phone, $dept, $salary, $hireDate, $status, $createdAgo]);
        $created++;
    } catch (Throwable $e) {
        // Duplicitni email preskocime.
    }
}

echo "Přidáno testovacích zaměstnanců: {$created}" . PHP_EOL;
