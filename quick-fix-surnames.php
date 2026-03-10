<?php
/**
 * Quick fix - opraví chybná příjmení "aová" na správná "ová"
 */
require_once 'config/database.php';

$stmt = $pdo->prepare("UPDATE employees SET last_name = REPLACE(last_name, 'aová', 'ová') WHERE last_name LIKE '%aová'");
$stmt->execute();

echo "Opraveno chybných příjmení: " . $stmt->rowCount() . PHP_EOL;
