<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$search = trim($_GET['search'] ?? '');
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$allowedPerPage = [10, 25, 50];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}

$sort = $_GET['sort'] ?? 'last_name';
$direction = strtolower($_GET['direction'] ?? 'asc');
$allowedSort = [
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'email' => 'email',
    'department' => 'department',
    'salary' => 'salary',
    'hire_date' => 'hire_date',
    'status' => 'status',
    'created_at' => 'created_at',
];
$sortColumn = $allowedSort[$sort] ?? 'last_name';
$direction = $direction === 'desc' ? 'DESC' : 'ASC';

$where = ' WHERE 1=1 ';
$params = [];

if ($search !== '') {
    $where .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) ';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($department !== '') {
    $where .= ' AND department = ? ';
    $params[] = $department;
}

if ($status !== '') {
    $where .= ' AND status = ? ';
    $params[] = $status;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM employees' . $where);
$countStmt->execute($params);
$totalEmployees = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalEmployees / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT * FROM employees' . $where . " ORDER BY {$sortColumn} {$direction}, first_name ASC LIMIT ? OFFSET ?";
$listStmt = $pdo->prepare($listSql);

$bindIndex = 1;
foreach ($params as $value) {
    $listStmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
}
$listStmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
$listStmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
$listStmt->execute();
$employees = $listStmt->fetchAll();

$filterChips = [];
if ($search !== '') {
    $filterChips[] = ['label' => 'Hledat', 'value' => $search, 'param' => 'search'];
}
if ($department !== '') {
    $filterChips[] = ['label' => 'Oddělení', 'value' => $department, 'param' => 'department'];
}
if ($status !== '') {
    $statusMap = getStatuses();
    $filterChips[] = ['label' => 'Status', 'value' => $statusMap[$status] ?? $status, 'param' => 'status'];
}

$queryBase = [
    'search' => $search,
    'department' => $department,
    'status' => $status,
    'per_page' => $perPage,
    'sort' => $sortColumn,
    'direction' => strtolower($direction),
];

function buildQuery(array $base, array $replace = [], array $remove = []) {
    $query = array_merge($base, $replace);
    foreach ($remove as $key) {
        unset($query[$key]);
    }
    foreach ($query as $key => $value) {
        if ($value === '') {
            unset($query[$key]);
        }
    }
    return http_build_query($query);
}

$roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seznam zaměstnanců - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-delete-endpoint="delete.php">
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
            <?php if (canManageEmployees()): ?>
            <div class="bulk-actions-bar">
                <div>
                    <span class="bulk-count">Vybráno: 0</span>
                </div>
                <div class="bulk-actions">
                    <button type="button" id="bulkExport" class="btn btn-secondary"><i class="fas fa-download"></i> Export vybraných</button>
                    <button type="button" id="bulkDelete" class="btn btn-danger"><i class="fas fa-trash"></i> Smazat vybrané</button>
                </div>
            </div>
            <?php endif; ?>

        </nav>

        <main class="content">
            <?= renderBreadcrumbs([
                ['text' => 'Zaměstnanci', 'url' => ''],
                'Seznam'
            ]) ?>
            
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-user-tie"></i> Seznam zaměstnanců</h1>
                    <p>Celkem nalezeno: <?= $totalEmployees ?> záznamů</p>
                </div>
                <div class="header-actions">
                    <?php if (canManageEmployees()): ?>
                        <button type="button" class="btn btn-primary" id="openExportModalBtn">
                            <i class="fas fa-file-export"></i> Pokročilý export
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <?php displayFlash(); ?>

            <div class="card">
                <form method="GET" action="list.php" class="filter-form" data-filter-key="employees-list" data-table-target="#employeeTableView">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">🔎 Vyhledávání</label>
                            <input type="text" id="search" name="search" placeholder="Jméno nebo email" value="<?= escape($search) ?>">
                        </div>

                        <div class="form-group">
                            <label for="department">Oddělení</label>
                            <select id="department" name="department">
                                <option value="">Všechna oddělení</option>
                                <?php foreach (getDepartments() as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $department === $key ? 'selected' : '' ?>><?= escape($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status">Stav</label>
                            <select id="status" name="status">
                                <option value="">Všechny stavy</option>
                                <?php foreach (getStatuses() as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= escape($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="per_page">Na stránku</label>
                            <select id="per_page" name="per_page">
                                <?php foreach ($allowedPerPage as $size): ?>
                                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrovat</button>
                            <a href="list.php" class="btn btn-secondary"><i class="fas fa-rotate-left"></i> Resetovat filtry</a>
                        </div>
                    </div>
                </form>

                <div class="filter-toolbar">
                    <select id="savedFilterPresets" class="form-control" style="max-width: 260px;">
                        <option value="">Načíst uložený filtr</option>
                    </select>
                    <button type="button" id="saveFilterPreset" class="btn btn-secondary"><i class="fas fa-floppy-disk"></i> Uložit filtr</button>
                </div>

                <?php if (!empty($filterChips)): ?>
                    <div class="filter-chips">
                        <?php foreach ($filterChips as $chip): ?>
                            <span class="chip">
                                <?= escape($chip['label']) ?>: <strong><?= escape($chip['value']) ?></strong>
                                <a href="list.php?<?= buildQuery($queryBase, ['page' => 1], [$chip['param']]) ?>" class="chip-remove" title="Odebrat filtr">×</a>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="status-legend">
                    <span class="status-legend-item"><span class="status-dot active"></span> Aktivní</span>
                    <span class="status-legend-item"><span class="status-dot vacation"></span> Dovolená</span>
                    <span class="status-legend-item"><span class="status-dot sick_leave"></span> Nemocenská</span>
                    <span class="status-legend-item"><span class="status-dot inactive"></span> Neaktivní</span>
                </div>
            </div>

            <div class="card" data-employee-view>
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> Zaměstnanci</h2>
                    <div class="header-actions">
                        <div class="view-toggle" role="group" aria-label="Přepnout zobrazení">
                            <button type="button" id="viewTableBtn" class="btn btn-secondary active"><i class="fas fa-table"></i> Tabulka</button>
                            <button type="button" id="viewCardsBtn" class="btn btn-secondary"><i class="fas fa-id-card"></i> Karty</button>
                        </div>
                        <?php if (canManageEmployees()): ?>
                            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Přidat zaměstnance</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($employees)): ?>
                    <div class="empty-state">
                        <div class="empty-state-emoji">🔎</div>
                        <h3 class="empty-state-title">Žádný zaměstnanec neodpovídá filtru</h3>
                        <p class="empty-state-description">Zkuste upravit filtry, nebo vytvořte nový záznam.</p>
                        <div class="empty-state-actions">
                            <a href="list.php" class="btn btn-secondary"><i class="fas fa-rotate-left"></i> Resetovat filtry</a>
                            <?php if (canManageEmployees()): ?>
                                <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Přidat zaměstnance</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="employeeTableView" class="table-responsive">
                        <div class="table-loading-state" aria-hidden="true">
                            <span class="spinner"></span>
                            <span>Načítám zaměstnance...</span>
                        </div>
                        <table class="table table-zebra table-sticky table-sticky-enhanced">
                            <thead>
                                <tr>
                                                                        <?php if (canManageEmployees()): ?>
                                                                            <th class="checkbox-cell"><input type="checkbox" id="selectAll"></th>
                                                                        <?php endif; ?>
                                    <th><a href="?<?= buildQuery($queryBase, ['sort' => 'last_name', 'direction' => $sortColumn === 'last_name' && $direction === 'ASC' ? 'desc' : 'asc']) ?>">Jméno</a></th>
                                    <th><a href="?<?= buildQuery($queryBase, ['sort' => 'email', 'direction' => $sortColumn === 'email' && $direction === 'ASC' ? 'desc' : 'asc']) ?>">Email</a></th>
                                    <th>Telefon</th>
                                    <th><a href="?<?= buildQuery($queryBase, ['sort' => 'department', 'direction' => $sortColumn === 'department' && $direction === 'ASC' ? 'desc' : 'asc']) ?>">Oddělení</a></th>
                                    <th><a href="?<?= buildQuery($queryBase, ['sort' => 'salary', 'direction' => $sortColumn === 'salary' && $direction === 'ASC' ? 'desc' : 'asc']) ?>">Plat</a></th>
                                    <th><a href="?<?= buildQuery($queryBase, ['sort' => 'hire_date', 'direction' => $sortColumn === 'hire_date' && $direction === 'ASC' ? 'desc' : 'asc']) ?>">Nástup</a></th>
                                    <th><a href="?<?= buildQuery($queryBase, ['sort' => 'status', 'direction' => $sortColumn === 'status' && $direction === 'ASC' ? 'desc' : 'asc']) ?>">Stav</a></th>
                                    <th>Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <tr data-id="<?= (int) $emp['id'] ?>">
                                                                                <?php if (canManageEmployees()): ?>
                                                                                    <td class="checkbox-cell">
                                                                                        <input type="checkbox" class="employee-checkbox" value="<?= (int) $emp['id'] ?>">
                                                                                    </td>
                                                                                <?php endif; ?>
                                        <td><strong><?= escape($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></td>
                                        <td><?= escape($emp['email']) ?></td>
                                        <td><?= escape($emp['phone']) ?></td>
                                        <td>
                                            <?php if (canManageEmployees()): ?>
                                                <span class="badge inline-editable" 
                                                      data-field="department" 
                                                      data-employee-id="<?= (int) $emp['id'] ?>" 
                                                      data-value="<?= escape($emp['department']) ?>"
                                                      title="Klikněte pro úpravu">
                                                    <?= escape($emp['department']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge"><?= escape($emp['department']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (canManageEmployees()): ?>
                                                <span class="inline-editable" 
                                                      data-field="salary" 
                                                      data-employee-id="<?= (int) $emp['id'] ?>" 
                                                      data-value="<?= (int) $emp['salary'] ?>"
                                                      title="Klikněte pro úpravu">
                                                    <?= formatSalary($emp['salary']) ?>
                                                </span>
                                            <?php else: ?>
                                                <?= formatSalary($emp['salary']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatDate($emp['hire_date']) ?></td>
                                        <td>
                                            <?php if (canManageEmployees()): ?>
                                                <span class="status-badge status-<?= escape($emp['status']) ?> inline-editable" 
                                                      data-field="status" 
                                                      data-employee-id="<?= (int) $emp['id'] ?>" 
                                                      data-value="<?= escape($emp['status']) ?>"
                                                      title="Klikněte pro úpravu">
                                                    <?= escape(getStatuses()[$emp['status']] ?? $emp['status']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-<?= escape($emp['status']) ?>">
                                                    <?= escape(getStatuses()[$emp['status']] ?? $emp['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <a href="detail.php?id=<?= (int) $emp['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i> Detail</a>
                                            <?php if (canManageEmployees()): ?>
                                                <a href="edit.php?id=<?= (int) $emp['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-pen"></i> Upravit</a>
                                                <button type="button" class="btn btn-sm btn-danger js-open-delete" data-id="<?= (int) $emp['id'] ?>" data-name="<?= escape($emp['first_name'] . ' ' . $emp['last_name']) ?>"><i class="fas fa-trash"></i> Smazat</button>
                                            <?php else: ?>
                                                <span class="text-muted">Jen čtení</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="employeeCardView" class="employee-cards view-hidden">
                        <?php foreach ($employees as $emp): ?>
                            <?php $cardAvatar = generateAvatar($emp['first_name'], $emp['last_name']); ?>
                            <article class="employee-card">
                                <div class="employee-card-header">
                                    <div class="employee-card-info">
                                        <h3><?= escape($emp['first_name'] . ' ' . $emp['last_name']) ?></h3>
                                        <small><?= escape($emp['email']) ?></small>
                                    </div>
                                    <div class="avatar <?= escape($cardAvatar['color_class']) ?>" style="width: 48px; height: 48px;"><?= escape($cardAvatar['initials']) ?></div>
                                </div>

                                <div class="employee-meta">
                                    <div class="employee-meta-item">
                                        <strong>Oddělení</strong>
                                        <span class="badge"><?= escape($emp['department']) ?></span>
                                    </div>
                                    <div class="employee-meta-item">
                                        <strong>Stav</strong>
                                        <span class="status-badge status-<?= escape($emp['status']) ?>" style="font-size: 11px;">
                                            <?= escape(getStatuses()[$emp['status']] ?? $emp['status']) ?>
                                        </span>
                                    </div>
                                    <div class="employee-meta-item">
                                        <strong>Plat</strong>
                                        <div><?= formatSalary($emp['salary']) ?></div>
                                    </div>
                                    <div class="employee-meta-item">
                                        <strong>Nástup</strong>
                                        <div><?= formatDate($emp['hire_date']) ?></div>
                                    </div>
                                </div>

                                <div class="employee-card-actions">
                                    <a href="detail.php?id=<?= (int) $emp['id'] ?>" class="btn btn-sm btn-secondary" style="flex: 1; text-align: center;"><i class="fas fa-eye"></i> Detail</a>
                                    <?php if (canManageEmployees()): ?>
                                        <a href="edit.php?id=<?= (int) $emp['id'] ?>" class="btn btn-sm btn-secondary" style="flex: 1; text-align: center;"><i class="fas fa-edit"></i> Upravit</a>
                                        <button type="button" class="btn btn-sm btn-danger js-open-delete" data-id="<?= (int) $emp['id'] ?>" data-name="<?= escape($emp['first_name'] . ' ' . $emp['last_name']) ?>" style="flex: 1; text-align: center;"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="pagination-wrap">
                        <div class="pagination-info">Stránka <?= $page ?> z <?= $totalPages ?> (<?= $totalEmployees ?> záznamů)</div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm btn-secondary" href="?<?= buildQuery($queryBase, ['page' => $page - 1]) ?>">Předchozí</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm btn-secondary" href="?<?= buildQuery($queryBase, ['page' => $page + 1]) ?>">Další</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="modal" id="deleteModal" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
            <h3 id="deleteModalTitle">Potvrdit smazání</h3>
            <p id="deleteModalText">Opravdu chcete smazat vybraného zaměstnance?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Zrušit</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Ano, smazat</button>
            </div>
        </div>
    </div>

    <div class="modal" id="exportModal" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="exportModalTitle">
            <h3 id="exportModalTitle"><i class="fas fa-file-export"></i> Pokročilý export</h3>
            <p>Vyberte sloupce, které chcete exportovat:</p>
            
            <form id="exportForm" class="export-options">
                <input type="hidden" name="search" value="<?= escape($search) ?>">
                <input type="hidden" name="department" value="<?= escape($department) ?>">
                <input type="hidden" name="status" value="<?= escape($status) ?>">
                <input type="hidden" name="export_format" id="exportFormat" value="csv">
                
                <div class="export-columns">
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="first_name" checked>
                        <span>Jméno</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="last_name" checked>
                        <span>Příjmení</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="email" checked>
                        <span>Email</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="phone" checked>
                        <span>Telefon</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="department" checked>
                        <span>Oddělení</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="salary" checked>
                        <span>Plat</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="hire_date" checked>
                        <span>Datum nástupu</span>
                    </label>
                    <label class="export-checkbox-label">
                        <input type="checkbox" name="columns[]" value="status" checked>
                        <span>Status</span>
                    </label>
                </div>
                
                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Formát exportu:</label>
                    <div style="display: flex; gap: 15px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="format" value="csv" checked>
                            <span><i class="fas fa-file-csv"></i> CSV</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="format" value="pdf">
                            <span><i class="fas fa-file-pdf"></i> PDF</span>
                        </label>
                    </div>
                </div>
            </form>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Zrušit</button>
                <button type="button" class="btn btn-primary" id="confirmExportBtn">
                    <i class="fas fa-download"></i> Exportovat
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/dashboard.js"></script>
</body>
</html>
