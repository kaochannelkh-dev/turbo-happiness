<?php
session_start();
require_once __DIR__ . '/config.php';

$mysqli = db_connect();
ensure_schema($mysqli);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Provide username and password.';
    } else {
        // check existing
        $existing = get_user_by_username($mysqli, $username);
        if ($existing) {
            $error = 'Username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $initialBalance = 100000; // adjust initial balance as desired
            $newId = create_user($mysqli, $username, $hash, $initialBalance);
            if ($newId) {
                $_SESSION['user'] = $username;
                $_SESSION['user_id'] = intval($newId);
                $_SESSION['balance'] = intval($initialBalance);
                header('Location: play.php');
                exit;
            } else {
                $error = 'Failed to create account (DB error).';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Register - Play Lottery</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-blue">
	<div class="center-card">
		<div class="logo-circle">P99</div>
		<h1 class="site-title">Create Account</h1>

		<?php if ($error): ?>
			<div style="background:rgba(255,255,255,0.06);padding:10px;border-radius:8px;margin-bottom:10px;color:#fff;">
				<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
			</div>
		<?php endif; ?>

		<form method="post" novalidate>			
			<input name="username" class="input" placeholder="Choose a username" required>
			<input name="password" type="password" class="input" placeholder="Choose a password" required>
			<button class="btn btn-yellow" type="submit">Register</button>
		</form>
		<div style="margin-top:12px;text-align:center;">
			<a href="login.php" class="btn btn-gray" style="text-decoration:none;color:#fff;">Back to Login</a>
		</div>
	</div>
</body>
</html>
