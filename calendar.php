<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Získat absenci pro vybraný měsíc
$stmt = $pdo->prepare(" 
    SELECT e.id, e.first_name, e.last_name, e.status, e.hire_date, DATE(e.updated_at) AS absence_date
    FROM employees e
    WHERE strftime('%Y-%m', COALESCE(
        CASE 
            WHEN e.status = 'vacation' THEN e.updated_at
            WHEN e.status = 'sick_leave' THEN e.updated_at
            ELSE NULL
        END, '1970-01-01'
    )) = ?
    AND e.status IN ('vacation', 'sick_leave')
");
$stmt->execute([sprintf('%04d-%02d', $year, $month)]);
$absences = $stmt->fetchAll();

// Získat všechny dny v měsíci
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay); // 0 = neděle

 $roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);

// Navigace měsíců
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
    5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
    9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec'
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalendář absencí - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/styles.css">    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head>
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
                <li><a href="calendar.php" class="active"><i class="fas fa-calendar"></i> Kalendář</a></li>
                <li><a href="profile.php"><i class="fas fa-cog"></i> Nastavení</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásit se</a></li>
            </ul>
            <div class="user-info">
                <span class="user-role-badge <?= escape($roleMeta['class']) ?>"><?= escape($roleMeta['label']) ?></span>
                <strong><?= escape($_SESSION['user_name']) ?></strong>
                <small><?= escape($_SESSION['user_email']) ?></small>
            </div>
        </nav>

        <main class="content">
            <?= renderBreadcrumbs(['Kalendář absencí']) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-calendar"></i> Kalendář absencí</h1>
                    <p>Přehled dovolených a nemocenských</p>
                </div>
            </header>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary">← Předchozí</a>
                    <h2><?= $monthNames[$month] ?> <?= $year ?></h2>
                    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary">Další →</a>
                </div>

                <div class="status-legend">
                    <span class="status-legend-item"><span class="status-dot vacation"></span> Dovolená</span>
                    <span class="status-legend-item"><span class="status-dot sick_leave"></span> Nemocenská</span>
                </div>

                <div class="calendar-grid">
                    <?php
                    $dayNames = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
                    foreach ($dayNames as $dayName) {
                        echo "<div style='font-weight: 600; text-align: center; padding: 8px;'>{$dayName}</div>";
                    }
                    
                    // Vyplnit prázdné dny na začátku
                    $adjustedDayOfWeek = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
                    for ($i = 0; $i < $adjustedDayOfWeek; $i++) {
                        echo '<div class="calendar-day" style="opacity: 0.3;"></div>';
                    }
                    
                    // Dny v měsíci
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $isToday = $dateStr === date('Y-m-d');
                        
                        $classes = ['calendar-day'];
                        if ($isToday) $classes[] = 'has-event';
                        
                        // Kontrola absence v konkrétní den
                        $dayStatus = null;
                        foreach ($absences as $absence) {
                            if (($absence['absence_date'] ?? '') !== $dateStr) {
                                continue;
                            }
                            $dayStatus = $absence['status'];
                            if ($dayStatus === 'vacation') {
                                $classes[] = 'vacation';
                            } elseif ($dayStatus === 'sick_leave') {
                                $classes[] = 'sick';
                            }
                            break;
                        }

                        echo '<div class="' . implode(' ', $classes) . '">';
                        echo '<div>' . $day . '</div>';
                        if ($dayStatus === 'vacation') {
                            echo '<small>D</small>';
                        } elseif ($dayStatus === 'sick_leave') {
                            echo '<small>N</small>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>

                <div style="margin-top: 24px; display: flex; gap: 16px; font-size: 13px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 16px; height: 16px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 3px;"></div>
                        <span>Dovolená</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 16px; height: 16px; background: #fee2e2; border: 1px solid #f87171; border-radius: 3px;"></div>
                        <span>Nemocenská</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>📋 Aktuální absence</h3>
                <?php if (empty($absences)): ?>
                    <div class="empty-state">
                        <div class="empty-state-emoji">🗓️</div>
                        <p>V tomto měsíci nejsou žádné zaznamenané absence.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Zaměstnanec</th>
                                <th>Typ absence</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absences as $absence): ?>
                                <tr>
                                    <td>
                                        <a href="employees/detail.php?id=<?= (int)$absence['id'] ?>">
                                            <?= escape($absence['first_name'] . ' ' . $absence['last_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= escape($absence['status']) ?>">
                                            <?= escape(getStatuses()[$absence['status']] ?? $absence['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatDate($absence['absence_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/dashboard.js"></script>
</body>
</html>
