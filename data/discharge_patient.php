<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
require_once __DIR__ . '/db_connect.php';

/** @var resource|null $conn */

// Handle the actual discharge (state-changing -> must be POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute') {
    ns_verify_csrf();

    $patientId = (int)($_POST['id'] ?? 0);
    if ($patientId <= 0) {
        header("Location: dashboard.php");
        exit();
    }

    $deleteQuery = "USE NurseAllocationDB;
                    DELETE FROM Patients WHERE patient_id = ?;";
    $deleteStmt = sqlsrv_query($conn, $deleteQuery, array($patientId));

    if ($deleteStmt === false) {
        error_log("Discharge failed: " . print_r(sqlsrv_errors(), true));
        die("<h2 style='color:red; text-align:center; font-family:Arial, sans-serif;'>Unable to discharge this patient right now.</h2>");
    }
    // Rebalance nurses after a patient is discharged
    if (function_exists('ns_run_smart_redistribution')) {
        $redistributionResult = ns_run_smart_redistribution($conn);
        if ($redistributionResult === false) {
            error_log('Automatic redistribution failed after discharging a patient.');
        }
    }

    header("Location: dashboard.php?discharge=success");
    exit();
}

// Otherwise this is the confirmation screen — need a valid id from the link
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$patientId = (int)$_GET['id'];

// Fetch patient info just to display their name nicely on the discharge screen
$infoQuery = "USE NurseAllocationDB;
              SELECT p.patient_name, d.dept_name 
              FROM Patients p 
              JOIN Departments d ON p.dept_id = d.dept_id 
              WHERE p.patient_id = ?;";
$infoStmt = sqlsrv_query($conn, $infoQuery, array($patientId));
if ($infoStmt !== false) { sqlsrv_next_result($infoStmt); }

$patientName = "Unknown Patient";
$deptName = "Unknown Department";

if ($infoStmt !== false && $row = sqlsrv_fetch_array($infoStmt, SQLSRV_FETCH_ASSOC)) {
    $patientName = $row['patient_name'];
    $deptName = $row['dept_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Medical Discharge</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="guard">
    <div class="guard-card">
        <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:6px;">
            <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise" style="height:20px;">
        </div>
        <h2 style="margin-top:14px;">🏥 Medical Discharge Report</h2>
        <p class="muted" style="margin-top:8px;">Finalizing files and checking the clinical checklist for:</p>

        <div style="background:var(--paper); border-radius:var(--radius-sm); padding:16px; margin:22px 0;">
            <h5 style="font-size:16px; font-weight:700;">Patient: <?php echo ns_out($patientName); ?></h5>
            <small class="muted">Department: <?php echo ns_out($deptName); ?></small>
        </div>

        <svg class="vitals-rule" viewBox="0 0 400 22" preserveAspectRatio="none"><path d="M0 11 H150 L165 2 L178 20 L190 11 H400" /></svg>

        <div style="margin-bottom:26px;">
            <p class="mono" style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; color:var(--slate); margin-bottom:4px;">Generating release code in</p>
            <div id="timer" class="timer">5</div>
        </div>

        <div style="display:flex; justify-content:center; gap:12px;">
            <a href="dashboard.php" class="btn btn--ghost">Cancel Release</a>
            <form id="dischargeForm" method="POST" action="discharge_patient.php" style="display:inline;">
                <?php echo ns_csrf_field(); ?>
                <input type="hidden" name="action" value="execute">
                <input type="hidden" name="id" value="<?php echo (int)$patientId; ?>">
                <button id="btn-discharge" type="submit" class="btn" disabled>
                    Finalize Discharge
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let secondsLeft = 5;
    const timerEl = document.getElementById('timer');
    const dischargeBtn = document.getElementById('btn-discharge');

    const interval = setInterval(() => {
        secondsLeft--;
        timerEl.innerText = secondsLeft;

        if (secondsLeft <= 0) {
            clearInterval(interval);
            timerEl.innerText = "APPROVED";
            timerEl.classList.add('is-ready');
            dischargeBtn.removeAttribute('disabled');
        }
    }, 1000);
</script>

</body>
</html>
