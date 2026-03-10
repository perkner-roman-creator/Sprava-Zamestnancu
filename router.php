<?php
/**
 * Router pro PHP built-in server
 */

// Získání cesty
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Pokud je to soubor (CSS, obrázky atd.), vrať ho
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Root přesměruje na login
if ($uri === '/') {
    $_SERVER['REQUEST_URI'] = '/login.php';
    $_SERVER['SCRIPT_NAME'] = '/login.php';
    require_once __DIR__ . '/login.php';
    exit;
}

// Pokus o načtení požadovaného souboru
$file = __DIR__ . $uri;

if (file_exists($file)) {
    require_once $file;
} else {
    // 404
    http_response_code(404);
    echo "404 - Stránka nenalezena";
}
