<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();
requireRole([1, 2]);

$actionFilter = trim($_GET['action'] ?? '');
$userFilter = (int) ($_GET['user_id'] ?? 0);
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = ' WHERE 1=1 ';
$params = [];

if ($actionFilter !== '') {
    $where .= ' AND al.action = ? ';
    $params[] = $actionFilter;
}
if ($userFilter > 0) {
    $where .= ' AND al.user_id = ? ';
    $params[] = $userFilter;
}
if ($fromDate !== '') {
    $where .= ' AND DATE(al.created_at) >= DATE(?) ';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where .= ' AND DATE(al.created_at) <= DATE(?) ';
    $params[] = $toDate;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM action_logs al' . $where);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = 'SELECT al.*, u.name as user_name
    FROM action_logs al
    LEFT JOIN users u ON u.id = al.user_id'
    . $where . ' ORDER BY al.created_at DESC LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);

$bindIndex = 1;
foreach ($params as $value) {
    if (is_int($value)) {
        $stmt->bindValue($bindIndex++, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
    }
}
$stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$users = $pdo->query('SELECT id, name FROM users ORDER BY name')->fetchAll();
$roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);

function queryWith(array $replace = []) {
    $query = array_merge($_GET, $replace);
    foreach ($query as $k => $v) {
        if ($v === '' || $v === null) {
            unset($query[$k]);
        }
    }
    return http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit log - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="wrapper">
            <button class="mobile-menu-toggle" aria-label="Toggle menu">☰</button>
            <div class="sidebar-overlay"></div>
        <nav class="sidebar">
            <div class="logo"><i class="fas fa-users"></i> Správa zaměstnanců</div>
            <ul class="menu">
                <li><a href="index.php"><i class="fas fa-chart-line"></i> Přehled</a></li>
                <li><a href="employees/list.php"><i class="fas fa-user-tie"></i> Zaměstnanci</a></li>
                <li><a href="employees/create.php"><i class="fas fa-plus"></i> Přidat zaměstnance</a></li>
                                <li><a href="employees/import.php"><i class="fas fa-download"></i> Import CSV</a></li>
                                <li><a href="calendar.php"><i class="fas fa-calendar"></i> Kalendář</a></li>
                <li><a href="audit-log.php" class="active"><i class="fas fa-list"></i> Audit log</a></li>
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
            <?= renderBreadcrumbs(['Audit log']) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-list"></i> Audit log</h1>
                    <p>Přehled všech akcí v systému</p>
                </div>

            </header>

            <div class="card">
                <form method="GET" class="filter-form" data-table-target="#auditTableWrap">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="action">Akce</label>
                            <select name="action" id="action">
                                <option value="">Všechny</option>
                                <?php foreach (['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'EXPORT'] as $action): ?>
                                    <option value="<?= $action ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= $action ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user_id">Uživatel</label>
                            <select name="user_id" id="user_id">
                                <option value="">Všichni</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= $userFilter === (int) $u['id'] ? 'selected' : '' ?>><?= escape($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="from">Od</label>
                            <input type="date" name="from" id="from" value="<?= escape($fromDate) ?>">
                        </div>
                        <div class="form-group">
                            <label for="to">Do</label>
                            <input type="date" name="to" id="to" value="<?= escape($toDate) ?>">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrovat</button>
                            <a href="audit-log.php" class="btn btn-secondary"><i class="fas fa-rotate-left"></i> Resetovat filtry</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <div class="empty-state-emoji">📭</div>
                        <h3 class="empty-state-title">Nebyly nalezeny žádné logy</h3>
                        <p class="empty-state-description">Pro vybraný interval a filtr nejsou dostupná žádná data.</p>
                        <div class="empty-state-actions">
                            <a href="audit-log.php" class="btn btn-secondary"><i class="fas fa-rotate-left"></i> Reset filtrů</a>
                        </div>
                    </div>
                <?php else: ?>
                <div class="table-responsive" id="auditTableWrap">
                    <div class="table-loading-state" aria-hidden="true">
                        <span class="spinner"></span>
                        <span>Načítám logy...</span>
                    </div>
                    <table class="table table-zebra table-sticky">
                        <thead>
                            <tr>
                                <th>Čas</th>
                                <th>Uživatel</th>
                                <th>Akce</th>
                                <th>Entita</th>
                                <th>ID</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= escape($log['created_at']) ?></td>
                                    <td><?= escape($log['user_name'] ?? 'Systém') ?></td>
                                    <td><span class="badge"><?= escape($log['action']) ?></span></td>
                                    <td><?= escape($log['entity_type']) ?></td>
                                    <td><?= escape((string) $log['entity_id']) ?></td>
                                    <td><?= escape($log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrap">
                    <div class="pagination-info">Stránka <?= $page ?> z <?= $totalPages ?> (<?= $totalRows ?> záznamů)</div>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm btn-secondary" href="?<?= queryWith(['page' => $page - 1]) ?>">Předchozí</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm btn-secondary" href="?<?= queryWith(['page' => $page + 1]) ?>">Další</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/dashboard.js"></script>
</body>
</html>
