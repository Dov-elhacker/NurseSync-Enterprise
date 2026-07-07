<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';
ns_require_login();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/redistribution_helper.php';

/** @var resource|null $conn */

$message = "";
$messageType = "success";

$validDepts = [];
$departments = [];
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

$validSeverities = ['Normal' => 1.0, 'Moderate' => 1.5, 'Critical' => 2.0];
$selectedSeverity = $_POST['severity_level'] ?? 'Normal';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    ns_verify_csrf();

    $patient_name   = ns_clean($_POST['patient_name'] ?? '');
    $dept_id        = (int)($_POST['dept_id'] ?? 0);
    $severity_level = $_POST['severity_level'] ?? '';

    if ($patient_name === '') {
        $messageType = "danger";
        $message = "Patient name cannot be empty.";
    } elseif (!isset($validDepts[$dept_id])) {
        $messageType = "danger";
        $message = "Please select a valid department.";
    } elseif (!array_key_exists($severity_level, $validSeverities)) {
        $messageType = "danger";
        $message = "Please select a valid severity level.";
    } else {
        $severity_weight = $validSeverities[$severity_level];

        $query = "INSERT INTO Patients (patient_name, dept_id, severity_level, severity_weight)
                  VALUES (?, ?, ?, ?);";

        $params = array($patient_name, $dept_id, $severity_level, $severity_weight);
        $stmt = sqlsrv_query($conn, $query, $params);

        if ($stmt === false) {
            error_log("Add patient failed: " . print_r(sqlsrv_errors(), true));
            $messageType = "danger";
            $message = "Something went wrong while adding this patient. Please try again.";
        } else {
            $message = "Patient added successfully! <a href='dashboard.php' style='text-decoration:underline;'>Go to Dashboard</a>";
            $redistributionResult = ns_run_smart_redistribution($conn);
            if ($redistributionResult === false) {
                error_log('Automatic redistribution failed after adding a patient.');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Register Patient</title>
    <link rel="stylesheet" href="style.css">
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
            <a href="add_patient.php" class="is-active">Register Patient</a>
            <a href="manage_nurses.php">Staff Suite</a>
            <a href="departments.php">Departments</a>
            <a href="redistribute.php">Redistribute</a>
            <a href="renew_data.php">Renew Data</a>
        </nav>
        <a href="logout.php" class="ns-logout" title="Sign out">⏻ Sign out</a>
    </div>
</header>

<div class="ns-shell">

    <div class="ns-hero" style="background-image:url('645625-4k-ultra-hd-hospital-wallpaper-and-background-image.jpg'); min-height:150px;">
        <div class="ns-hero__content">
            <div class="ns-hero__eyebrow"><span class="dot"></span> Patient intake</div>
            <h1>Register New Patient</h1>
            <p>Severity is weighted automatically — the load recalculates the moment this patient is admitted.</p>
        </div>
    </div>

    <div class="center-col">
        <?php if ($message): ?>
            <div class="banner banner--<?php echo ns_out($messageType); ?>">
                <span class="banner__msg"><?php echo ($messageType === 'success' ? ' ' : ' ') . $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card__head">
                <h3>Patient Intake Form</h3>
                <span class="tag">auto-weighted</span>
            </div>
            <svg class="vitals-rule vitals-rule--quiet" viewBox="0 0 600 22" preserveAspectRatio="none"><path d="M0 11 H230 L245 2 L258 20 L270 11 H600" /></svg>

            <form action="add_patient.php" method="POST">
                <?php echo ns_csrf_field(); ?>
                <div class="field">
                    <label for="patient_name">Patient full name</label>
                    <input type="text" class="input" id="patient_name" name="patient_name" required placeholder="e.g., David John" value="<?php echo ns_out($_POST['patient_name'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label for="dept_id">Assign department</label>
                    <select class="input" id="dept_id" name="dept_id" required>
                        <?php if (empty($departments)): ?>
                            <option value="">No departments configured</option>
                        <?php else: ?>
                            <?php foreach ($departments as $deptRow): ?>
                                <option value="<?php echo (int)$deptRow['dept_id']; ?>" <?php echo (isset($_POST['dept_id']) && (int)$_POST['dept_id'] === (int)$deptRow['dept_id']) ? 'selected' : ''; ?>><?php echo ns_out($deptRow['dept_name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="severity_level">Severity triage level</label>
                    <select class="input" id="severity_level" name="severity_level" required>
                        <option value="Normal" <?php echo $selectedSeverity === 'Normal' ? 'selected' : ''; ?>>Normal — weight 1.0</option>
                        <option value="Moderate" <?php echo $selectedSeverity === 'Moderate' ? 'selected' : ''; ?>>Moderate — weight 1.5</option>
                        <option value="Critical" <?php echo $selectedSeverity === 'Critical' ? 'selected' : ''; ?>>Critical — weight 2.0</option>
                    </select>
                    <span class="hint">Weight determines how heavily this patient counts toward the department's required nurse ratio.</span>
                </div>

                <button type="submit" class="btn btn--block btn--lg"><span class="ico"></span> Add Patient &amp; Recalculate Load</button>
            </form>
        </div>
    </div>

    <p class="ns-footer">NurseSync Enterprise · Ward Operations Console</p>
</div>

</body>
</html>
