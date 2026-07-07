<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/redistribution_helper.php';

/** @var resource|null $conn */

// The actual reallocation is a state-changing action -> only run on POST + CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute') {
    ns_verify_csrf();

    $redistributionResult = ns_run_smart_redistribution($conn);
    if ($redistributionResult === false) {
        error_log('Redistribution execution failed from manual trigger.');
        die("<h2 style='color:red; text-align:center; font-family:Arial, sans-serif;'>Unable to run redistribution right now.</h2>");
    }

    header('Location: dashboard.php?allocated=success');
    exit();
}

// ---- Otherwise, show the confirmation screen with a live preview ----
$excluded = ns_get_excluded_department_names();
$excludedList = "'" . implode("','", array_map('addslashes', $excluded)) . "'";
$previewQuery = "USE NurseAllocationDB;
          SELECT d.dept_name,
                 d.min_nurses AS [MinNurses],
                 ISNULL(n.NurseCount, 0) AS [CurrentNurses],
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
              SELECT dept_id, SUM(severity_weight) AS WeightedLoad
              FROM Patients
              GROUP BY dept_id
          ) p ON d.dept_id = p.dept_id
          WHERE d.dept_name NOT IN ($excludedList);";
$previewStmt = sqlsrv_query($conn, $previewQuery);
if ($previewStmt !== false) { sqlsrv_next_result($previewStmt); }

$shortagePreview = [];
if ($previewStmt !== false) {
    while ($row = sqlsrv_fetch_array($previewStmt, SQLSRV_FETCH_ASSOC)) {
        $diff = $row['CurrentNurses'] - $row['RequiredNurses'];
        if ($diff < 0) {
            $shortagePreview[] = ['name' => $row['dept_name'], 'needed' => abs($diff)];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Confirm Redistribution</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="guard">
    <div class="guard-card">
        <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise" style="height:20px; margin-bottom:14px;">
        <h2 style="margin-top:14px;"> Confirm Smart Redistribution</h2>
        <p class="muted" style="margin-top:10px;">This will move on-duty nurses between departments to match current patient load.</p>

        <svg class="vitals-rule" viewBox="0 0 400 22" preserveAspectRatio="none"><path d="M0 11 H150 L165 2 L178 20 L190 11 H400" /></svg>

        <?php if (empty($shortagePreview)): ?>
            <div class="status-row status-row--optimal" style="margin-bottom:24px;">All departments are currently staffed at or above requirement — no moves needed.</div>
        <?php else: ?>
            <ul style="list-style:none; margin:0 0 24px; padding:0; text-align:left; display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($shortagePreview as $s): ?>
                    <li class="status-row status-row--alert" style="text-align:left;"><strong><?php echo ns_out($s['name']); ?></strong> needs <strong><?php echo (int)$s['needed']; ?></strong> more nurse(s)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div style="display:flex; justify-content:center; gap:12px;">
            <a href="dashboard.php" class="btn btn--ghost">Cancel</a>
            <form method="POST" action="redistribute.php" style="display:inline;">
                <?php echo ns_csrf_field(); ?>
                <input type="hidden" name="action" value="execute">
                <button type="submit" class="btn"><span class="ico"></span> Run Redistribution Now</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
