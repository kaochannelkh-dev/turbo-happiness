<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
	echo json_encode(['ok'=>false,'error'=>'Not authenticated']);
	exit;
}
$mysqli = db_connect();
if (!$mysqli) {
	echo json_encode(['ok'=>false,'error'=>'DB unavailable']);
	exit;
}

$play_time = $_POST['play_time'] ?? '';
$draw = $_POST['draw'] ?? '';
$num_raw = trim($_POST['num_raw'] ?? '');
$label = trim($_POST['label'] ?? '');
$bet = isset($_POST['bet']) ? intval($_POST['bet']) : 0;

if ($play_time === '' || $draw === '') {
	echo json_encode(['ok'=>false,'error'=>'Missing play_time or draw']);
	exit;
}

$digits = preg_replace('/\D/','',$num_raw);

// If digits present require a positive bet
if ($digits !== '' && $bet <= 0) {
	echo json_encode(['ok'=>false,'error'=>'Bet required for numeric ticket']);
	exit;
}

// prepare values to insert
$num_padded = ($digits !== '') ? str_pad($digits, 4, '0', STR_PAD_LEFT) : '';
$opts = json_encode(['raw' => $digits, 'label' => $label, 'added_via' => 'add_ticket'], JSON_UNESCAPED_UNICODE);

// insert new ticket row
$stmt = $mysqli->prepare("INSERT INTO " . TABLE_NAME . " (username, num, bet, draw, win, opts) VALUES (?, ?, ?, ?, 0, ?)");
if (!$stmt) {
	echo json_encode(['ok'=>false,'error'=>'DB prepare failed']);
	exit;
}
$stmt->bind_param('ssiss', $_SESSION['user'], $num_padded, $bet, $draw, $opts);
$ok = $stmt->execute();
if (!$ok) {
	$error = $stmt->error;
	$stmt->close();
	echo json_encode(['ok'=>false,'error'=>'DB insert failed: '.$error]);
	exit;
}
$insertId = $stmt->insert_id;
$stmt->close();

// respond with the stored ticket data (num_raw, num_padded, bet, label, id)
echo json_encode([
	'ok' => true,
	'insert_id' => $insertId,
	'ticket' => [
		'id' => $insertId,
		'num' => $num_padded,
		'num_raw' => $digits,
		'bet' => $bet,
		'label' => $label,
		'draw' => $draw,
		'play_time' => $play_time
	]
]);
exit;
