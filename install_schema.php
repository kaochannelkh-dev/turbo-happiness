<?php
// Minimal schema installer. Run from CLI or browser once.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// try to include existing config (if present)
$configPath = __DIR__ . '/config.php';
$mysqli = null;
if (file_exists($configPath)) {
	require_once $configPath;
	if (function_exists('db_connect')) {
		$mysqli = @db_connect();
	}
}

// fallback: if db_connect not available, allow setting credentials here
if (!$mysqli) {
	// Adjust these values if you don't have config.php
	$dbHost = '127.0.0.1';
	$dbUser = 'root';
	$dbPass = '';
	$dbName = 'lottery_db';
	$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	if ($mysqli->connect_errno) {
		echo "DB connect failed: " . $mysqli->connect_error . PHP_EOL;
		exit(1);
	}
}

$sql = file_get_contents(__DIR__ . '/sql/create_tables.sql');
if ($sql === false) {
	echo "Cannot read SQL file: sql/create_tables.sql\n";
	exit(1);
}

// execute as multi query
if ($mysqli->multi_query($sql)) {
	do {
		// flush all results
		if ($res = $mysqli->store_result()) {
			$res->free();
		}
	} while ($mysqli->more_results() && $mysqli->next_result());
	echo "Schema created / verified successfully.\n";
} else {
	echo "Schema creation failed: " . $mysqli->error . PHP_EOL;
	exit(1);
}

// optional: if users table empty, create a demo user (uncomment to use)
/*
$check = $mysqli->query("SELECT COUNT(*) AS cnt FROM users");
$row = $check->fetch_assoc();
if (intval($row['cnt']) === 0) {
	$demoPass = password_hash('secret', PASSWORD_DEFAULT);
	$mysqli->prepare("INSERT INTO users (username, password_hash, balance) VALUES (?, ?, ?)")
		->bind_param('ssi', $u = 'demo', $demoPass, $b = 100000)
		->execute();
	echo "Demo user 'demo' created with balance 100000\n";
}
*/

$mysqli->close();
echo "Done.\n";
