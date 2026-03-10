<?php
/**
 * Konfigurace připojení k databázi
 * Podporuje SQLite (vývojové prostředí) nebo MySQL (produkce)
 */

// ===== KONFIGURACE DATABÁZE =====

// SQLite (výchozí - žádná instalace MySQL nutná)
$dbPath = __DIR__ . "/../employees.db";
$dsn = "sqlite:" . $dbPath;
$username = null;
$password = null;

// MySQL (odkomentujte pro použití MySQL)
// $dsn = "mysql:host=localhost;dbname=employees_db;charset=utf8mb4";
// $username = "root";
// $password = "";

// ===== PŘIPOJENÍ K DATABÁZI =====

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Vytvoření tabulek pokud neexistují (auto-init pro SQLite)
    if (strpos($dsn, 'sqlite:') === 0) {
        initDatabase($pdo);
    }
    
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}

/**
 * Inicializace databázových tabulek
 */
function initDatabase($pdo) {
    // Tabulka rolí
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            description TEXT
        )
    ");
    
    // Tabulka uživatelů (pro přihlášení)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role_id INTEGER DEFAULT 2,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(role_id) REFERENCES roles(id)
        )
    ");
    
    // Tabulka zaměstnanců
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20),
            department VARCHAR(50) NOT NULL,
            salary DECIMAL(10,2),
            hire_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Tabulka akcí a logů
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS action_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INTEGER,
            old_values TEXT,
            new_values TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ");
    
    // Kontrola, zda existuje výchozí admin účet
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    $roleCount = $stmt->fetchColumn();
    
    if ($roleCount == 0) {
        // Přidání rolí
        $pdo->exec("
            INSERT INTO roles (id, name, description) 
            VALUES 
            (1, 'Admin', 'Správce - úplný přístup'),
            (2, 'Manager', 'Vedoucí - čtení a editace'),
            (3, 'Viewer', 'Prohlížeč - pouze čtení')
        ");
    }
    
    // Kontrola, zda existuje výchozí admin účet
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Vytvoření výchozího admin účtu
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (email, password, name, role_id) 
            VALUES ('admin@firma.cz', '$adminPassword', 'Administrátor', 1)
        ");
        
        // Přidání ukázkových zaměstnanců
        $pdo->exec("
            INSERT INTO employees (first_name, last_name, email, phone, department, salary, hire_date, status)
            VALUES 
            ('Jan', 'Novák', 'jan.novak@firma.cz', '+420 777 123 456', 'IT', 45000, '2023-01-15', 'active'),
            ('Eva', 'Svobodová', 'eva.svobodova@firma.cz', '+420 777 234 567', 'HR', 38000, '2023-03-20', 'active'),
            ('Petr', 'Dvořák', 'petr.dvorak@firma.cz', '+420 777 345 678', 'Prodej', 42000, '2023-06-10', 'active'),
            ('Marie', 'Nováková', 'marie.novakova@firma.cz', '+420 777 456 789', 'Marketing', 40000, '2024-02-01', 'active'),
            ('Tomáš', 'Procházka', 'tomas.prochazka@firma.cz', '+420 777 567 890', 'Finance', 47000, '2022-11-05', 'active')
        ");
    }
}

// Funkce pro získání PDO instance (pro použití v jiných souborech)
function getDatabase() {
    global $pdo;
    return $pdo;
}
