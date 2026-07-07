<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/department_defaults.php';

/** @var resource|null $conn */

$message = '';
$messageType = 'success';

ns_seed_default_departments($conn);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ns_verify_csrf();

    if ($_POST['action'] === 'add') {
        $dept_name  = ns_clean($_POST['dept_name'] ?? '');
        $base_ratio = (int)($_POST['base_ratio'] ?? 0);
        $min_nurses = (int)($_POST['min_nurses'] ?? 0);

        if ($dept_name === '') {
            $messageType = 'danger';
            $message = 'Department name cannot be empty.';
        } elseif ($base_ratio < 1) {
            $messageType = 'danger';
            $message = 'Standard ratio must be at least 1.';
        } elseif ($min_nurses < 0) {
            $messageType = 'danger';
            $message = 'Minimum nurses must be zero or more.';
        } else {
            $insertQuery = "INSERT INTO Departments (dept_name, base_ratio, min_nurses)
                            VALUES (?, ?, ?);";
            $stmt = sqlsrv_query($conn, $insertQuery, array($dept_name, $base_ratio, $min_nurses));
            if ($stmt === false) {
                error_log('Add department failed: ' . print_r(sqlsrv_errors(), true));
                $messageType = 'danger';
                $message = 'Unable to add department. Please try again.';
            } else {
                $message = 'Department added successfully.';
            }
        }
    }

    if ($_POST['action'] === 'delete') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        if ($dept_id <= 0) {
            $messageType = 'danger';
            $message = 'Invalid department selected for removal.';
        } else {
            $checkQuery = "SELECT
                             SUM(CASE WHEN n.status = 'Active' THEN 1 ELSE 0 END) AS nurse_count,
                             COUNT(DISTINCT p.patient_id) AS patient_count
                           FROM Departments d
                           LEFT JOIN Nurses n ON d.dept_id = n.dept_id
                           LEFT JOIN Patients p ON d.dept_id = p.dept_id
                           WHERE d.dept_id = ?
                           GROUP BY d.dept_id;";
            $checkStmt = sqlsrv_query($conn, $checkQuery, array($dept_id));

            if ($checkStmt !== false && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
                $nurseCount = (int)$row['nurse_count'];
                $patientCount = (int)$row['patient_count'];
                if ($nurseCount > 0 || $patientCount > 0) {
                    $messageType = 'danger';
                    $message = 'Cannot delete department while it still has active nurses or patients assigned.';
                } else {
                    $deleteQuery = "DELETE FROM Departments WHERE dept_id = ?;";
                    $deleteStmt = sqlsrv_query($conn, $deleteQuery, array($dept_id));
                    if ($deleteStmt === false) {
                        error_log('Delete department failed: ' . print_r(sqlsrv_errors(), true));
                        $messageType = 'danger';
                        $message = 'Unable to delete department. Please try again.';
                    } else {
                        $message = 'Department removed successfully.';
                    }
                }
            } else {
                $messageType = 'danger';
                $message = 'Could not find department or check assignment counts.';
            }
        }
    }
}

$departments = [];
$excluded = ns_get_excluded_department_names();
$excludedList = "'" . implode("','", array_map('addslashes', $excluded)) . "'";
$deptQuery = "SELECT d.dept_id, d.dept_name, d.base_ratio, d.min_nurses,
                     SUM(CASE WHEN n.status = 'Active' THEN 1 ELSE 0 END) AS active_nurses,
                     COUNT(DISTINCT p.patient_id) AS active_patients
              FROM Departments d
              LEFT JOIN Nurses n ON d.dept_id = n.dept_id
              LEFT JOIN Patients p ON d.dept_id = p.dept_id
              WHERE d.dept_name NOT IN ($excludedList)
              GROUP BY d.dept_id, d.dept_name, d.base_ratio, d.min_nurses
              ORDER BY d.dept_name;";
