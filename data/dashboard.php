<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
// Include the database connection config
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/redistribution_helper.php';

/** @var resource|null $conn */

$autoRedistributionMoves = 0;
$autoRedistributionMessage = '';
$redistributionResult = ns_run_smart_redistribution($conn);
if ($redistributionResult === false) {
    error_log('Automatic redistribution failed during dashboard load.');
    $autoRedistributionMessage = 'Automatic redistribution could not be completed at this time.';
} elseif ($redistributionResult > 0) {
    $autoRedistributionMoves = (int)$redistributionResult;
    $autoRedistributionMessage = 'Automatic redistribution moved ' . $autoRedistributionMoves . ' nurse' . ($autoRedistributionMoves === 1 ? '' : 's') . '.';
}

// 1. Fetching data and calculating required workloads using SQL
$excluded = ns_get_excluded_department_names();
$excludedList = "'" . implode("','", array_map('addslashes', $excluded)) . "'";
$query = "USE NurseAllocationDB;
          SELECT 
            d.dept_id,
            d.dept_name AS [Department],
            d.base_ratio AS [BaseRatio],
            d.min_nurses AS [MinNurses],
            ISNULL(n.NurseCount, 0) AS [CurrentNurses],
            ISNULL(p.PatientCount, 0) AS [ActualPatients],
            ISNULL(p.WeightedLoad, 0) AS [WeightedLoad],
            CASE 
              WHEN CEILING(ISNULL(p.WeightedLoad, 0) / d.base_ratio) < d.min_nurses THEN d.min_nurses 
              ELSE CEILING(ISNULL(p.WeightedLoad, 0) / d.base_ratio) 
            END AS [RequiredNurses]
          FROM Departments d
          LEFT JOIN (
              SELECT dept_id, COUNT(*) AS NurseCount
              FROM Nurses
              WHERE status = 'Active'
              GROUP BY dept_id
          ) n ON d.dept_id = n.dept_id
          LEFT JOIN (
              SELECT dept_id, COUNT(*) AS PatientCount, SUM(severity_weight) AS WeightedLoad
              FROM Patients
              GROUP BY dept_id
          ) p ON d.dept_id = p.dept_id
          WHERE d.dept_name NOT IN ($excludedList);";

$stmt = sqlsrv_query($conn, $query);
if ($stmt !== false) { sqlsrv_next_result($stmt); }
if ($stmt === false) {
    error_log("Dashboard query failed: " . print_r(sqlsrv_errors(), true));
    die("<h2 style='color:red; text-align:center; font-family:Arial, sans-serif;'>Unable to load dashboard data right now.</h2>");
}

$departmentsData = [];
$chartLabels = [];
$chartCurrent = [];
$chartRequired = [];

$excluded = ns_get_excluded_department_names();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if (in_array($row['Department'], $excluded, true)) {
        continue;
    }
    $row['Difference'] = $row['CurrentNurses'] - $row['RequiredNurses'];
    $departmentsData[] = $row;
    
    $chartLabels[] = $row['Department'];
    $chartCurrent[] = $row['CurrentNurses'];
    $chartRequired[] = $row['RequiredNurses'];
}

// 2. Fetch all patients with their IDs for the live details section
$patientQuery = "USE NurseAllocationDB;
                 SELECT p.patient_id, p.patient_name, p.severity_level, p.severity_weight, d.dept_name 
                 FROM Patients p
                 JOIN Departments d ON p.dept_id = d.dept_id
                 ORDER BY d.dept_id, p.patient_name;";
$patientStmt = sqlsrv_query($conn, $patientQuery);
if ($patientStmt !== false) { sqlsrv_next_result($patientStmt); }
$patientsList = [];
if ($patientStmt !== false) {
    while ($pRow = sqlsrv_fetch_array($patientStmt, SQLSRV_FETCH_ASSOC)) {
        $patientsList[] = $pRow;
    }
}

// Totals for the hero strip
$totalPatients = 0;
$allPatientQuery = "USE NurseAllocationDB; SELECT COUNT(*) AS TotalPatients FROM Patients;";
$allPatientStmt = sqlsrv_query($conn, $allPatientQuery);
if ($allPatientStmt !== false) { sqlsrv_next_result($allPatientStmt); if ($row = sqlsrv_fetch_array($allPatientStmt, SQLSRV_FETCH_ASSOC)) { $totalPatients = (int)$row['TotalPatients']; }}

$totalNurses = 0;
$allNurseQuery = "USE NurseAllocationDB; SELECT COUNT(*) AS TotalActiveNurses FROM Nurses WHERE status = 'Active';";
$allNurseStmt = sqlsrv_query($conn, $allNurseQuery);
if ($allNurseStmt !== false) { sqlsrv_next_result($allNurseStmt); if ($row = sqlsrv_fetch_array($allNurseStmt, SQLSRV_FETCH_ASSOC)) { $totalNurses = (int)$row['TotalActiveNurses']; }}

