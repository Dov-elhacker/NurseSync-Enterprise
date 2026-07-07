<?php
/**
 * ONE-TIME USE ONLY.
 * Open this page in your browser, type your desired admin password,
 * copy the generated hash into config.php as NS_ADMIN_PASSWORD_HASH,
 * then DELETE this file from the server.
 */
function ns_out(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$hash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generate Admin Password Hash</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="guard">
    <div class="guard-card" style="text-align:left;">
        <h2 style="margin-bottom:16px;">🔑 Generate Admin Password Hash</h2>
        <p class="muted">Enter the password you want to log in with. This page never stores it — it only prints a hash for you to paste into <code>config.php</code>.</p>

        <form method="POST" style="margin-top:20px;">
            <div class="field">
                <label>New admin password</label>
                <input type="text" name="password" class="input" required placeholder="Choose a strong password">
            </div>
            <button type="submit" class="btn btn--block">Generate Hash</button>
        </form>

        <?php if ($hash): ?>
            <div style="margin-top:22px; padding:16px; background:var(--paper); border-radius:var(--radius-sm); word-break:break-all;">
                <p class="mono" style="font-size:12px; color:var(--slate); margin-bottom:6px;">PASTE THIS INTO config.php:</p>
                <code class="mono" style="font-size:13px;"><?php echo ns_out($hash); ?></code>
            </div>
            <p class="muted" style="margin-top:16px; font-size:13px;">⚠️ Now delete this file (<code>generate_hash.php</code>) from your server — don't leave it deployed.</p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
