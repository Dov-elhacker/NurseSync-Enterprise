<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/redistribution_helper.php';

/** @var resource|null $conn */

$validDepts  = [];
$departments  = [];
$validShifts = [1, 2, 3];
$selectedDept = (int)($_POST['dept_id'] ?? 0);
$selectedShift = (int)($_POST['shift_id'] ?? 2);

$excluded = ['Emergency Room (ER)', 'Inpatient Ward', 'Outpatient Clinic'];
$excludedList = "'" . implode("','", array_map('addslashes', $excluded)) . "'";
$deptQuery = "SELECT dept_id, dept_name FROM Departments WHERE dept_name NOT IN ($excludedList) ORDER BY dept_name;";
$deptStmt = sqlsrv_query($conn, $deptQuery);
if ($deptStmt !== false) {
    while ($row = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
        $departments[] = $row;
        $validDepts[(int)$row['dept_id']] = $row['dept_name'];
    }
}

// 1. Process Actions (Insert / Delete) — both are state-changing, so both require POST + CSRF
$actionType = '';
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ns_verify_csrf();

    if ($_POST['action'] === 'add') {
        $name  = ns_clean($_POST['nurse_name'] ?? '');
        $dept  = (int)($_POST['dept_id'] ?? 0);
        $shift = (int)($_POST['shift_id'] ?? 0);
        $hours = (int)($_POST['hours_worked'] ?? 0);

        if ($name === '') {
            $formError = 'Nurse name cannot be empty.';
        } elseif (!isset($validDepts[$dept])) {
            $formError = 'Please select a valid department.';
        } elseif (!in_array($shift, $validShifts, true)) {
            $formError = 'Please select a valid shift.';
        } elseif ($hours < 1 || $hours > 16) {
            $formError = 'Hours worked must be between 1 and 16.';
        } else {
            $deptName = $validDepts[$dept] ?? '';
            if (stripos($deptName, 'Emergency') !== false) {
                $specialty = 'Critical Care';
            } elseif (stripos($deptName, 'Outpatient') !== false) {
                $specialty = 'Outpatient Care';
            } else {
                $specialty = 'General Care';
            }

            $query = "USE NurseAllocationDB;
                      INSERT INTO Nurses (nurse_name, dept_id, status, shift_id, shift_hours_worked, specialty) 
                      VALUES (?, ?, 'Active', ?, ?, ?);";
            $stmt = sqlsrv_query($conn, $query, array($name, $dept, $shift, $hours, $specialty));
            if ($stmt !== false) { sqlsrv_next_result($stmt); }

            if ($stmt === false) {
                error_log("Add nurse failed: " . print_r(sqlsrv_errors(), true));
                $formError = 'Something went wrong while registering this nurse. Please try again.';
            } else {
                $actionType = 'Added';
                $redistributionResult = ns_run_smart_redistribution($conn);
                if ($redistributionResult === false) {
                    error_log('Automatic redistribution failed after adding a nurse.');
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $nid = (int)($_POST['nurse_id'] ?? 0);
        if ($nid > 0) {
            $query = "USE NurseAllocationDB;
                      DELETE FROM Nurses WHERE nurse_id = ?;";
            $stmt = sqlsrv_query($conn, $query, array($nid));
            if ($stmt !== false) { sqlsrv_next_result($stmt); }

            if ($stmt === false) {
                error_log("Delete nurse failed: " . print_r(sqlsrv_errors(), true));
                $formError = 'Something went wrong while removing this nurse. Please try again.';
            } else {
                $actionType = 'Removed';
                // After removing a nurse, rebalance staff automatically
                $redistributionResult = ns_run_smart_redistribution($conn);
                if ($redistributionResult === false) {
                    error_log('Automatic redistribution failed after removing a nurse.');
                }
            }
        }
    }
}

// 2. Fetch all nurses with their shift mappings and calculated payroll bonuses
$fetchQuery = "USE NurseAllocationDB;
               SELECT n.nurse_id, n.nurse_name, n.shift_id, n.shift_hours_worked, d.dept_name
               FROM Nurses n
               JOIN Departments d ON n.dept_id = d.dept_id
               ORDER BY n.shift_id, n.nurse_name;";
$stmt = sqlsrv_query($conn, $fetchQuery);
if ($stmt !== false) { sqlsrv_next_result($stmt); }
$nursesList = [];
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $nursesList[] = $row;
    }
}

