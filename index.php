<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'");
$activeCount = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM employees");
$totalCount = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT AVG(salary) FROM employees WHERE status = 'active'");
$avgSalary = (float) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT department) FROM employees");
$deptCount = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE created_at >= datetime('now', '-7 day')");
$newHiresCurrent = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE created_at >= datetime('now', '-14 day') AND created_at < datetime('now', '-7 day')");
$newHiresPrev = (int) $stmt->fetchColumn();

if ($newHiresPrev > 0) {
    $hireTrendPct = round((($newHiresCurrent - $newHiresPrev) / $newHiresPrev) * 100);
} else {
    $hireTrendPct = $newHiresCurrent > 0 ? 100 : 0;
}
$hireTrendClass = $hireTrendPct >= 0 ? 'trend-up' : 'trend-down';
$hireTrendLabel = ($hireTrendPct >= 0 ? '+' : '') . $hireTrendPct . '% oproti minulemu tydnu';

$stmt = $pdo->query("SELECT department, COUNT(*) as count FROM employees GROUP BY department ORDER BY count DESC");
$departmentStats = $stmt->fetchAll();

$stmt = $pdo->query("SELECT department, AVG(salary) as avg_salary FROM employees WHERE status = 'active' GROUP BY department ORDER BY avg_salary DESC");
$departmentSalaries = $stmt->fetchAll();

$actionLogs = getActionLogs($pdo, 8);
$stmt = $pdo->query("SELECT * FROM employees ORDER BY created_at DESC LIMIT 5");
$recentEmployees = $stmt->fetchAll();

$deptLabels = json_encode(array_column($departmentStats, 'department'));
$deptCounts = json_encode(array_column($departmentStats, 'count'));
$salaryLabels = json_encode(array_column($departmentSalaries, 'department'));
$salaryCounts = json_encode(array_column($departmentSalaries, 'avg_salary'));

