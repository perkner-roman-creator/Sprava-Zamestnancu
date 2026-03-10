<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Neplatne ID zamestnance.');
    redirect('list.php');
}

$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('error', 'Zamestnanec nebyl nalezen.');
    redirect('list.php');
}

$milestonesStmt = $pdo->prepare('SELECT title, description, milestone_date FROM employee_milestones WHERE employee_id = ? ORDER BY milestone_date DESC LIMIT 8');
$milestonesStmt->execute([$id]);
$milestones = $milestonesStmt->fetchAll();

$logsStmt = $pdo->prepare("SELECT action, created_at, entity_type FROM action_logs WHERE entity_type = 'employee' AND entity_id = ? ORDER BY created_at DESC LIMIT 8");
$logsStmt->execute([$id]);
$logs = $logsStmt->fetchAll();

$avatar = generateAvatar($employee['first_name'], $employee['last_name']);

logAction($pdo, 'EXPORT', 'employee_card', $id, [], ['format' => 'pdf-card']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karta zamestnance - <?= escape($employee['first_name'] . ' ' . $employee['last_name']) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <main class="content" style="margin-left: 0; max-width: 1000px; margin-inline: auto;">
        <div class="no-print" style="display:flex; justify-content: space-between; margin-bottom: 16px; gap: 8px; flex-wrap: wrap;">
            <a href="detail.php?id=<?= (int) $employee['id'] ?>" class="btn btn-secondary">↩️ Zpet na detail</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Tisk / Ulozit jako PDF</button>
        </div>

        <div class="card">
            <div class="employee-row" style="margin-bottom: 14px;">
                <div class="avatar <?= escape($avatar['color_class']) ?>"><?= escape($avatar['initials']) ?></div>
                <div>
                    <h1><?= escape($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
                    <p class="text-muted"><?= escape($employee['email']) ?></p>
                </div>
            </div>

            <div class="profile-grid">
                <div><strong>Telefon</strong><br><?= escape($employee['phone']) ?></div>
                <div><strong>Oddeleni</strong><br><?= escape($employee['department']) ?></div>
                <div><strong>Plat</strong><br><?= formatSalary($employee['salary']) ?></div>
                <div><strong>Nastup</strong><br><?= formatDate($employee['hire_date']) ?></div>
                <div><strong>Status</strong><br><span class="status-badge status-<?= escape($employee['status']) ?>"><?= escape(getStatuses()[$employee['status']] ?? $employee['status']) ?></span></div>
                <div><strong>Vygenerovano</strong><br><?= date('d.m.Y H:i') ?></div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>🎯 Milniky</h2>
                <?php if (empty($milestones)): ?>
                    <p class="text-muted">Bez milniku.</p>
                <?php else: ?>
                    <?php foreach ($milestones as $milestone): ?>
                        <div class="timeline-milestone">
                            <strong><?= escape($milestone['title']) ?></strong>
                            <?php if (!empty($milestone['description'])): ?>
                                <p class="text-muted"><?= escape($milestone['description']) ?></p>
                            <?php endif; ?>
                            <span class="milestone-badge">📅 <?= formatDate($milestone['milestone_date']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>🕒 Posledni aktivity</h2>
                <?php if (empty($logs)): ?>
                    <p class="text-muted">Bez aktivit.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div>
                                    <strong><?= escape($log['action']) ?></strong>
                                    <div class="text-muted"><?= escape($log['entity_type']) ?></div>
                                    <small class="text-muted"><?= escape($log['created_at']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