$shiftNames = [
    1 => " Night Shift (12 AM – 8 AM)",
    2 => " Morning Shift (8 AM – 4 PM)",
    3 => " Evening Shift (4 PM – 12 AM)"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Staff Management Suite</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .overlay-gate{
            display:none; position:fixed; inset:0; z-index:9999;
            background:rgba(11,31,43,0.96);
            color:#fff; justify-content:center; align-items:center; text-align:center;
        }
        .overlay-gate .timer{ color:var(--coral); }
        @media (min-width:992px){
            .suite-grid{ display:grid; grid-template-columns:360px 1fr; gap:26px; align-items:start; }
        }
    </style>
</head>
<body>

<!-- 5-Second Processing Gate Animation Overlay -->
<div id="processingGate" class="overlay-gate">
    <div>
        <h1 style="color:#F2C14E; font-size:24px;"> Secure Transaction Gateway</h1>
        <h3 id="gateMessage" style="font-weight:500; color:#DCEBEA; margin-top:10px;">Updating SQL Server live registry…</h3>
        <div class="timer" id="gateTimer" style="margin:26px 0;">5</div>
        <p class="muted" style="color:#8FAAB2;">Verifying staff credentials and compliance metrics.</p>
    </div>
</div>

<header class="ns-topbar">
    <div class="ns-topbar__inner">
        <a href="dashboard.php" class="ns-brand">
            <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise">
            <div class="ns-brand__meta"><small>Ward Operations</small></div>
        </a>
        <nav class="ns-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="add_patient.php">Register Patient</a>
            <a href="manage_nurses.php" class="is-active">Staff Suite</a>
            <a href="departments.php">Departments</a>
            <a href="redistribute.php">Redistribute</a>
            <a href="renew_data.php">Renew Data</a>
        </nav>
        <a href="logout.php" class="ns-logout" title="Sign out">⏻ Sign out</a>
    </div>
</header>

<div class="ns-shell">

    <div class="ns-hero" style="background-image:url('1433435-healthcare-wallpaper.jpg'); min-height:150px;">
        <div class="ns-hero__content">
            <div class="ns-hero__eyebrow"><span class="dot"></span> Staff registry</div>
            <h1>Hospital Staff Management Suite</h1>
            <p>Shift assignment, overtime tracking, and automated payroll bonus calculation.</p>
        </div>
    </div>

    <?php if (!empty($actionType)): ?>
        <div class="banner banner--success">
            <span class="banner__msg"> Transaction successful — nurse record dynamically <?php echo strtolower($actionType); ?>.</span>
        </div>
    <?php endif; ?>

    <?php if ($formError): ?>
        <div class="banner banner--danger">
            <span class="banner__msg"> <?php echo ns_out($formError); ?></span>
        </div>
    <?php endif; ?>

    <div class="suite-grid">
        <!-- Add New Nurse Form Card -->
        <div class="card">
            <div class="card__head">
                <h3>Hire / Register Nurse</h3>
            </div>
            <svg class="vitals-rule vitals-rule--quiet" viewBox="0 0 400 22" preserveAspectRatio="none"><path d="M0 11 H150 L165 2 L178 20 L190 11 H400" /></svg>

            <form id="addNurseForm" method="POST" action="manage_nurses.php">
                <?php echo ns_csrf_field(); ?>
                <input type="hidden" name="action" value="add">

                <div class="field">
                    <label>Full name</label>
                    <input type="text" name="nurse_name" class="input" required placeholder="Nurse name" value="<?php echo ns_out($_POST['nurse_name'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label>Assign initial department</label>
                    <select name="dept_id" class="input" required>
                        <?php if (empty($departments)): ?>
                            <option value="">No departments configured</option>
                        <?php else: ?>
                            <?php foreach ($departments as $deptRow): ?>
                                <option value="<?php echo (int)$deptRow['dept_id']; ?>" <?php echo $selectedDept === (int)$deptRow['dept_id'] ? 'selected' : ''; ?>><?php echo ns_out($deptRow['dept_name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Assigned work shift</label>
                    <select name="shift_id" class="input" required>
                        <option value="1" <?php echo $selectedShift === 1 ? 'selected' : ''; ?>>Night Shift (12 AM – 8 AM)</option>
                        <option value="2" <?php echo $selectedShift === 2 ? 'selected' : ''; ?>>Morning Shift (8 AM – 4 PM)</option>
                        <option value="3" <?php echo $selectedShift === 3 ? 'selected' : ''; ?>>Evening Shift (4 PM – 12 AM)</option>
                    </select>
                </div>

                <div class="field">
                    <label>Hours worked this shift</label>
                    <input type="number" name="hours_worked" class="input" value="8" min="1" max="16" required>
                    <span class="hint">Standard shift is 8 hours. 8+ hours triggers overtime.</span>
                </div>

                <button type="button" onclick="triggerGate('add')" class="btn btn--info btn--block">
                    <span class="ico"></span> Confirm Registration
                </button>
            </form>
        </div>

        <!-- Live Registry Table Card -->
        <div class="card">
            <div class="card__head">
                <h3>Live Shift Registry</h3>
                <span class="tag"><?php echo count($nursesList); ?> on-duty</span>
            </div>
            <div class="table-wrap" style="max-height:520px; overflow-y:auto;">
                <table class="ns-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Current Station</th>
                            <th>Shift Timeline</th>
                            <th>Hours</th>
                            <th>Payroll Bonus</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nursesList)): ?>
                            <tr><td colspan="6" class="muted" style="text-align:center; padding:28px;">No nurses on registry yet. Hire your first nurse using the form.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($nursesList as $n): 
                            $isOvertime = $n['shift_hours_worked'] > 8;
                            $bonusText = $isOvertime ? " +0.25 Day Salary (OT)" : "Standard Rate";
                            $rowClass = $isOvertime ? "is-overtime" : "";
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td style="font-weight:600;"><?php echo ns_out($n['nurse_name']); ?></td>
                                <td class="muted"><?php echo ns_out($n['dept_name']); ?></td>
                                <td><small><?php echo ns_out($shiftNames[$n['shift_id']] ?? 'Unassigned'); ?></small></td>
                                <td><span class="chip chip--normal"><?php echo (int)$n['shift_hours_worked']; ?> hrs</span></td>
                                <td style="color:<?php echo $isOvertime ? 'var(--coral-dark)' : 'var(--teal-dark)'; ?>; font-weight:600;"><?php echo $bonusText; ?></td>
                                <td style="text-align:center;">
                                    <form method="POST" action="manage_nurses.php" class="inline-form">
                                        <?php echo ns_csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="nurse_id" value="<?php echo (int)$n['nurse_id']; ?>">
                                        <button type="button" onclick="triggerGate('delete', this)" class="btn btn--danger btn--sm">Terminate</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="ns-footer">NurseSync Enterprise · Ward Operations Console</p>
</div>

<script>
function triggerGate(type, btnEl = null) {
    const gate = document.getElementById('processingGate');
    const msg = document.getElementById('gateMessage');
    const timerEl = document.getElementById('gateTimer');

    msg.innerText = type === 'add' ? "Securing nurse credentials & shift allocation…" : "Purging staff profile from database registry…";
    gate.style.display = 'flex';

    let countdown = 5;
    timerEl.innerText = countdown;

    const counter = setInterval(() => {
        countdown--;
        timerEl.innerText = countdown;
        if (countdown <= 0) {
            clearInterval(counter);
            if (type === 'add') {
                document.getElementById('addNurseForm').submit();
            } else {
                btnEl.closest('form').submit();
            }
        }
    }, 1000);
}
</script>
</body>
</html>
