<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
requireRole([1, 2]); // Jen Admin (1) a Manager (2) mohou editovat

$roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);

$id = $_GET['id'] ?? 0;
$errors = [];

// Načtení zaměstnance
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('error', 'Zaměstnanec nenalezen');
    redirect('list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $beforeUpdate = $employee;

    // Získání a validace dat
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = $_POST['department'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $hireDate = $_POST['hire_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validace
    if (empty($firstName)) $errors[] = 'Jméno je povinné';
    if (empty($lastName)) $errors[] = 'Příjmení je povinné';
    if (empty($email)) {
        $errors[] = 'Email je povinný';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Email není ve správném formátu';
    }
    if (!empty($phone) && !isValidPhone($phone)) {
        $errors[] = 'Telefon není ve správném formátu';
    }
    if (empty($department)) $errors[] = 'Oddělení je povinné';
    if (empty($salary)) {
        $errors[] = 'Plat je povinný';
    } elseif (!isValidSalary($salary)) {
        $errors[] = 'Plat musí být kladné číslo';
    }
    if (empty($hireDate)) $errors[] = 'Datum nástupu je povinné';
    
    // Kontrola duplicitního emailu (kromě aktuálního zaměstnance)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Jiný zaměstnanec s tímto emailem již existuje';
        }
    }
    
    // Aktualizace v databázi
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                    department = ?, salary = ?, hire_date = ?, status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $firstName,
                $lastName,
                $email,
                $phone,
                $department,
                $salary,
                $hireDate,
                $status,
                $id
            ]);

            logAction($pdo, 'UPDATE', 'employee', $id, $beforeUpdate, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'department' => $department,
                'salary' => $salary,
                'hire_date' => $hireDate,
                'status' => $status,
            ]);
            
            setFlash('success', 'Zaměstnanec byl úspěšně aktualizován');
            redirect('list.php');
            
        } catch (PDOException $e) {
            $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
        }
    }
    
    // Pokud jsou chyby, použijeme POST data pro pre-fill
    $employee = array_merge($employee, $_POST);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upravit zaměstnance - Správa Zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="wrapper">
            <button class="mobile-menu-toggle" aria-label="Toggle menu">☰</button>
            <div class="sidebar-overlay"></div>
        <nav class="sidebar">
            <div class="logo"><i class="fas fa-users"></i> Správa Zaměstnanců</div>
            <ul class="menu">
                <li><a href="../index.php"><i class="fas fa-chart-line"></i> Přehled</a></li>
                <li><a href="list.php" class="active"><i class="fas fa-user-tie"></i> Zaměstnanci</a></li>
                <li><a href="create.php"><i class="fas fa-plus"></i> Přidat zaměstnance</a></li>
                                <li><a href="import.php"><i class="fas fa-download"></i> Import CSV</a></li>
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
                ['text' => escape($employee['first_name'] . ' ' . $employee['last_name']), 'url' => 'detail.php?id=' . (int)$employee['id']],
                'Upravit'
            ]) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-edit"></i> Upravit zaměstnance</h1>
                    <p>Aktualizace údajů zaměstnance #<?= $id ?></p>
                </div>
            </header>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Chyby ve formuláři:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" action="edit.php?id=<?= $id ?>">
                    <section class="form-section">
                        <h3 class="form-section-title"><i class="fas fa-id-card"></i> Osobní údaje</h3>
                        <p class="form-section-subtitle">Základní kontaktní informace zaměstnance.</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Jméno *</label>
                                <input type="text" id="first_name" name="first_name" required
                                       value="<?= escape($employee['first_name']) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Příjmení *</label>
                                <input type="text" id="last_name" name="last_name" required
                                       value="<?= escape($employee['last_name']) ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required
                                       value="<?= escape($employee['email']) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Telefon</label>
                                <input type="tel" id="phone" name="phone"
                                       placeholder="+420 777 123 456"
                                       value="<?= escape($employee['phone']) ?>">
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h3 class="form-section-title"><i class="fas fa-briefcase"></i> Pracovní údaje</h3>
                        <p class="form-section-subtitle">Nastavení oddělení, statusu a nástupu.</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department">Oddělení *</label>
                                <select id="department" name="department" required>
                                    <option value="">-- Vyberte oddělení --</option>
                                    <?php foreach (getDepartments() as $key => $value): ?>
                                        <option value="<?= $key ?>" 
                                            <?= $employee['department'] === $key ? 'selected' : '' ?>>
                                            <?= escape($value) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select id="status" name="status" required>
                                    <?php foreach (getStatuses() as $key => $value): ?>
                                        <option value="<?= $key ?>" 
                                            <?= $employee['status'] === $key ? 'selected' : '' ?>>
                                            <?= escape($value) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="salary">Měsíční plat (Kč) *</label>
                                <input type="number" id="salary" name="salary" min="0" step="1000" required
                                       value="<?= escape($employee['salary']) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="hire_date">Datum nástupu *</label>
                                <input type="date" id="hire_date" name="hire_date" required
                                       value="<?= escape($employee['hire_date']) ?>">
                            </div>
                        </div>
                    </section>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Uložit změny</button>
                        <a href="list.php" class="btn btn-secondary"><i class="fas fa-xmark"></i> Zrušit</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="../assets/dashboard.js"></script>
</body>
</html>
