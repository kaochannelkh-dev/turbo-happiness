<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit;
}
$play_time = $_POST['play_time'] ?? '';
$draw = $_POST['draw'] ?? '';
if (!$play_time || !$draw) {
    echo json_encode(['ok'=>false,'error'=>'Missing parameters']); exit;
}
$mysqli = db_connect();
if (!$mysqli) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }
$user = $_SESSION['user'];

// fetch total bet and total win for these rows (exclude previously inserted REFUND or special markers if any)
$stmt = $mysqli->prepare("SELECT SUM(bet) AS totalBet, SUM(win) AS totalWin FROM " . TABLE_NAME . " WHERE username = ? AND play_time = ? AND draw = ?");
$stmt->bind_param('sss', $user, $play_time, $draw);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc() ?: ['totalBet'=>0,'totalWin'=>0];
$stmt->close();
$totalBet = intval($row['totalBet'] ?? 0);
$totalWin = intval($row['totalWin'] ?? 0);

// compute delta that was applied originally: delta = totalWin - totalBet
$delta = $totalWin - $totalBet;

// update user balance by reverting delta (subtract what was added previously)
$userRow = get_user_by_username($mysqli, $user);
if (!$userRow) { echo json_encode(['ok'=>false,'error'=>'User not found']); exit; }
$newBalance = intval($userRow['balance']) - $delta;
if ($newBalance < 0) $newBalance = 0;
$ok = update_user_balance($mysqli, intval($userRow['id']), $newBalance);
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'Failed update balance']); exit; }

// delete the rows for this play
$del = $mysqli->prepare("DELETE FROM " . TABLE_NAME . " WHERE username = ? AND play_time = ? AND draw = ?");
$del->bind_param('sss', $user, $play_time, $draw);
$del->execute();
$del->close();

// reflect session
$_SESSION['balance'] = $newBalance;

echo json_encode(['ok'=>true,'new_balance'=>$newBalance,'deleted_bet'=>$totalBet,'deleted_win'=>$totalWin]);
exit;
