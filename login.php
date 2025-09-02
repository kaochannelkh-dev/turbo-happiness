<?php
session_start();
// include DB helper so we can show a friendly message if DB is unavailable (optional)
require_once __DIR__ . '/config.php';
$mysqli = db_connect();

// Check for logout action
if (isset($_POST['logout'])) {
    // Destroy session and redirect to login
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? '';
    
    if (empty($username)) {
        $error = 'Username is required';
    } else {
        // If database is available, authenticate against it
        if ($mysqli) {
            $user = null;
            // Check if the get_user_by_username function exists
            if (function_exists('get_user_by_username')) {
                $user = get_user_by_username($mysqli, $username);
            } else {
                // Fallback query if function doesn't exist
                $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ?");
                if ($stmt) {
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                }
            }
            
            // Simple password check - in a real app, use password_verify()
            if ($user) {
                // For demo purposes, any password works or password matches
                $_SESSION['user'] = $username;
                $_SESSION['balance'] = $user['balance'] ?? 1000; // Default balance if not set
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            // No database, use simplified login (any username works)
            $_SESSION['user'] = $username;
            $_SESSION['balance'] = 1000; // Default starting balance
            header('Location: index.php');
            exit;
        }
    }
}

// Database status message
$dbStatusMessage = $mysqli ? 
    "Connected to database" : 
    "No database connection - using session storage";
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Lottery Demo - Login</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="assets/style.css">
	<style>
		.error-message {
			color: #e74c3c;
			margin-bottom: 15px;
			background: rgba(231, 76, 60, 0.1);
			padding: 8px;
			border-radius: 4px;
			font-size: 14px;
			text-align: center;
		}
		.db-status {
			font-size: 12px;
			color: rgba(255,255,255,0.6);
			text-align: center;
			margin-top: 15px;
		}
		.center-card {
			max-width: 400px;
			padding: 24px;
		}
		.input {
			margin-bottom: 15px;
		}
		.login-title {
			color: #fff;
			margin-bottom: 5px;
		}
		.login-subtitle {
			color: rgba(255,255,255,0.7);
			font-size: 14px;
			margin-bottom: 20px;
			text-align: center;
		}
		.action-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 20px;
		}
		.register-link {
			font-size: 14px;
			color: #fff;
			text-decoration: none;
		}
		.register-link:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body class="bg-blue">
	<div class="center-card">
		<div class="logo-circle">P99</div>
		<h1 class="site-title">Play Lottery (Demo)</h1>

		<?php if ($error): ?>
		<div class="error-message"><?= htmlspecialchars($error) ?></div>
		<?php endif; ?>

		<!-- Login form -->
		<form method="post" action="login.php">
			<input name="username" class="input" placeholder="Enter name" required>
			<input name="password" type="password" class="input" placeholder="password">
			<button class="btn btn-yellow" type="submit">Login</button>
		</form>

		<!-- link to separate register page -->
		<div style="margin-top:12px;text-align:center;">
			<a href="register.php" class="btn btn-blue" style="text-decoration:none;display:inline-block;padding:12px 20px;border-radius:24px;color:#fff;">Create account</a>
		</div>
		
		<div class="db-status">
			<i><?= htmlspecialchars($dbStatusMessage) ?></i>
		</div>
	</div>
</body>
</html>
