<?php
session_start();
// Immediately redirect to login page if not logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';
$mysqli = db_connect();
if ($mysqli) {
    // ensure tables exist on first run
    if (function_exists('ensure_schema')) {
        ensure_schema($mysqli);
    }
}

$user = $_SESSION['user'] ?? null;
$balance = isset($_SESSION['balance']) ? number_format($_SESSION['balance']) . '៛' : null;
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>P99 Lottery</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="assets/style.css">
	<style>
		/* small inline adjustments for the homepage cards */
		.hero-card {
			background: linear-gradient(180deg,#0d66ff 0%, #0033cc 100%);
			border-radius:18px;
			padding:5px;
			margin-bottom:18px;
			box-shadow: 0 6px 18px rgba(0,0,0,0.25);
			color: #ffd800;
			text-align:center;
			font-weight:800;
			font-size:30px;
			letter-spacing:1px;
		}
		.hero-card .logo { width:88px;height:88px;border-radius:50%;background:radial-gradient(circle,#66b2ff,#0044cc);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:900;margin-bottom:10px; }
		.cards-wrap{width:100%;max-width:1100px;margin:18px auto;padding:0 12px}
		
		/* New topbar styles */
		.topbar {
			background-color: #0033cc;
			height: 60px;
			display: flex;
			align-items: center;
			padding: 0 12px;
		}
		.topbar-inner {
			width: 100%;
			max-width: 1080px;
			margin: 0 auto;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.topbar-left {
			display: flex;
			align-items: center;
		}
		.topbar-logo {
			width: 40px;
			height: 40px;
			background: #ffd800;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #0033cc;
			font-weight: 900;
			font-size: 16px;
			margin-right: 12px;
		}
		.topbar-user {
			color: white;
			display: flex;
			flex-direction: column;
		}
		.user-id {
			font-weight: bold;
			font-size: 14px;
			white-space: nowrap;
		}
		.user-balance {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 13px;
		}
		.balance-icon {
			background: #fff;
			border-radius: 50%;
			width: 16px;
			height: 16px;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #0033cc;
			font-weight: bold;
			font-size: 12px;
		}
		.balance-text {
			white-space: nowrap;
		}
		.khmer-text {
			color: #ffd800;
			font-size: 13px;
			margin-left: 8px;
		}
		.menu-button {
			width: 40px;
			height: 40px;
			background: transparent;
			border: none;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			padding: 0;
		}
		.menu-dot {
			width: 5px;
			height: 5px;
			background: white;
			border-radius: 50%;
			margin: 2px;
		}
		@media(max-width:700px){ 
			.hero-card{font-size:20px;padding:18px} 
			.hero-card .logo{width:64px;height:64px} 
		}
	</style>
</head>
<body class="bg-blue">
	<header class="topbar">
		<div class="topbar-inner">
			<div style="display:flex;align-items:center;gap:12px">
				<div class="logo-circle" style="width:48px;height:48px;font-size:18px">P99</div>
				<div style="color:#fff;font-weight:700">P99 Lottery</div>
			</div>

			<div style="display:flex;align-items:center;gap:12px;color:#fff;font-size:14px">
				<?php if ($user): ?>
					<div style="text-align:right">
						<div style="font-weight:700"><?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></div>
						<div style="font-size:12px;color:rgba(255,255,255,0.9)"><?= $balance ?></div>
					</div>
					<form method="post" action="login.php" style="margin:0">
						<button class="btn btn-gray" type="submit" name="logout">Logout</button>
					</form>
				<?php else: ?>
					<a class="btn btn-blue" href="register.php" style="text-decoration:none;color:#fff">Register</a>
					<a class="btn btn-yellow" href="login.php" style="text-decoration:none;color:#000">Login</a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<main class="cards-wrap">
		<a href="play.php" style="text-decoration:none;display:block">
			<div class="hero-card">
				<div class="logo">P99</div>
				<div style="color:#fff;font-size:16px;margin-bottom:6px">KhmerLottery</div>
			</div>
		</a>
        <a href="play.php" style="text-decoration:none;display:block">
			<div class="hero-card">
				<div class="logo">P99</div>
				<div style="color:#fff;font-size:16px;margin-bottom:6px">Minhngocc.net</div>
			</div>
		</a>

        <a href="play.php" style="text-decoration:none;display:block">
			<div class="hero-card">
				<div class="logo">P99</div>
				<div style="color:#fff;font-size:16px;margin-bottom:6px">thinhmamnet.com</div>
			</div>
		</a>

        <a href="play.php" style="text-decoration:none;display:block">
			<div class="hero-card">
				<div class="logo">P99</div>
				<div style="color:#fff;font-size:16px;margin-bottom:6px">Minhngoc.com.vn</div>
			</div>
		</a>
        <a href="play.php" style="text-decoration:none;display:block">
            <div class="hero-card">
                <div class="logo">P99</div>
                <div style="color:#fff;font-size:16px;margin-bottom:6px">XoSoKienThiet.com</div>
            </div>
	</main>
</body>
</html>
            </div>
	</main>
</body>
</html>
