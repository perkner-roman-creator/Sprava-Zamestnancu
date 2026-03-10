<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Přesměrování, pokud je uživatel již přihlášen
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$timeout = isset($_GET['timeout']);
$loggedOut = isset($_GET['logout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Vyplňte všechny pole';
    } else {
        // Kontrola uživatele v databázi
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Úspěšné přihlášení
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role_id'] = $user['role_id'];
            $_SESSION['user_role_name'] = $user['role_name'];
            $_SESSION['last_activity'] = time();
            
            // Aktualizace last_login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Logování přihlášení
            logAction($pdo, 'LOGIN', 'user', $user['id']);
            
            // Regenerace session ID pro bezpečnost
            session_regenerate_id(true);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Nesprávný email nebo heslo';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Správa Zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>👥 Správa Zaměstnanců</h1>
            <p class="subtitle">Přihlaste se do systému</p>
            
            <?php if ($timeout): ?>
                <div class="alert alert-error">⏱️ Vaše relace vypršela - přihlaste se prosím znovu</div>
            <?php endif; ?>
            
            <?php if ($loggedOut): ?>
                <div class="alert alert-success">✅ Byl jste úspěšně odhlášen</div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus
                           value="<?= escape($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Heslo</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    🔐 Přihlásit se
                </button>
            </form>
            
            <div class="login-help">
                <p><strong>Výchozí přihlašovací údaje:</strong></p>
                <p>Email: <code>admin@firma.cz</code></p>
                <p>Heslo: <code>admin123</code></p>
            </div>
        </div>
    </div>
</body>
</html>