$deptStmt = sqlsrv_query($conn, $deptQuery);
if ($deptStmt !== false) {
    while ($row = sqlsrv_fetch_array($deptStmt, SQLSRV_FETCH_ASSOC)) {
        $departments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Departments</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .overlay-gate {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(11, 31, 43, 0.94);
            color: #fff;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .overlay-card {
            max-width: 420px;
            width: 100%;
            padding: 28px 26px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 56px rgba(1, 31, 52, 0.3);
            backdrop-filter: blur(18px);
        }
        .overlay-card h2 {
            margin-bottom: 14px;
        }
        .overlay-card .timer {
            font-size: 62px;
            font-weight: 700;
            margin: 14px 0 6px;
            color: #F59E0B;
        }
    </style>
</head>
<body>

<header class="ns-topbar">
    <div class="ns-topbar__inner">
        <a href="dashboard.php" class="ns-brand">
            <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise">
            <div class="ns-brand__meta"><small>Ward Operations</small></div>
        </a>
        <nav class="ns-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="add_patient.php">Register Patient</a>
            <a href="manage_nurses.php">Staff Suite</a>
            <a href="departments.php" class="is-active">Departments</a>
            <a href="redistribute.php">Redistribute</a>
            <a href="renew_data.php">Renew Data</a>
        </nav>
        <a href="logout.php" class="ns-logout" title="Sign out">⏻ Sign out</a>
    </div>
</header>

<div class="ns-shell">
    <div class="ns-hero" style="background-image:url('1872912-hospitals-wallpaper.jpg'); min-height:150px;">
        <div class="ns-hero__content">
            <div class="ns-hero__eyebrow"><span class="dot"></span> Department management</div>
            <h1>Manage Departments</h1>
            <p>Create, remove and maintain the department list used by patients and nurse assignments.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="banner banner--<?php echo ns_out($messageType); ?>">
            <span class="banner__msg"><?php echo ns_out($message); ?></span>
        </div>
    <?php endif; ?>

    <div id="removeGate" class="overlay-gate">
        <div class="overlay-card">
            <h2>⚠️ Confirm Department Removal</h2>
            <p class="muted">This action will permanently delete the selected department when the timer expires.</p>
            <div class="timer" id="removeTimer">5</div>
            <p class="muted" id="removeLabel">Department removal begins automatically unless cancelled.</p>
            <button type="button" class="btn btn--ghost" onclick="cancelDepartmentRemoval()">Cancel</button>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h3>Current Departments</h3>
            <span class="tag"><?php echo count($departments); ?> configured</span>
        </div>
        <div class="table-wrap">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Ratio</th>
                        <th>Min Nurses</th>
                        <th>Active Nurses</th>
                        <th>Active Patients</th>
                        <th style="text-align:center;">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr><td colspan="6" class="muted" style="text-align:center; padding:24px;">No departments have been configured yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo ns_out($dept['dept_name']); ?></td>
                                <td><?php echo ns_out($dept['base_ratio']); ?></td>
                                <td><?php echo ns_out($dept['min_nurses']); ?></td>
                                <td><?php echo ns_out($dept['active_nurses']); ?></td>
                                <td><?php echo ns_out($dept['active_patients']); ?></td>
                                <td style="text-align:center;">
                                    <form class="delete-department-form" method="POST" action="departments.php" style="margin:0;">
                                        <?php echo ns_csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="dept_id" value="<?php echo (int)$dept['dept_id']; ?>">
                                        <button type="button" class="btn btn--outline-danger btn--sm" onclick="startDepartmentRemoval(this)">Remove department</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h3>Add New Department</h3>
            <span class="tag">dynamic department list</span>
        </div>
        <form method="POST" action="departments.php">
            <?php echo ns_csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="field">
                <label for="dept_name">Department Name</label>
                <input type="text" class="input" id="dept_name" name="dept_name" required placeholder="e.g. Emergency Room (ER)">
            </div>

            <div class="field">
                <label for="base_ratio">Standard nurse ratio (1 patient per X nurses)</label>
                <input type="number" class="input" id="base_ratio" name="base_ratio" min="1" value="1" required>
            </div>

            <div class="field">
                <label for="min_nurses">Minimum nurses</label>
                <input type="number" class="input" id="min_nurses" name="min_nurses" min="0" value="1" required>
            </div>

            <button type="submit" class="btn btn--block btn--lg">Add Department</button>
        </form>
    </div>
    <script>
        let removeInterval = null;
        let removeForm = null;

        function startDepartmentRemoval(button) {
            removeForm = button.closest('form');
            const gate = document.getElementById('removeGate');
            const timerEl = document.getElementById('removeTimer');
            const label = document.getElementById('removeLabel');

            gate.style.display = 'flex';
            let countdown = 5;
            timerEl.innerText = countdown;
            label.innerText = 'Department removal begins automatically unless cancelled.';

            removeInterval = setInterval(() => {
                countdown -= 1;
                timerEl.innerText = countdown;

                if (countdown <= 0) {
                    clearInterval(removeInterval);
                    removeInterval = null;
                    gate.style.display = 'none';
                    if (removeForm) {
                        removeForm.submit();
                    }
                }
            }, 1000);
        }

        function cancelDepartmentRemoval() {
            const gate = document.getElementById('removeGate');
            gate.style.display = 'none';
            if (removeInterval) {
                clearInterval(removeInterval);
                removeInterval = null;
            }
            removeForm = null;
        }
    </script>
</div>

</body>
</html>