$shortfallCount = count(array_filter($departmentsData, fn($d) => $d['Difference'] < 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Ward Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header class="ns-topbar">
    <div class="ns-topbar__inner">
        <nav class="ns-nav">
            <a href="dashboard.php" class="is-active">Dashboard</a>
            <a href="add_patient.php">Register Patient</a>
            <a href="manage_nurses.php">Staff Suite</a>
            <a href="departments.php">Departments</a>
            <a href="redistribute.php">Redistribute</a>
            <a href="renew_data.php">Renew Data</a>
        </nav>
        <div class="ns-topbar__right">
            <a href="logout.php" class="ns-logout" title="Sign out"> Sign out</a>
            <div class="ns-logo-topright">
                <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise">
            </div>
        </div>
    </div>
</header>

<div class="ns-shell">

    <?php if (isset($_GET['allocated']) && $_GET['allocated'] == 'success'): ?>
        <div class="banner banner--success" role="alert">
            <span class="banner__msg"> Optimization executed — nurses were dynamically reallocated inside SQL Server.</span>
            <button type="button" class="banner__close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
        <div class="banner banner--danger" role="alert">
            <span class="banner__msg"> System reset complete — all patient records were purged from SQL Server.</span>
            <button type="button" class="banner__close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['discharge']) && $_GET['discharge'] == 'success'): ?>
        <div class="banner banner--info" role="alert">
            <span class="banner__msg"> Patient discharged — hospital workload metrics have been updated.</span>
            <button type="button" class="banner__close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <?php if ($autoRedistributionMessage !== ''): ?>
        <div class="banner banner--info" role="alert">
            <span class="banner__msg"><?php echo ns_out($autoRedistributionMessage); ?></span>
            <button type="button" class="banner__close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="ns-hero" style="background-image:url('1872912-hospitals-wallpaper.jpg');">
        <div class="ns-hero__content">
            <div class="ns-hero__eyebrow"><span class="dot"></span> Live · SQL Server DAVIDSQLSERVER2</div>
            <h1>Smart Nurse Allocation</h1>
            <p>Patient-load based workforce management — <?php echo (int)$totalPatients; ?> patients across the floor, <?php echo (int)$totalNurses; ?> nurses on duty<?php echo $shortfallCount > 0 ? ", $shortfallCount department(s) under pressure." : ", every department covered."; ?></p>
        </div>
    </div>

    <!-- Stat strip -->
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-card__label">Total Patients</span>
            <span class="stat-card__value"><?php echo (int)$totalPatients; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Nurses On Duty</span>
            <span class="stat-card__value"><?php echo (int)$totalNurses; ?></span>
        </div>
        <div class="stat-card <?php echo $shortfallCount > 0 ? 'stat-card--alert' : 'stat-card--ok'; ?>">
            <span class="stat-card__label">Departments Under Pressure</span>
            <span class="stat-card__value"><?php echo (int)$shortfallCount; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card__label">Departments Tracked</span>
            <span class="stat-card__value"><?php echo count($departmentsData); ?></span>
        </div>
    </div>

    <div class="grid-actions">
        <div class="btn-cluster">
            <a href="add_patient.php" class="btn"><span class="ico"></span> Register New Patient</a>
            <a href="manage_nurses.php" class="btn btn--info"><span class="ico"></span> Staff Management Suite</a>
            <a href="renew_data.php" class="btn btn--danger"><span class="ico"></span> Renew Data</a>
        </div>
        <a href="redistribute.php" class="btn btn--lg btn--pulse"><span class="ico"></span> Execute Smart Redistribution</a>
    </div>
    <div style="font-size:0.9rem; color:var(--slate); text-align:center; margin-top:-12px; margin-bottom:22px;">Automatic redistribution also executes every dashboard refresh.</div>

    <!-- Live Analytics Chart -->
    <div class="card">
        <div class="card__head">
            <h3>Staffing Levels Comparison</h3>
            <span class="tag">live · auto-refreshed on load</span>
        </div>
        <svg class="vitals-rule" viewBox="0 0 600 22" preserveAspectRatio="none"><path d="M0 11 H230 L245 2 L258 20 L270 11 H600" /></svg>
        <div style="max-height: 300px; position: relative;">
            <canvas id="staffingChart"></canvas>
        </div>
    </div>

    <!-- Live Allocation Table -->
    <div class="card">
        <div class="card__head">
            <h3>Live Operational Status</h3>
            <span class="tag"><?php echo count($departmentsData); ?> departments</span>
        </div>
        <div class="table-wrap">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Standard Ratio (1:X)</th>
                        <th>Actual Patients</th>
                        <th>Weighted Load</th>
                        <th>Current Nurses</th>
                        <th>Required Nurses</th>
                        <th>Status / Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departmentsData as $dept): 
                        $statusClass = 'status-row--optimal';
                        $statusText = 'Optimal staffing';
                        
                        if ($dept['Difference'] < 0) {
                            $statusClass = 'status-row--alert';
                            $statusText = 'CRITICAL: shortage of ' . abs($dept['Difference']) . ' nurse(s)';
                        } elseif ($dept['Difference'] > 0) {
                            $statusClass = 'status-row--surplus';
                            $statusText = 'Surplus: ' . $dept['Difference'] . ' available nurse(s)';
                        }
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo ns_out($dept['Department']); ?></td>
                            <td class="mono muted">1 : <?php echo (int)$dept['BaseRatio']; ?></td>
                            <td class="mono"><?php echo (int)$dept['ActualPatients']; ?></td>
                            <td><span class="chip chip--normal"><?php echo number_format($dept['WeightedLoad'], 2); ?></span></td>
                            <td class="mono" style="font-weight:600; color:var(--teal-dark);"><?php echo (int)$dept['CurrentNurses']; ?></td>
                            <td class="mono" style="font-weight:600;"><?php echo (int)$dept['RequiredNurses']; ?></td>
                            <td><div class="status-row <?php echo $statusClass; ?>"><?php echo ns_out($statusText); ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Patient Census Details WITH DISCHARGE BUTTON -->
    <div class="card">
        <div class="card__head">
            <h3> Registered Patients — Detailed Census</h3>
            <span class="tag"><?php echo count($patientsList); ?> registered</span>
        </div>
        <div class="table-wrap">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Assigned Department</th>
                        <th>Severity Level</th>
                        <th>Calculated Weight</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patientsList)): ?>
                        <tr><td colspan="5" class="muted" style="text-align:center; padding:28px;">No patients registered in the system yet. All loads are clean.</td></tr>
                    <?php else: ?>
                        <?php foreach ($patientsList as $p): 
                            $chipClass = 'chip--normal';
                            if ($p['severity_level'] == 'Critical') $chipClass = 'chip--critical';
                            if ($p['severity_level'] == 'Moderate') $chipClass = 'chip--moderate';
                        ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo ns_out($p['patient_name']); ?></td>
                                <td class="muted"><?php echo ns_out($p['dept_name']); ?></td>
                                <td><span class="chip <?php echo $chipClass; ?>"><?php echo ns_out($p['severity_level']); ?></span></td>
                                <td class="mono" style="font-weight:600;"><?php echo number_format($p['severity_weight'], 2); ?></td>
                                <td style="text-align:center;">
                                    <a href="discharge_patient.php?id=<?php echo (int)$p['patient_id']; ?>" class="btn btn--outline-danger btn--sm">
                                         Discharge
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rule-Based Smart Allocation Insights -->
    <div class="card" style="border-color:var(--teal); border-width:1.5px;">
        <div class="card__head">
            <h3> Smart Redistribution Insights</h3>
            <span class="tag">rule-based engine</span>
        </div>
        <svg class="vitals-rule vitals-rule--coral" viewBox="0 0 600 22" preserveAspectRatio="none"><path d="M0 11 H150 L165 2 L178 20 L190 11 H600" /></svg>
        <?php 
        $shortages = [];
        $surpluses = [];

        foreach ($departmentsData as $d) {
            if ($d['Difference'] < 0) $shortages[] = $d;
            if ($d['Difference'] > 0) $surpluses[] = $d;
        }

        if (empty($shortages)) {
            echo '<div class="status-row status-row--optimal" style="font-size:15px; padding:16px;">All departments are safe. No urgent staffing movements required.</div>';
        } else {
            echo '<ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px;">';
            foreach ($shortages as $short) {
                $needed = abs($short['Difference']);
                echo '<li class="status-row status-row--alert" style="text-align:left; font-size:14.5px;"><strong>' . ns_out($short['Department']) . '</strong> is experiencing high workload pressure — needs <strong>' . (int)$needed . '</strong> more nurse(s).</li>';

                foreach ($surpluses as &$surp) {
                    if ($surp['Difference'] > 0 && $needed > 0) {
                        $moveCount = min($needed, $surp['Difference']);
                        echo '<li class="muted" style="padding-left:22px; font-size:13.5px;">➔ Move <strong style="color:var(--ink);">' . (int)$moveCount . '</strong> nurse(s) from <strong style="color:var(--ink);">' . ns_out($surp['Department']) . '</strong> to <strong style="color:var(--ink);">' . ns_out($short['Department']) . '</strong>.</li>';
                        $surp['Difference'] -= $moveCount;
                        $needed -= $moveCount;
                    }
                }
                unset($surp);
            }
            echo '</ul>';
        }
        ?>
    </div>

    <p class="ns-footer">NurseSync Enterprise · Ward Operations Console</p>
</div>

<script>
    const staffingChart = new Chart(document.getElementById('staffingChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Current On-Duty Nurses',
                    data: <?php echo json_encode($chartCurrent); ?>,
                    backgroundColor: 'rgba(14, 124, 123, 0.85)',
                    borderRadius: 6,
                    maxBarThickness: 46
                },
                {
                    label: 'Required Staffing Needed',
                    data: <?php echo json_encode($chartRequired); ?>,
                    backgroundColor: 'rgba(225, 79, 60, 0.85)',
                    borderRadius: 6,
                    maxBarThickness: 46
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: "'IBM Plex Sans', sans-serif" }, boxWidth: 12, padding: 18 } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: "'IBM Plex Sans', sans-serif" } } },
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'IBM Plex Mono', monospace" } }, grid: { color: '#E4ECEB' } }
            }
        }
    });
</script>
</body>
</html>