$roleMeta = getRoleMeta($_SESSION['user_role_id'] ?? 0, $_SESSION['user_role_name'] ?? null);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přehled - Správa zaměstnanců</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
        <button class="mobile-menu-toggle">☰</button>
        <div class="sidebar-overlay"></div>
    
    <div class="wrapper">
        <nav class="sidebar">
            <div class="logo"><i class="fas fa-users"></i> Správa zaměstnanců</div>
            <ul class="menu">
                <li><a href="index.php" class="active"><i class="fas fa-chart-line"></i> Přehled</a></li>
                <li><a href="employees/list.php"><i class="fas fa-user-tie"></i> Zaměstnanci</a></li>
                <?php if (canManageEmployees()): ?>
                    <li><a href="employees/create.php"><i class="fas fa-plus"></i> Přidat zaměstnance</a></li>
                                        <li><a href="employees/import.php"><i class="fas fa-download"></i> Import CSV</a></li>
                    <li><a href="audit-log.php"><i class="fas fa-list"></i> Audit log</a></li>
                <?php endif; ?>
                                <li><a href="calendar.php"><i class="fas fa-calendar"></i> Kalendář</a></li>
                                <li><a href="profile.php"><i class="fas fa-cog"></i> Nastavení</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Odhlásit se</a></li>
            </ul>
            <div class="user-info">
                <span class="user-role-badge <?= escape($roleMeta['class']) ?>"><?= escape($roleMeta['label']) ?></span>
                <strong><?= escape($_SESSION['user_name']) ?></strong>
                <small><?= escape($_SESSION['user_email']) ?></small>
                <div class="session-timeout">Čas vypršení relace: 30 min</div>
            </div>
        </nav>

        <main class="content">
            <header class="page-header-actions">
                <div>
                    <h1><i class="fas fa-chart-line"></i> Přehled</h1>
                    <p>Přehled systému správy zaměstnanců</p>
                </div>
            </header>

            <?php displayFlash(); ?>

            <div class="status-legend">
                <span class="status-legend-item"><span class="status-dot active"></span> Aktivní</span>
                <span class="status-legend-item"><span class="status-dot vacation"></span> Dovolená</span>
                <span class="status-legend-item"><span class="status-dot sick_leave"></span> Nemocenská</span>
                <span class="status-legend-item"><span class="status-dot inactive"></span> Neaktivní</span>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <h3>Celkem zaměstnanců</h3>
                        <div class="stat-value" data-count="<?= $totalCount ?>">0</div>
                        <div class="stat-change"><?= $activeCount ?> aktivních</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h3>Aktivní zaměstnanci</h3>
                        <div class="stat-value" data-count="<?= $activeCount ?>">0</div>
                        <div class="stat-change"><?= $totalCount > 0 ? round(($activeCount / $totalCount) * 100, 0) : 0 ?>% pokrytí</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-content">
                        <h3>Průměrný plat</h3>
                        <div class="stat-value" style="font-size: 24px;"><?= ceil($avgSalary / 1000) ?>k</div>
                        <div class="stat-change"><?= formatSalary($avgSalary) ?></div>
                                            <div class="sparkline-container">
                                                <canvas id="salarySparkline" class="sparkline-canvas"></canvas>
                                            </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-content">
                        <h3>Nové nástupy (7 dnů)</h3>
                        <div class="stat-value"><?= $newHiresCurrent ?></div>
                        <div class="stat-change <?= $hireTrendClass ?>"><?= escape(str_replace('oproti minulemu tydnu', 'oproti minulému týdnu', $hireTrendLabel)) ?></div>
                    </div>
                </div>
            </div>

            <div class="grid">
                <div class="card card-chart">
                    <h2 style="margin-bottom: 16px;"><i class="fas fa-chart-bar"></i> Počet zaměstnanců dle oddelení</h2>
                    <div class="chart-wrap" id="departmentChartWrap">
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>

                <div class="card card-chart">
                    <h2 style="margin-bottom: 16px;"><i class="fas fa-chart-pie"></i> Průměrný plat dle oddelení</h2>
                    <div class="chart-wrap" id="salaryChartWrap">
                        <div class="chart-skeleton" aria-hidden="true"></div>
                        <canvas id="salaryChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-list"></i> Poslední aktivita (<?= count($actionLogs) ?> akcí)</h2>
                <?php if (empty($actionLogs)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-clock-rotate-left"></i></div>
                        <h3 class="empty-state-title">Zatím žádná aktivita</h3>
                        <p class="empty-state-description">Jakmile někdo upraví data nebo se přihlásí, uvidíte to zde.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-log">
                        <?php foreach ($actionLogs as $log): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $iconMap = [
                                        'CREATE' => '<i class="fas fa-plus"></i>',
                                        'UPDATE' => '<i class="fas fa-edit"></i>',
                                        'DELETE' => '<i class="fas fa-trash"></i>',
                                        'LOGIN' => '<i class="fas fa-lock"></i>',
                                        'LOGOUT' => '<i class="fas fa-sign-out-alt"></i>',
                                        'EXPORT' => '<i class="fas fa-download"></i>',
                                        'IMPORT' => '<i class="fas fa-upload"></i>',
                                    ];
                                    echo $iconMap[$log['action']] ?? '<i class="fas fa-circle"></i>';
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <strong><?= escape($log['user_name'] ?? 'Systém') ?></strong>
                                    <span class="action-badge"><?= escape($log['action']) ?></span>
                                    <p><?= escape($log['entity_type']) ?> <?= $log['entity_id'] ? '#' . (int) $log['entity_id'] : '' ?></p>
                                    <small><?= escape($log['created_at']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid">
                <div class="card">
                    <h2><i class="fas fa-user-plus"></i> Nedávně přidaní zaměstanci</h2>
                    <?php if (empty($recentEmployees)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-user-plus"></i></div>
                            <h3 class="empty-state-title">Zatím žádní noví zaměstnanci</h3>
                            <p class="empty-state-description">Vytvořte první záznam a přehled začne zobrazovat poslední přírůstky.</p>
                            <?php if (canManageEmployees()): ?>
                                <div class="empty-state-actions">
                                    <a href="employees/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Přidat prvního zaměstnance</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table table-zebra">
                            <tbody>
                                <?php foreach ($recentEmployees as $emp): ?>
                                    <?php $avatar = generateAvatar($emp['first_name'], $emp['last_name']); ?>
                                    <tr>
                                        <td>
                                            <div class="employee-row">
                                                <div class="avatar <?= escape($avatar['color_class']) ?>"><?= escape($avatar['initials']) ?></div>
                                                <div>
                                                    <strong><?= escape($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                                                    <br><small><?= escape($emp['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge"><?= escape($emp['department']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2><i class="fas fa-bolt"></i> Rychlé akce</h2>
                    <div class="quick-actions-grid">
                        <?php if (canManageEmployees()): ?>
                            <a href="employees/create.php" class="action-btn">
                                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                                <div class="action-label">Přidat zaměstnance</div>
                            </a>
                        <?php endif; ?>
                        <a href="employees/list.php" class="action-btn">
                            <div class="action-icon"><i class="fas fa-list"></i></div>
                            <div class="action-label">Zobrazit seznam</div>
                        </a>
                        <?php if (canManageEmployees()): ?>
                            <a href="employees/export.php?export=csv" class="action-btn">
                                <div class="action-icon"><i class="fas fa-file-csv"></i></div>
                                <div class="action-label">Export CSV</div>
                            </a>
                            <a href="employees/export.php?export=pdf" class="action-btn">
                                <div class="action-icon"><i class="fas fa-file-pdf"></i></div>
                                <div class="action-label">Export PDF</div>
                            </a>
                            <a href="audit-log.php" class="action-btn">
                                <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                                <div class="action-label">Audit log</div>
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="action-btn">
                            <div class="action-icon"><i class="fas fa-sign-out-alt"></i></div>
                            <div class="action-label">Odhlásit se</div>
                        </a>
                    </div>

                    <?php if (canManageEmployees()): ?>
                        <div class="demo-panel" style="margin-top: 14px;">
                            <strong><i class="fas fa-clapperboard"></i> Demo mode</strong>
                            <p class="text-muted">Vloží ukázkové akce do audit logu, aby bylo portfolio připravené na prezentaci.</p>
                            <button type="button" id="runDemoScenario" class="btn btn-primary">Spustit demo scénář</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/dashboard.js"></script>
    <script>
        function markChartReady(wrapperId) {
            const wrap = document.getElementById(wrapperId);
            if (wrap) {
                wrap.classList.add('is-ready');
            }
        }

        let deptLabels = <?= $deptLabels ?>;
        let deptCounts = <?= $deptCounts ?>;
        let salaryLabels = <?= $salaryLabels ?>;
        let salaryCounts = <?= $salaryCounts ?>;

        if (!Array.isArray(deptLabels) || deptLabels.length === 0) {
            deptLabels = ['IT', 'HR', 'Prodej', 'Marketing', 'Finance'];
            deptCounts = [2, 1, 2, 1, 1];
        }
        if (!Array.isArray(salaryLabels) || salaryLabels.length === 0) {
            salaryLabels = ['IT', 'Prodej', 'Finance', 'Marketing', 'HR'];
            salaryCounts = [47500, 43000, 47000, 40000, 38000];
        }

        if (window.Chart && document.getElementById('departmentChart')) {
            const departmentCtx = document.getElementById('departmentChart').getContext('2d');
            new Chart(departmentCtx, {
                type: 'bar',
                data: {
                    labels: deptLabels,
                    datasets: [{
                        label: 'Počet zaměstnanců',
                        data: deptCounts,
                        backgroundColor: '#475569',
                        borderColor: '#1e293b',
                        borderWidth: 1,
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
            markChartReady('departmentChartWrap');

            // Inicializovat sparkline (simulovaná data pro poslední 10 týdnů)
            const salaryTrend = [42, 43, 41, 44, 45, 46, 45, 47, 48, Math.round(<?= $avgSalary / 1000 ?>)];
            createSparkline('salarySparkline', salaryTrend, '#16a34a');
        }

        if (window.Chart && document.getElementById('salaryChart')) {
            const salaryCtx = document.getElementById('salaryChart').getContext('2d');
            new Chart(salaryCtx, {
                type: 'line',
                data: {
                    labels: salaryLabels,
                    datasets: [{
                        label: 'Průměrný plat',
                        data: salaryCounts,
                        borderColor: '#334155',
                        backgroundColor: 'rgba(51, 65, 85, 0.15)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
            markChartReady('salaryChartWrap');
        }

        // Fallback: schovat skeletony i kdyby grafy nebyly dostupné
        setTimeout(() => {
            markChartReady('departmentChartWrap');
            markChartReady('salaryChartWrap');
        }, 1000);
    </script>
</body>
</html>
