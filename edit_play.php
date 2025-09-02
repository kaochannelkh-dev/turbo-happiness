<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) { echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
$mysqli = db_connect();
if (!$mysqli) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }

$user = $_SESSION['user'];
$play_time = $_POST['play_time'] ?? '';
$draw = $_POST['draw'] ?? '';
if (!$play_time || !$draw) { echo json_encode(['ok'=>false,'error'=>'Missing identifiers']); exit; }

// collect items from POST
$items = [];
foreach ($_POST as $k => $v) {
    if (preg_match('/^items\[(\d+)\]\[(.+)\]$/', $k, $m)) {
        $idx = intval($m[1]); $field = $m[2];
        $items[$idx][$field] = $v;
    }
}
ksort($items);


// parse total_bet override if provided
$total_bet_override_raw = $_POST['total_bet'] ?? null;
$total_bet_override = null;
if ($total_bet_override_raw !== null) {
    $total_bet_override = intval(preg_replace('/\D/', '', (string)$total_bet_override_raw));
}

// fetch original totals for this play (existing DB rows)
$stmt = $mysqli->prepare("SELECT SUM(bet) AS totalBet FROM " . TABLE_NAME . " WHERE username = ? AND play_time = ? AND draw = ?");
$stmt->bind_param('sss', $user, $play_time, $draw);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc() ?: ['totalBet'=>0];
$stmt->close();
$original_total_bet = intval($row['totalBet'] ?? 0);

// Update each provided row by id
$updateStmt = $mysqli->prepare("UPDATE " . TABLE_NAME . " SET num = ?, bet = ?, opts = ? WHERE id = ? AND username = ?");
foreach ($items as $it) {
    $db_id = intval($it['db_id'] ?? 0);
    if ($db_id <= 0) continue;
    $num_raw = preg_replace('/\D/','', $it['num_raw'] ?? '');
    $num_padded = $num_raw !== '' ? str_pad($num_raw, 4, '0', STR_PAD_LEFT) : '';
    $bet = intval($it['bet'] ?? 0);
    $label = $it['label'] ?? '';
    $opts = json_encode(['raw'=>$num_raw,'label'=>$label], JSON_UNESCAPED_UNICODE);
    $updateStmt->bind_param('sisss', $num_padded, $bet, $opts, $db_id, $user);
    $updateStmt->execute();
}
$updateStmt->close();

// determine new total_bet for the play from DB (after updates)
$stmt2 = $mysqli->prepare("SELECT SUM(bet) AS totalBet FROM " . TABLE_NAME . " WHERE username = ? AND play_time = ? AND draw = ?");
$stmt2->bind_param('sss', $user, $play_time, $draw);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row2 = $res2->fetch_assoc() ?: ['totalBet'=>0];
$stmt2->close();
$new_total_bet_db = intval($row2['totalBet'] ?? 0);

// if a total_bet override was provided, use it to compute delta (and optionally persist as separate REFUND/adjustment row)
// We'll treat override as the desired new total; compute delta against original_total_bet and adjust balance.
$effective_new_total = ($total_bet_override !== null) ? $total_bet_override : $new_total_bet_db;
$delta = $effective_new_total - $original_total_bet;

// update user's balance by subtracting delta (increase bet => decrease balance; decrease bet => increase balance)
$userRow = get_user_by_username($mysqli, $user);
if (!$userRow) { echo json_encode(['ok'=>false,'error'=>'User not found']); exit; }
$newBalance = intval($userRow['balance']) - $delta;
if ($newBalance < 0) $newBalance = 0;
$ok = update_user_balance($mysqli, intval($userRow['id']), $newBalance);
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'Failed to update balance']); exit; }

// If override provided and differs from DB sum, optionally record an adjustment row to keep history (here we insert an ADJUSTMENT row)
if ($total_bet_override !== null && $total_bet_override !== $new_total_bet_db) {
    $opts = json_encode(['adjustment_of' => $play_time, 'note' => 'total_bet_override'], JSON_UNESCAPED_UNICODE);
    $ins = $mysqli->prepare("INSERT INTO " . TABLE_NAME . " (username, num, bet, draw, win, opts) VALUES (?, 'ADJ', ?, ?, 0, ?)");
    $ins->bind_param('siss', $user, ($total_bet_override - $new_total_bet_db), $draw, $opts);
    $ins->execute();
    $ins->close();
}

// re-query the play rows to build updated play object
$stmt3 = $mysqli->prepare("SELECT id, play_time, num, bet, draw, win, opts FROM " . TABLE_NAME . " WHERE username = ? AND play_time = ? AND draw = ? ORDER BY id ASC");
$stmt3->bind_param('sss', $user, $play_time, $draw);
$stmt3->execute();
$res3 = $stmt3->get_result();
$items_out = [];
$total_bet = 0; $total_win = 0;
while ($row = $res3->fetch_assoc()) {
    $opts = @json_decode($row['opts'] ?? '', true) ?: [];
    $items_out[] = [
        'db_id' => $row['id'],
        'num' => $row['num'],
        'num_raw' => isset($opts['raw']) ? (string)$opts['raw'] : '',
        'label' => isset($opts['label']) ? (string)$opts['label'] : '',
        'bet' => intval($row['bet']),
        'win' => intval($row['win'])
    ];
    $total_bet += intval($row['bet']);
    $total_win += intval($row['win']);
}
$stmt3->close();

$play = [
    'time' => date('H:i:s', strtotime($play_time)),
    'play_time_raw' => $play_time,
    'draw' => $draw,
    'items' => $items_out,
    'total_bet' => $effective_new_total,
    'total_win' => $total_win
];

echo json_encode(['ok'=>true,'play'=>$play,'new_balance'=>$newBalance]);
exit;
?>
