<?php
require_once 'config/database.php';

// Resetovat tabulku action_logs
try {
    $pdo->exec("DROP TABLE IF EXISTS action_logs");
    $pdo->exec("
        CREATE TABLE action_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id INTEGER,
            old_values TEXT,
            new_values TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Přidání log item o přihlášení
    $stmt = $pdo->prepare("INSERT INTO action_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([1, 'LOGIN', 'user', 1]);
    
    echo "✅ action_logs table opravena\n";
} catch (Exception $e) {
    echo "❌ Chyba: " . $e->getMessage() . "\n";
}
