<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
requireRole([1, 2]);

$uploaded = false;
$preview = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $file = $_FILES['csvFile'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Přeskočit hlavičku
        $header = fgetcsv($handle, 1000, ',');
        
        $imported = 0;
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($row) < 6) continue;
            
            try {
                $stmt = $pdo->prepare('INSERT INTO employees (first_name, last_name, email, phone, department, salary, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    trim($row[0]), // first_name
                    trim($row[1]), // last_name
                    trim($row[2]), // email
                    trim($row[3] ?? ''), // phone
                    trim($row[4]), // department
                    floatval($row[5] ?? 0), // salary
                    trim($row[6] ?? date('Y-m-d')), // hire_date
                    trim($row[7] ?? 'active'), // status
                ]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Řádek " . ($imported + 2) . ": " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        logAction($pdo, 'IMPORT', 'employees', null, [], [
            'imported' => $imported,
            'errors' => count($errors)
        ]);
        
        setFlash('success', "Importováno $imported zaměstnanců" . (count($errors) > 0 ? " s " . count($errors) . " chybami" : ""));
        $uploaded = true;
    } else {
        setFlash('error', 'Chyba při nahrávání souboru');
    }
}

$roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <button class="mobile-menu-toggle">☰</button>
    <div class="sidebar-overlay"></div>
    
    <div class="wrapper">
        <nav class="sidebar">
            <div class="logo"><i class="fas fa-users"></i> Správa zaměstnanců</div>
            <ul class="menu">
                <li><a href="../index.php"><i class="fas fa-chart-line"></i> Přehled</a></li>
                <li><a href="list.php"><i class="fas fa-user-tie"></i> Zaměstnanci</a></li>
                <li><a href="create.php"><i class="fas fa-plus"></i> Přidat zaměstnance</a></li>
                <li><a href="import.php" class="active"><i class="fas fa-download"></i> Import CSV</a></li>
                <li><a href="../calendar.php"><i class="fas fa-calendar"></i> Kalendář</a></li>
                <li><a href="../audit-log.php"><i class="fas fa-list"></i> Audit log</a></li>
                <li><a href="../profile.php"><i class="fas fa-cog"></i> Nastavení</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásit se</a></li>
            </ul>
            <div class="user-info">
                <span class="user-role-badge <?= escape($roleMeta['class']) ?>"><?= escape($roleMeta['label']) ?></span>
                <strong><?= escape($_SESSION['user_name']) ?></strong>
                <small><?= escape($_SESSION['user_email']) ?></small>
            </div>
        </nav>

        <main class="content">
            <?= renderBreadcrumbs([
                ['text' => 'Zaměstnanci', 'url' => 'list.php'],
                'Import CSV'
            ]) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-file-upload"></i> Import zaměstnanců z CSV</h1>
                    <p>Nahrajte CSV soubor s daty zaměstnanců</p>
                </div>
                <div class="header-actions">
                    <a href="list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Zpět</a>
                </div>
            </header>

            <?php if ($flash = getFlash()): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= escape($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="card">
                    <h3>⚠️ Chyby při importu</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>📋 Formát CSV souboru</h2>
                <p>CSV soubor musí obsahovat následující sloupce (v tomto pořadí):</p>
                <pre style="background: var(--chip-bg); padding: 12px; border-radius: 6px; overflow-x: auto;">first_name,last_name,email,phone,department,salary,hire_date,status
Jan,Novák,jan.novak@firma.cz,+420 777 123 456,IT,45000,2023-01-15,active
Marie,Svobodová,marie.svobodova@firma.cz,+420 777 654 321,HR,42000,2023-02-20,active</pre>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="card">
                    <div class="upload-zone">
                        <div class="upload-zone-icon">📁</div>
                        <div class="upload-zone-text">
                            <strong>Klikněte nebo přetáhněte CSV soubor</strong><br>
                            <small>Podporované formáty: .csv</small>
                        </div>
                        <input type="file" id="csvFile" name="csvFile" accept=".csv" style="display: none;" required>
                    </div>
                    
                    <div id="csvPreview" style="margin-top: 16px;"></div>
                    
                    <div style="margin-top: 16px; display: flex; gap: 8px;">
                        <button type="submit" id="importSubmit" class="btn btn-primary" disabled>Importovat data</button>
                        <a href="list.php" class="btn btn-secondary">Zrušit</a>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="../assets/dashboard.js"></script>
</body>
</html>
