<?php
// Rozšíření databáze o milníky pro zaměstnance
require_once 'config/database.php';

// Vytvořit tabulku pro milníky
$pdo->exec("
    CREATE TABLE IF NOT EXISTS employee_milestones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        milestone_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        milestone_date DATE NOT NULL,
        created_at DATETIME DEFAULT (datetime('now')),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )
");

echo "✓ Tabulka employee_milestones vytvořena\n";

// Přidat nějaké ukázkové milníky
$milestones = [
    ['type' => 'PROMOTION', 'title' => 'Povýšení na Senior Developer', 'desc' => 'Uznání za vynikající výkon'],
    ['type' => 'SALARY_RAISE', 'title' => 'Navýšení platu o 15%', 'desc' => 'Odměna za dosažené výsledky'],
    ['type' => 'TRAINING', 'title' => 'Absolvování školení PHP Advanced', 'desc' => 'Certifikát získán'],
    ['type' => 'AWARD', 'title' => 'Zaměstnanec měsíce', 'desc' => 'Ocenění od vedení společnosti'],
    ['type' => 'PROJECT', 'title' => 'Dokončení projektu XYZ', 'desc' => 'Úspěšné nasazení do produkce'],
];

// Přidat milníky k náhodným zaměstnancům
$employees = $pdo->query('SELECT id FROM employees LIMIT 10')->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare('
    INSERT INTO employee_milestones (employee_id, milestone_type, title, description, milestone_date)
    VALUES (?, ?, ?, ?, ?)
');

$added = 0;
foreach ($employees as $empId) {
    $numMilestones = rand(0, 3);
    for ($i = 0; $i < $numMilestones; $i++) {
        $milestone = $milestones[array_rand($milestones)];
        $date = date('Y-m-d', strtotime('-' . rand(30, 730) . ' days'));
        
        $stmt->execute([
            $empId,
            $milestone['type'],
            $milestone['title'],
            $milestone['desc'],
            $date
        ]);
        $added++;
    }
}

echo "✓ Přidáno {$added} ukázkových milníků\n";
echo "✓ Rozšíření databáze dokončeno\n";
