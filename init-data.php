<?php
require_once 'config/database.php';

// Smazání starých dat
try {
    $pdo->exec("DELETE FROM action_logs");
    $pdo->exec("DELETE FROM employees");
    $pdo->exec("DELETE FROM users");
    $pdo->exec("DELETE FROM roles");
    
    // Přidání rolí
    $pdo->exec("
        INSERT INTO roles (id, name, description) 
        VALUES 
        (1, 'Admin', 'Správce - úplný přístup'),
        (2, 'Manager', 'Vedoucí - čtení a editace'),
        (3, 'Viewer', 'Prohlížeč - pouze čtení')
    ");
    
    // Přidání admin uživatele
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password, name, role_id) VALUES ('admin@firma.cz', '$adminPassword', 'Administrátor', 1)");
    
    // Přidání zaměstnanců
    $pdo->exec("
        INSERT INTO employees (first_name, last_name, email, phone, department, salary, hire_date, status)
        VALUES 
        ('Jan', 'Novák', 'jan.novak@firma.cz', '+420 777 123 456', 'IT', 45000, '2023-01-15', 'active'),
        ('Eva', 'Svobodová', 'eva.svobodova@firma.cz', '+420 777 234 567', 'HR', 38000, '2023-03-20', 'active'),
        ('Petr', 'Dvořák', 'petr.dvorak@firma.cz', '+420 777 345 678', 'Prodej', 42000, '2023-06-10', 'active'),
        ('Marie', 'Nováková', 'marie.novakova@firma.cz', '+420 777 456 789', 'Marketing', 40000, '2024-02-01', 'active'),
        ('Tomáš', 'Procházka', 'tomas.prochazka@firma.cz', '+420 777 567 890', 'Finance', 47000, '2022-11-05', 'active'),
        ('Lukáš', 'Svoboda', 'lukas.svoboda@firma.cz', '+420 777 111 222', 'IT', 50000, '2023-05-01', 'active'),
        ('Petra', 'Nováková', 'petra.novakova@firma.cz', '+420 777 333 444', 'Prodej', 44000, '2023-07-15', 'active')
    ");
    
    echo "✅ Databáze inicializována\n";
} catch (Exception $e) {
    echo "❌ Chyba: " . $e->getMessage() . "\n";
}
