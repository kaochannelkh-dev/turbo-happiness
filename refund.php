<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$mysqli = db_connect();
if (!$mysqli) {
    echo json_encode(['ok' => false, 'error' => 'DB unavailable']);
    exit;
}

$username = $_SESSION['user'];
$play_time = $_POST['play_time'] ?? '';
$draw = $_POST['draw'] ?? '';

if (!$play_time || !$draw) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

// prevent duplicate refunds: look for existing refund entry referencing this play_time
$checkStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM " . TABLE_NAME . " WHERE username = ? AND draw = ? AND opts LIKE ?");
$likePattern = '%"refund_of":"' . $mysqli->real_escape_string($play_time) . '"%';
$checkStmt->bind_param('sss', $username, $draw, $likePattern);
$checkStmt->execute();
$res = $checkStmt->get_result();
$row = $res->fetch_assoc();
$checkStmt->close();
if (intval($row['cnt']) > 0) {
    echo json_encode(['ok' => false, 'error' => 'Already refunded']);
    exit;
}

// compute total bet for this play (exclude any refund rows)
$sumStmt = $mysqli->prepare("SELECT SUM(bet) AS totalBet FROM " . TABLE_NAME . " WHERE username = ? AND play_time = ? AND draw = ? AND num != 'REFUND'");
$sumStmt->bind_param('sss', $username, $play_time, $draw);
$sumStmt->execute();
$res = $sumStmt->get_result();
$sRow = $res->fetch_assoc();
$sumStmt->close();

$totalBet = intval($sRow['totalBet'] ?? 0);
if ($totalBet <= 0) {
    echo json_encode(['ok' => false, 'error' => 'No bet found to refund']);
    exit;
}

// update user's balance (add totalBet back)
$userRow = get_user_by_username($mysqli, $username);
if (!$userRow) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}
$newBalance = intval($userRow['balance']) + $totalBet;
$ok = update_user_balance($mysqli, intval($userRow['id']), $newBalance);
if (!$ok) {
    echo json_encode(['ok' => false, 'error' => 'Failed to update balance']);
    exit;
}

// insert a refund record (num = 'REFUND', negative bet) and annotate it with opts.refund_of
$opts = json_encode(['refund_of' => $play_time], JSON_UNESCAPED_UNICODE);
$ins = $mysqli->prepare("INSERT INTO " . TABLE_NAME . " (username, num, bet, draw, win, opts) VALUES (?, 'REFUND', ?, ?, 0, ?)");
$negBet = -$totalBet;
$ins->bind_param('siss', $username, $negBet, $draw, $opts);
$ins->execute();
$ins->close();

// reflect new session balance if desired
$_SESSION['balance'] = $newBalance;

echo json_encode(['ok' => true, 'new_balance' => $newBalance, 'refunded' => $totalBet]);
exit;
