<?php
require_once 'config/database.php';

// Testovací uživatelé
$testUsers = [
    ['email' => 'admin@firma.cz', 'password' => 'admin123', 'name' => 'Jan Admin', 'role_id' => 1],
    ['email' => 'manager@firma.cz', 'password' => 'manager123', 'name' => 'Eva Manager', 'role_id' => 2],
    ['email' => 'viewer@firma.cz', 'password' => 'viewer123', 'name' => 'Petr Viewer', 'role_id' => 3],
];

try {
    // Vymazat existující testovací uživatele
    $pdo->exec("DELETE FROM users WHERE email IN ('admin@firma.cz', 'manager@firma.cz', 'viewer@firma.cz')");
    
    // Vložit nové uživatele
    foreach ($testUsers as $user) {
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, name, role_id, created_at)
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $user['email'],
            $hashedPassword,
            $user['name'],
            $user['role_id']
        ]);
    }
    
    echo "✅ Testovací uživatelé vytvořeni:\n\n";
    echo "👤 ADMIN:\n";
    echo "   Email: admin@firma.cz\n";
    echo "   Heslo: admin123\n\n";
    
    echo "👤 MANAGER:\n";
    echo "   Email: manager@firma.cz\n";
    echo "   Heslo: manager123\n\n";
    
    echo "👤 VIEWER (jen čtení):\n";
    echo "   Email: viewer@firma.cz\n";
    echo "   Heslo: viewer123\n";
    
} catch (Exception $e) {
    echo "❌ Chyba: " . $e->getMessage();
}
