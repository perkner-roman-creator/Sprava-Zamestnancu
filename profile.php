<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$userId = $_SESSION['user_id'];
$updated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';
        
        // Ověřit současné heslo
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPw, $user['password'])) {
            setFlash('error', 'Současné heslo je nesprávné');
        } elseif ($newPw !== $confirmPw) {
            setFlash('error', 'Nová hesla se neshodují');
        } elseif (strlen($newPw) < 6) {
            setFlash('error', 'Heslo musí mít alespoň 6 znaků');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);
            
            logAction($pdo, 'UPDATE', 'user', $userId, [], ['field' => 'password']);
            setFlash('success', 'Heslo bylo úspěšně změněno');
            $updated = true;
        }
    }
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            setFlash('error', 'Všechna pole jsou povinná');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, $userId]);
            
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            logAction($pdo, 'UPDATE', 'user', $userId, [], ['fields' => ['name', 'email']]);
            setFlash('success', 'Profil byl úspěšně aktualizován');
            $updated = true;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$roleMeta = getRoleMeta($user['role_id'], null);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení profilu - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <button class="mobile-menu-toggle">☰</button>
    <div class="sidebar-overlay"></div>
    
    <div class="wrapper">
        <nav class="sidebar">
            <div class="logo"><i class="fas fa-users"></i> Správa zaměstnanců</div>
            <ul class="menu">
                <li><a href="index.php"><i class="fas fa-chart-line"></i> Přehled</a></li>
                <li><a href="employees/list.php"><i class="fas fa-user-tie"></i> Zaměstnanci</a></li>
                <?php if (canManageEmployees()): ?>
                    <li><a href="employees/create.php"><i class="fas fa-plus"></i> Přidat zaměstnance</a></li>
                    <li><a href="employees/import.php"><i class="fas fa-download"></i> Import CSV</a></li>
                    <li><a href="audit-log.php"><i class="fas fa-list"></i> Audit log</a></li>
                <?php endif; ?>
                <li><a href="calendar.php"><i class="fas fa-calendar"></i> Kalendář</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-cog"></i> Nastavení</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásit se</a></li>
            </ul>
            <div class="user-info">
                <span class="user-role-badge <?= escape($roleMeta['class']) ?>"><?= escape($roleMeta['label']) ?></span>
                <strong><?= escape($user['name']) ?></strong>
                <small><?= escape($user['email']) ?></small>
            </div>
        </nav>

        <main class="content">
            <?= renderBreadcrumbs(['Nastavení profilu']) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-cog"></i> Nastavení profilu</h1>
                    <p>Spravujte svůj účet a preference</p>
                </div>
            </header>

            <?php if ($flash = getFlash()): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= escape($flash['message']) ?></div>
            <?php endif; ?>

            <div class="grid">
                <div class="card">
                    <div class="settings-section">
                        <h3>👤 Osobní údaje</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label for="name">Jméno</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= escape($user['email']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Role</label>
                                <div>
                                    <span class="user-role-badge <?= escape($roleMeta['class']) ?>"><?= escape($roleMeta['label']) ?></span>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Uložit změny</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="settings-section">
                        <h3>🔐 Změna hesla</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Současné heslo</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">Nové heslo</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
                                <small class="text-muted">Minimálně 6 znaků</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Potvrdit nové heslo</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">🔒 Změnit heslo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="settings-section">
                    <h3>🧪 Demo režim</h3>
                    <p class="text-muted">Před pohovorem můžeš jedním klikem naplnit audit log ukázkovými záznamy.</p>
                    <a href="demo-scenario.php" class="btn btn-secondary">▶️ Spustit demo scénář</a>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/dashboard.js"></script>
</body>
</html>
