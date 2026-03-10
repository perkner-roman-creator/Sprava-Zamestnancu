<?php
/**
 * Pomocné funkce pro aplikaci
 */

// Session timeout (30 minut)
define('SESSION_TIMEOUT', 30 * 60);

/**
 * Kontrola a obnovení session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                session_destroy();
                header('Location: /login.php?timeout=1');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Kontrola, zda je uživatel přihlášen
 */
function requireLogin() {
    checkSessionTimeout();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Kontrola role uživatele
 * @param array $allowedRoles - pole povolených rolí (1=Admin, 2=Manager, 3=Viewer)
 */
function requireRole($allowedRoles = []) {
    requireLogin();
    
    if (empty($allowedRoles)) return; // Bez omezení
    
    $currentRole = $_SESSION['user_role_id'] ?? null;
    if (!$currentRole || !in_array((int) $currentRole, $allowedRoles, true)) {
        http_response_code(403);
        die('Nemáte oprávnění pro tuto akci!');
    }
}

/**
 * Kontrola, zda uživatel může spravovat zaměstnance.
 */
function canManageEmployees() {
    $roleId = (int) ($_SESSION['user_role_id'] ?? 0);
    return in_array($roleId, [1, 2], true);
}

/**
 * Meta informace o roli pro UI.
 */
function getRoleMeta($roleId, $roleName = null) {
    $id = (int) $roleId;
    $meta = [
        1 => ['label' => 'Admin', 'class' => 'role-admin'],
        2 => ['label' => 'Manager', 'class' => 'role-manager'],
        3 => ['label' => 'Viewer', 'class' => 'role-viewer'],
    ];

    $fallbackLabel = $roleName ?: 'Uživatel';
    return $meta[$id] ?? ['label' => $fallbackLabel, 'class' => 'role-default'];
}

/**
 * Bezpečný výstup HTML (ochrana proti XSS)
 */
function escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validace emailu
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validace telefonního čísla (český formát)
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[\s\-\(\)]/','', $phone);
    return preg_match('/^\+?420?[0-9]{9}$/', $phone);
}

/**
 * Validace platu (kladné číslo)
 */
function isValidSalary($salary) {
    return is_numeric($salary) && $salary >= 0;
}

/**
 * Formátování platu pro zobrazení
 */
function formatSalary($salary) {
    return number_format($salary, 0, ',', ' ') . ' Kč';
}

/**
 * Formátování data do českého formátu
 */
function formatDate($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date('d.m.Y', $timestamp);
}

/**
 * Generování avataru s iniciály
 */
function generateAvatar($firstName, $lastName) {
    $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
    $fullName = $firstName . ' ' . $lastName;
    $colorClass = getAvatarColorClass($fullName);
    
    return [
        'initials' => strtoupper($initials),
        'color_class' => $colorClass,
        // Pro zpětnou kompatibilitu, pokud někde ještě používáme color
        'color' => '#667eea'
    ];
}

/**
 * Logování akcí v databázi
 */
function logAction($pdo, $action, $entityType, $entityId, $oldValues = [], $newValues = []) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO action_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            !empty($oldValues) ? json_encode($oldValues) : null,
            !empty($newValues) ? json_encode($newValues) : null,
            $ipAddress
        ]);
    } catch (Exception $e) {
        // Selhání logování nebude blokovat operaci
        error_log("Logování chyba: " . $e->getMessage());
    }
}

/**
 * Získání poslední akce
 */
function getActionLogs($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT al.*, u.name as user_name
            FROM action_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Získání seznamu oddělení
 */
function getDepartments() {
    return [
        'IT' => 'IT',
        'HR' => 'Lidské zdroje',
        'Prodej' => 'Prodej',
        'Marketing' => 'Marketing',
        'Finance' => 'Finance',
        'Logistika' => 'Logistika',
        'Administrativa' => 'Administrativa'
    ];
}

/**
 * Získání statusů zaměstnance
 */
function getStatuses() {
    return [
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
        'vacation' => 'Na dovolené',
        'sick_leave' => 'Nemocenská'
    ];
}

/**
 * Vytvoření flash zprávy
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Zobrazení a smazání flash zprávy
 */
function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-error';
        echo "<div class='alert {$class}'>" . escape($flash['message']) . "</div>";
        unset($_SESSION['flash']);
    }
}

/**
 * Vrátí flash zprávu a zároveň ji smaže ze session.
 * Používá se tam, kde je potřeba flash vykreslit vlastní šablonou.
 */
function getFlash() {
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Validace CSRF tokenu (základní implementace)
 */
function generateToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Kontrola CSRF tokenu
 */
function validateToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Přesměrování na jinou stránku
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Generování breadcrumbs navigace
 * @param array $items - pole s položkami breadcrumbs ve formátu ['text' => 'Text', 'url' => 'url'] nebo jen 'text' pro současnou stránku
 * @return string HTML kód breadcrumbs
 */
function renderBreadcrumbs($items = []) {
    if (empty($items)) {
        return '';
    }
    
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb navigace">';
    $html .= '<a href="index.php" class="breadcrumbs-item"><i class="fas fa-home"></i></a>';
    
    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);
        
        // Pokud je položka jen string
        if (is_string($item)) {
            $html .= '<span class="breadcrumbs-separator"><i class="fas fa-chevron-right"></i></span>';
            $html .= '<span class="breadcrumbs-item breadcrumbs-current">' . escape($item) . '</span>';
        } 
        // Pokud je položka array s url
        elseif (is_array($item) && isset($item['text'])) {
            $html .= '<span class="breadcrumbs-separator"><i class="fas fa-chevron-right"></i></span>';
            
            if ($isLast || empty($item['url'])) {
                $html .= '<span class="breadcrumbs-item breadcrumbs-current">' . escape($item['text']) . '</span>';
            } else {
                $html .= '<a href="' . escape($item['url']) . '" class="breadcrumbs-item">' . escape($item['text']) . '</a>';
            }
        }
    }
    
    $html .= '</nav>';
    return $html;
}

/**
 * Získat avatar color třídu podle jména (hash-based)
 * @param string $name - jméno pro hash
 * @return string - CSS třída (avatar-color-0 až avatar-color-9)
 */
function getAvatarColorClass($name) {
    $hash = crc32($name);
    $colorIndex = abs($hash) % 10;
    return 'avatar-color-' . $colorIndex;
}
