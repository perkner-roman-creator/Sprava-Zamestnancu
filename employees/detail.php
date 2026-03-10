<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Neplatné ID zaměstnance.');
    redirect('list.php');
}

$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('error', 'Zaměstnanec nebyl nalezen.');
    redirect('list.php');
}

$logsStmt = $pdo->prepare("SELECT al.*, u.name as user_name
    FROM action_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.entity_type = 'employee' AND al.entity_id = ?
    ORDER BY al.created_at DESC
    LIMIT 20");
$logsStmt->execute([$id]);
$logs = $logsStmt->fetchAll();
// Načíst milníky zaměstnance
$milestonesStmt = $pdo->prepare("
    SELECT * FROM employee_milestones
    WHERE employee_id = ?
    ORDER BY milestone_date DESC
");
$milestonesStmt->execute([$id]);
$milestones = $milestonesStmt->fetchAll();

$milestoneIcons = [
    'PROMOTION' => '🎖️',
    'SALARY_RAISE' => '💵',
    'TRAINING' => '📚',
    'AWARD' => '🏆',
    'PROJECT' => '🚀',
];


$avatar = generateAvatar($employee['first_name'], $employee['last_name']);
$roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail zaměstnance - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
        <button class="mobile-menu-toggle">☰</button>
        <div class="sidebar-overlay"></div>
    
    <div class="wrapper">
        <nav class="sidebar">
            <div class="logo"><i class="fas fa-users"></i> Správa zaměstnanců</div>
            <ul class="menu">
                <li><a href="../index.php"><i class="fas fa-chart-line"></i> Přehled</a></li>
                <li><a href="list.php" class="active"><i class="fas fa-user-tie"></i> Zaměstnanci</a></li>
                <?php if (canManageEmployees()): ?>
                    <li><a href="create.php"><i class="fas fa-plus"></i> Přidat zaměstnance</a></li>
                    <li><a href="import.php"><i class="fas fa-download"></i> Import CSV</a></li>
                    <li><a href="../audit-log.php"><i class="fas fa-list"></i> Audit log</a></li>
                <?php endif; ?>
                <li><a href="../calendar.php"><i class="fas fa-calendar"></i> Kalendář</a></li>
                <li><a href="../profile.php"><i class="fas fa-cog"></i> Nastavení</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásit se</a></li>
            </ul>
            <div class="user-info">
                <span class="user-role-badge <?= escape($roleMeta['class']) ?>"><?= escape($roleMeta['label']) ?></span>
                <strong><?= escape($_SESSION['user_name']) ?></strong>
                <small><?= escape($_SESSION['user_email']) ?></small>
            </div>

        </nav>

        <main class="content">
            <?= renderBreadcrumbs([
                ['text' => 'Zaměstnanci', 'url' => 'list.php'],
                escape($employee['first_name'] . ' ' . $employee['last_name'])
            ]) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-user"></i> Detail zaměstnance</h1>
                    <p>Kompletní profil a historie změn</p>
                </div>
                <div class="header-actions">
                    <a href="list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Zpět na seznam</a>
                    <a href="export-card.php?id=<?= (int) $employee['id'] ?>" class="btn btn-secondary" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> PDF karta</a>
                    <?php if (canManageEmployees()): ?>
                        <a href="edit.php?id=<?= (int) $employee['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Upravit</a>
                    <?php endif; ?>
                </div>
            </header>

            <nav class="section-nav no-print" aria-label="Navigace sekcí">
                <a href="#profil" class="active">Profil</a>
                <a href="#milniky">Milníky</a>
                <a href="#historie">Historie</a>
            </nav>

            <div class="grid">
                <div id="profil" class="card section-panel">
                    <div class="employee-row" style="margin-bottom: 20px;">
                        <div class="avatar <?= escape($avatar['color_class']) ?>"><?= escape($avatar['initials']) ?></div>
                        <div>
                            <h2><?= escape($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                            <p class="text-muted"><?= escape($employee['email']) ?></p>
                        </div>
                    </div>

                    <div class="profile-grid">
                        <div><strong>Telefon:</strong><br><?= escape($employee['phone']) ?></div>
                        <div><strong>Oddělení:</strong><br><?= escape($employee['department']) ?></div>
                        <div><strong>Plat:</strong><br><?= formatSalary($employee['salary']) ?></div>
                        <div><strong>Stav:</strong><br><span class="status-badge status-<?= escape($employee['status']) ?>"><?= escape(getStatuses()[$employee['status']] ?? $employee['status']) ?></span></div>
                        <div><strong>Datum nástupu:</strong><br><?= formatDate($employee['hire_date']) ?></div>
                        <div><strong>Poslední změna:</strong><br><?= formatDate($employee['updated_at']) ?></div>
                    </div>
                </div>

                <div id="historie" class="card section-panel">
                    <h2>🕒 Časová osa aktivit</h2>
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <div class="empty-state-emoji">🧾</div>
                            <p>U tohoto zaměstnance zatím nejsou žádné logy.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($logs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div>
                                        <strong><?= escape($log['action']) ?></strong>
                                        <div class="text-muted"><?= escape($log['user_name'] ?? 'Systém') ?> | <?= escape($log['entity_type']) ?> #<?= (int) $log['entity_id'] ?></div>
                                        <small class="text-muted"><?= escape($log['created_at']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="milniky" class="card section-panel">
                <h2>🎯 Milníky kariéry</h2>
                <?php if (empty($milestones)): ?>
                    <div class="empty-state">
                        <div class="empty-state-emoji">🚀</div>
                        <p>Zatím nejsou přidané žádné milníky.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($milestones as $milestone): ?>
                        <div class="timeline-milestone">
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="font-size: 28px;"><?= $milestoneIcons[$milestone['milestone_type']] ?? '✨' ?></div>
                                <div style="flex: 1;">
                                    <strong style="font-size: 16px; display: block; margin-bottom: 4px;">
                                        <?= escape($milestone['title']) ?>
                                    </strong>
                                    <?php if (!empty($milestone['description'])): ?>
                                        <p class="text-muted" style="margin-bottom: 8px;">
                                            <?= escape($milestone['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <span class="milestone-badge">📅 <?= formatDate($milestone['milestone_date']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/dashboard.js"></script>
</body>
</html>
