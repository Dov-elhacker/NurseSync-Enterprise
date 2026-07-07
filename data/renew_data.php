<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
require_once __DIR__ . '/db_connect.php';

/** @var resource|null $conn */

// If the user confirmed after the countdown (state-changing -> must be POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm') {
    ns_verify_csrf();

    $query = "USE NurseAllocationDB;
              DELETE FROM Patients;";

    $stmt = sqlsrv_query($conn, $query);

    if ($stmt === false) {
        error_log("Renew data failed: " . print_r(sqlsrv_errors(), true));
        die("<h2 style='color:red; text-align:center; font-family:Arial, sans-serif;'>Unable to reset patient data right now.</h2>");
    }

    header("Location: dashboard.php?reset=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Critical Warning</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="guard">
    <div class="guard-card guard-card--danger">
        <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise" style="height:20px; margin-bottom:14px;">
        <h1 style="color:var(--coral); font-size:26px;"> Critical Warning</h1>
        <h4 style="margin-top:14px; font-weight:600; font-size:16px;">You are about to wipe all registered patient data from the system.</h4>
        <p class="muted" style="margin-top:10px;">This action is irreversible and will reset the active hospital workload metrics to zero.</p>

        <svg class="vitals-rule vitals-rule--coral" viewBox="0 0 400 22" preserveAspectRatio="none"><path d="M0 11 H150 L165 2 L178 20 L190 11 H400" /></svg>

        <div style="margin-bottom:26px;">
            <p class="mono" style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; color:var(--slate); margin-bottom:4px;">System unlocking in</p>
            <div id="countdown" class="timer">5</div>
        </div>

        <div style="display:flex; justify-content:center; gap:12px;">
            <a href="dashboard.php" class="btn btn--ghost btn--lg">Cancel &amp; Go Back</a>
            <form id="renewForm" method="POST" action="renew_data.php" style="display:inline;">
                <?php echo ns_csrf_field(); ?>
                <input type="hidden" name="action" value="confirm">
                <button id="btn-confirm" type="submit" class="btn btn--danger btn--lg" disabled>
                    Confirm &amp; Wipe Data
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let timeLeft = 5;
    const countdownEl = document.getElementById('countdown');
    const confirmBtn = document.getElementById('btn-confirm');

    const timer = setInterval(() => {
        timeLeft--;
        countdownEl.innerText = timeLeft;

        if (timeLeft <= 0) {
            clearInterval(timer);
            countdownEl.innerText = "READY";
            countdownEl.classList.add('is-ready');
            confirmBtn.removeAttribute('disabled');
        }
    }, 1000);
</script>

</body>
</html>
