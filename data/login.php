<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ns_verify_csrf();

    $username = ns_clean($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // Constant-time-ish check: always run password_verify even on username mismatch
    // to avoid leaking which part was wrong through timing.
    $validUser = hash_equals(NS_ADMIN_USERNAME, $username);
    $validPass = password_verify($password, NS_ADMIN_PASSWORD_HASH);

    if ($validUser && $validPass) {
        session_regenerate_id(true);
        $_SESSION['ns_user'] = $username;
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}

if (ns_is_logged_in()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NurseSync Enterprise — Sign In</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="guard">
    <div class="guard-card" style="max-width:400px;">
        <div style="display:flex; align-items:center; justify-content:center; margin-bottom:10px;">
            <img src="NurseSync-Enterprise-logo.png" alt="NurseSync Enterprise" style="height:26px;">
        </div>
        <h2 style="margin-top:10px;">Ward Operations Console</h2>
        <p class="muted" style="margin-top:8px;">Sign in to manage patients, staff, and allocation.</p>

        <svg class="vitals-rule" viewBox="0 0 400 22" preserveAspectRatio="none"><path d="M0 11 H150 L165 2 L178 20 L190 11 H400" /></svg>

        <?php if ($error): ?>
            <div class="banner banner--danger" style="text-align:left;">
                <span class="banner__msg">⚠️ <?php echo ns_out($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" style="text-align:left;">
            <?php echo ns_csrf_field(); ?>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="input" required autofocus placeholder="admin">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="input" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn--block btn--lg">Sign In</button>
        </form>
    </div>
</div>

</body>
</html>
