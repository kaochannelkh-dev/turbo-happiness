<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// include DB helper
require_once __DIR__ . '/config.php';
$mysqli = db_connect();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'play') {
    // cart can be provided as JSON (multiple tickets) or single chosen/bet fallback
    $cart_json = $_POST['cart'] ?? '';
    $tickets = [];
    if ($cart_json) {
        $decoded = json_decode($cart_json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $it) {
                // Accept items where client may provide a label-only entry (letters) or digits+bet.
                $raw = $it['num'] ?? '';               // raw entered value (no padding)
                $digits = preg_replace('/\D/', '', $raw); // digits extracted from raw
                $betv = intval($it['bet'] ?? 0);
                $label = $it['label'] ?? $raw;
 
                if ($digits !== '' && $betv > 0) {
                    // numeric ticket with bet
                    $tickets[] = [
                        'num_raw' => $digits, // store raw digits for later display
                        'num' => str_pad($digits, 4, '0', STR_PAD_LEFT), // padded for evaluation/storage
                        'bet' => $betv,
                        'opts' => $it['opts'] ?? [],
                        'label' => $label
                    ];
                } elseif ($digits === '' && trim($label) !== '') {
                    // letters-only ticket, allowed with no bet (bet = 0)
                    $tickets[] = [
                        'num_raw' => '',
                        'num' => '',
                        'bet' => 0,
                        'opts' => $it['opts'] ?? [],
                        'label' => $label
                    ];
                }
                // otherwise ignore malformed item
            }
        }
    } else {
        $chosen = preg_replace('/\D/', '', $_POST['chosen'] ?? '');
        $bet = intval($_POST['bet'] ?? 0);
        if ($chosen !== '' && $bet > 0) {
            $tickets[] = ['num' => str_pad($chosen, 4, '0', STR_PAD_LEFT), 'bet' => $bet, 'opts' => []];
        }
    }

    if (empty($tickets)) {
        $message = 'Enter number(s) and bet amount.';
    } else {
        $totalBet = array_sum(array_column($tickets, 'bet'));
        if ($totalBet > $_SESSION['balance']) {
            $message = 'Insufficient balance for total bet of ' . number_format($totalBet) . '៛';
        } else {
            // single draw for this play
            $draw = str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
            $totalWin = 0;
            $rowsToStore = [];
            foreach ($tickets as $t) {
                // numeric win (existing rules)
                $win_numeric = 0;
                if (!empty($t['num'])) {
                    if ($t['num'] === $draw) {
                        $win_numeric = $t['bet'] * 100;
                    } elseif (substr($t['num'], -2) === substr($draw, -2)) {
                        $win_numeric = $t['bet'] * 5;
                    }
                }

                // letter win: count unique letters A-D in the entered label (raw letters), multiplier = count
                $label = strtoupper(trim($t['label'] ?? ($t['opts']['label'] ?? '')));
                // keep only A,B,C,D and dedupe
                $letters = preg_replace('/[^ABCD]/', '', $label);
                $unique = 0;
                if ($letters !== '') {
                    $chars = str_split($letters);
                    $unique = count(array_unique($chars));
                }
                $win_letters = 0;
                if ($unique > 0) {
                    // single -> x1, pair -> x2, triple -> x3, all four -> x4
                    $win_letters = intval($t['bet']) * $unique;
                }

                // total win for this ticket is numeric + letters
                $win = $win_numeric + $win_letters;
                $totalWin += $win;

                // include raw and label into opts so we can show the original entered value later
                $rowsToStore[] = [
                    'num' => $t['num'],
                    'bet' => $t['bet'],
                    'draw' => $draw,
                    'win' => $win,
                    'opts' => array_merge($t['opts'] ?? [], ['raw' => $t['num_raw'] ?? '', 'label' => $t['label'] ?? ''])
                ];
            }

            // update balance
            $_SESSION['balance'] += ($totalWin - $totalBet);

            // persist entries: try DB, fallback to session history
            if ($mysqli) {
                $stmt = $mysqli->prepare(
                    "INSERT INTO " . TABLE_NAME . " (username, num, bet, draw, win, opts) VALUES (?, ?, ?, ?, ?, ?)"
                );
                if ($stmt) {
                    foreach ($rowsToStore as $r) {
                        // persist opts (contains original raw and label)
                        $opts_json = json_encode($r['opts'], JSON_UNESCAPED_UNICODE);
                        $stmt->bind_param('ssisis', $_SESSION['user'], $r['num'], $r['bet'], $r['draw'], $r['win'], $opts_json);
                        $stmt->execute();
                    }
                    $stmt->close();
                } else {
                    error_log('DB prepare failed: ' . $mysqli->error);
                    // fallback to session
                    foreach ($rowsToStore as $r) {
                        array_unshift($_SESSION['history'], [
                            'time' => date('H:i:s'),
                            'num' => $r['num'],
                            'bet' => $r['bet'],
                            'draw' => $r['draw'],
                            'win' => $r['win']
                        ]);
                    }
                    $_SESSION['history'] = array_slice($_SESSION['history'], 0, 10);
                }
            } else {
                foreach ($rowsToStore as $r) {
                    array_unshift($_SESSION['history'], [
                        'time' => date('H:i:s'),
                        'num' => $r['num'],
                        'bet' => $r['bet'],
                        'draw' => $r['draw'],
                        'win' => $r['win']
                    ]);
                }
                $_SESSION['history'] = array_slice($_SESSION['history'], 0, 10);
            }

            $message = $totalWin > 0 ? "WIN! Draw: $draw, Total Prize: " . number_format($totalWin) . "៛" : "Lose. Draw: $draw";
        }
    }

    // store result message in session and redirect to avoid form resubmission (PRG)
    $_SESSION['flash'] = $message;
    header('Location: play.php');
    exit;
}

// load recent history (DB preferred)
$history = [];
if ($mysqli) {
    // include opts so we can recover the original raw value entered by the user
    $stmt = $mysqli->prepare("SELECT id, play_time, num, bet, draw, win, opts FROM " . TABLE_NAME . " WHERE username = ? ORDER BY id DESC LIMIT 100");
    if ($stmt) {
        $stmt->bind_param('s', $_SESSION['user']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // decode opts to get raw/label if present
            $opts = @json_decode($row['opts'] ?? '', true) ?: [];
            $history[] = [
                'time' => date('H:i:s', strtotime($row['play_time'])),
                'play_time_raw' => $row['play_time'],
                'num'  => $row['num'],
                'num_raw' => isset($opts['raw']) ? (string)$opts['raw'] : '',
                'bet'  => $row['bet'],
                'draw' => $row['draw'],
                'win'  => $row['win'],
                'label'=> isset($opts['label']) ? (string)$opts['label'] : '',
                'db_id' => $row['id']
            ];
        }
        $stmt->close();
    } else {
        error_log('DB history prepare failed: ' . $mysqli->error);
        $history = $_SESSION['history'] ?? [];
    }
} else {
    $history = $_SESSION['history'] ?? [];
}

// After loading $history (from DB or session) build grouped plays (one play = one draw time)
$plays = []; // each play: ['id'=>..., 'time'=>..., 'draw'=>..., 'play_time_raw'=>..., 'items'=>[...], 'total_bet'=>..., 'total_win'=>...]
if (!empty($history)) {
    // group rows by draw + raw play_time to represent one "play" (single draw)
    foreach ($history as $row) {
        $key = ($row['draw'] ?? '') . '|' . ($row['play_time_raw'] ?? '');
        if (!isset($plays[$key])) {
            $plays[$key] = [
                'id' => count($plays) + 1,
                'time' => $row['time'] ?? date('Y-m-d H:i:s'),
                'play_time_raw' => $row['play_time_raw'] ?? '',
                'draw' => $row['draw'] ?? '',
                'items' => [],
                'total_bet' => 0,
                'total_win' => 0
            ];
        }
        $plays[$key]['items'][] = [
            'num' => $row['num'],           // padded numeric value (for evaluation)
            'num_raw' => $row['num_raw'] ?? '', // original entered digits (no padding)
            'label' => $row['label'] ?? '',     // letters/label
            'bet' => $row['bet'],
            'win' => $row['win'],
            'db_id' => $row['db_id'] ?? null    // <-- added db id
        ];
        $plays[$key]['total_bet'] += intval($row['bet']);
        $plays[$key]['total_win'] += intval($row['win']);
    }
    // convert associative to indexed
    $plays = array_values($plays);
} else {
    $plays = [];
}

// small helpers for view
$user = htmlspecialchars($_SESSION['user']);
$balance = number_format($_SESSION['balance']);

// expose plays with their raw play_time for client
$plays_json = json_encode($plays, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Lottery Demo - Play</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="assets/style.css">
	<style>
		/* Enhanced topbar styles */
		.topbar {
			background: linear-gradient(to bottom, #0045e6, #0033cc);
			width: 100%;
			height: 60px;
			display: flex;
			top: 0;
			z-index: 1000;
			box-shadow: 0 2px 8px rgba(0,0,0,0.2);
		}
		
		.topbar-container {
			width: 100%;
			max-width: 1080px;
			height: 100%;
			margin: 0 auto;
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 0 15px;
		}
		
		.topbar-left {
			display: flex;
			align-items: center;
			height: 100%;
		}
		
		.back-button {
			width: 36px;
			height: 36px;
			border-radius: 50%;
			background: rgba(255,255,255,0.15);
			display: flex;
			align-items: center;
			justify-content: center;
			color: white;
			text-decoration: none;
			margin-right: 12px;
		}
		
		.back-button:hover {
			background: rgba(255,255,255,0.25);
		}
		
		.logo-section {
			display: flex;
			align-items: center;
		}
		
		.logo-circle {
			width: 36px;
			height: 36px;
			border-radius: 50%;
			background: #ffd800;
			color: #0033cc;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 900;
			font-size: 16px;
			margin-right: 10px;
		}
		
		.logo-text {
			color: white;
			font-weight: 700;
			font-size: 18px;
		}
		
		.topbar-right {
			display: flex;
			align-items: center;
			height: 100%;
		}
		
		.user-balance {
			display: flex;
			flex-direction: column;
			align-items: flex-end;
		}
		
		.balance-row {
			display: flex;
			align-items: center;
		}
		
		.balance-icon {
			width: 20px;
			height: 20px;
			border-radius: 50%;
			background: white;
			color: #0033cc;
			font-weight: bold;
			font-size: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-right: 6px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.2);
		}
		
		.balance-amount {
			color: white;
			font-size: 14px;
			font-weight: 500;
		}
		
		.balance-dollar {
			color: #ccc;
			font-size: 14px;
			margin-left: 4px;
		}
		
		.khmer-text {
			color: #ffd800;
			font-size: 13px;
			margin-top: 2px;
		}
		
		.menu-button {
			width: 36px;
			height: 36px;
			border-radius: 50%;
			background: rgba(255,255,255,0.1);
			border: none;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			margin-left: 15px;
			cursor: pointer;
		}
		
		.menu-dot {
			width: 4px;
			height: 4px;
			background: white;
			border-radius: 50%;
			margin: 2px;
		}
		
		/* Enhanced Right Panel Styling */
		.right-panel {
			width: 340px;
			background: #0033cc; /* Deep blue background */
			padding: 16px;
			border-radius: 16px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
			color: white;
		}
		
		/* Input styling */
		.input-group {
			margin-bottom: 14px;
		}
		
		.input-group label {
			display: block;
			margin-bottom: 6px;
			font-weight: bold;
			color: #ffffff;
			font-size: 16px;
		}
		
		.input.big {
			width: 100%;
			height: 50px;
			background-color: #f0f0f0;
			border: none;
			border-radius: 8px;
			padding: 8px 12px;
			font-size: 18px;
			font-weight: bold;
			color: #333;
		}
		
		.input.big.active-input {
			background-color: #ffffff;
			box-shadow: 0 0 0 3px rgba(255, 216, 0, 0.5);
		}
		
		/* Options styling */
		.options {
			display: flex;
			flex-wrap: wrap;
			margin-bottom: 16px;
			padding: 10px;
			background: rgba(255, 255, 255, 0.1);
			border-radius: 8px;
		}
		
		.options label {
			margin-right: 15px;
			margin-bottom: 8px;
			display: flex;
			align-items: center;
			cursor: pointer;
			color: #ffffff;
			font-weight: 500;
		}
		
		.options label:last-child {
			width: 100%;
			margin-top: 8px;
			padding-top: 8px;
			border-top: 1px solid rgba(255, 255, 255, 0.2);
			display: flex;
			font-weight: bold;
			color: #ffd800;
		}
		
		.options input[type="checkbox"] {
			margin-right: 5px;
			transform: scale(1.2);
			accent-color: #ffd800;
		}
		
		/* Keypad styling */
		.keypad {
			background: rgba(255, 255, 255, 0.08);
			border-radius: 16px;
			padding: 16px;
			margin-top: 20px;
			box-shadow: inset 0 1px 8px rgba(0, 0, 0, 0.2);
		}
		
		.keypad .row {
			display: flex;
			justify-content: space-between;
			margin-bottom: 10px;
			gap: 8px;
		}
		
		.keypad .key {
			flex: 1;
			height: 48px;
			border-radius: 10px;
			background: rgba(255, 255, 255, 0.95);
			border: none;
			font-size: 18px;
			font-weight: bold;
			cursor: pointer;
			color: #0033cc;
			transition: all 0.2s ease;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
			position: relative;
			overflow: hidden;
		}
		
		.keypad .key::after {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			height: 40%;
			background: linear-gradient(to bottom, rgba(255,255,255,0.4), rgba(255,255,255,0));
			border-radius: 10px 10px 0 0;
			pointer-events: none;
		}
		
		.keypad .letters .key {
			background: linear-gradient(to bottom, #e6e6ff, #d9d9ff);
			color: #3333cc;
		}
		
		.keypad .key:hover {
			background: #ffffff;
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
		}
		
		.keypad .key:active {
			transform: translateY(1px);
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
		}
		
		.keypad .action.key {
			background: linear-gradient(to bottom, #ffd800, #ffcc00);
			color: #0033cc;
			font-weight: 900;
			box-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
		}
		
		.keypad .action.key:hover {
			background: linear-gradient(to bottom, #ffe066, #ffd800);
		}
		
		.keypad .bet-key {
			background: linear-gradient(to bottom, #5cca61, #4caf50);
			color: white;
		}
		
		.keypad .bet-key:hover {
			background: linear-gradient(to bottom, #6bd470, #5cca61);
		}
		
		/* Flow indicators for connected buttons */
		.keypad .row:not(:last-child) {
			position: relative;
		}
		
		.keypad .row:not(:last-child)::after {
			content: '';
			position: absolute;
			bottom: -5px;
			left: 25%;
			right: 25%;
			height: 2px;
			background: rgba(255, 255, 255, 0.2);
			border-radius: 1px;
		}
		
		/* Enhanced Control buttons */
		.row.controls {
			margin-top: 20px;
			gap: 10px;
		}
		
		.btn {
			flex: 1;
			height: 50px;
			border: none;
			border-radius: 10px;
			font-size: 16px;
			font-weight: bold;
			cursor: pointer;
			transition: all 0.2s ease;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			position: relative;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		
		.btn::after {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			height: 40%;
			background: linear-gradient(to bottom, rgba(255,255,255,0.2), rgba(255,255,255,0));
			border-radius: 10px 10px 0 0;
			pointer-events: none;
		}
		
		.btn-teal {
			background: linear-gradient(to bottom, #00b09b, #00897b);
			color: white;
		}
		
		.btn-gray {
			background: linear-gradient(to bottom, #78909c, #607d8b);
			color: white;
		}
		
		.btn-blue {
			background: linear-gradient(to bottom, #42a5f5, #2196f3);
			color: white;
		}
		
		.btn:hover {
			opacity: 0.9;
			transform: translateY(-3px);
			box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
		}
		
		.btn:active {
			transform: translateY(1px);
			opacity: 1;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
		}
		
		/* Add icons to buttons for better visual cues */
		.btn-teal::before {
			content: '▶';
			margin-right: 8px;
			font-size: 14px;
		}
		
		.btn-gray:first-of-type::before {
			content: '✕';
			margin-right: 8px;
			font-size: 14px;
		}
		
		.btn-blue::before {
			content: '+';
			margin-right: 6px;
			font-size: 16px;
		}
		
		#addTicket::before {
			content: '⊕';
			margin-right: 8px;
			font-size: 16px;
		}

		/* Result message styling */
		.result {
			margin-top: 16px;
			padding: 12px;
			background: rgba(255, 255, 255, 0.1);
			border-left: 4px solid #ffd800;
			color: #ffffff;
			border-radius: 4px;
			font-weight: bold;
		}

		/* Preview box styling */
		#previewBox {
			background: #ffffff;
			border: 2px solid var(--yellow);
			color: #000;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
		}
		
		.placeholder {
			height: 600px;
			width: 380px;
			border: 2px solid rgba(255, 255, 255, 0.15);
			border-radius: 12px;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			background: rgba(255, 255, 255, 0.02);
		}
		
		/* Adjust main container to account for fixed topbar */
		.container {
			display: flex;
			max-width: 1080px;
			width: 100%;
			margin-left: auto;
			margin-right: auto;
			padding: 10px 15px;
		}
	</style>
</head>
<body class="bg-blue">
	<!-- Enhanced topbar with functional areas -->
	<header class="topbar">
		<div class="topbar-container">
			<!-- Left side: Back button + Lottery logo -->
			<div class="topbar-left">
				<a href="index.php" class="back-button" title="Back to Home">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<path d="M15 18l-6-6 6-6"/>
					</svg>
				</a>
				<div class="logo-section">
					<div class="logo-circle">8888</div>
					<div class="logo-text">Lottery</div>
				</div>
			</div>
			
			<!-- Right side: User balance information -->
			<div class="topbar-right">
				<div class="user-balance">
					<div class="balance-row">
						<div class="balance-icon">៛</div>
						<span class="balance-amount"><?= $balance ?></span>
						<span class="balance-dollar">/ 0$</span>
					</div>
					<div class="khmer-text">ពាក់យហ្គេម</div>
				</div>
				<button class="menu-button" title="Menu">
					<div class="menu-dot"></div>
					<div class="menu-dot"></div>
					<div class="menu-dot"></div>
				</button>
			</div>
		</div>
	</header>

	<main class="container">
		<section class="left-panel">
			<div id="recentList" class="recent-box" style="background: #fff;padding: 8px;max-width: 300px; width: 100%;height: 700px;border-radius: 8px;color: #000;margin-top: 5px;box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
				<!-- populated by JS -->
			</div>
		</section>
		<section class="middle-panel">
            <div class="time">46</div>
			<!-- Preview / Cart (first box) -->
			<div id="previewBox" class="placeholder">
				<div class="ticket-preview">
					<div class="ticket-preview-empty">
						<div class="magnify">🔍</div>
						<p class="muted">Selected ticket preview</p>
					</div>
				</div>
			</div>

			<div class="history" style="display:none;">
				<!-- legacy: hidden, server data now grouped into plays -->
			</div>
		</section>
		
		<aside class="right-panel">
			<form id="playForm" method="post">
				<div class="input-group">
					<label>ចំនួន (Number)</label>
					<!-- allow typing letters and numbers; limit length to 8 -->
					<input id="chosen" name="chosen" class="input big" maxlength="8" inputmode="text" placeholder="Type numbers or letters" value="">
				</div>

				<div class="input-group">
					<label>ភ្នាល់ (Bet) ៛</label>
					<!-- show 'None' when no bet is entered; value is empty string when no bet -->
					<input id="bet" name="bet" readonly class="input big" placeholder="None">
				</div>

				<div class="options">
					<label><input type="checkbox" name="opt[]" value="A"> A</label>
					<label><input type="checkbox" name="opt[]" value="B"> B</label>
					<label><input type="checkbox" name="opt[]" value="C"> C</label>
					<label><input type="checkbox" name="opt[]" value="D"> D</label>
					<label><input type="checkbox" id="useMultiplier" name="useMultiplier" checked> ប្រើប្រាស់គុណ (X2D/X3D)</label>
				</div>

				<input type="hidden" name="action" value="play">
				<!-- cart data: JSON of tickets -->
				<input type="hidden" name="cart" id="cartData" value="">

				<div class="keypad" id="keypad">
					<!-- letter row -->
					<div class="row letters">
						<button type="button" class="key letter-key">A</button>
						<button type="button" class="key letter-key">B</button>
						<button type="button" class="key letter-key">C</button>
						<button type="button" class="key letter-key">D</button>
						<button type="button" class="key letter-key" data-action="all">ABCD</button>
					</div>

					<!-- numeric rows -->
					<div class="row">
						<button type="button" class="num key" data-func="number-key">7</button>
						<button type="button" class="num key" data-func="number-key">8</button>
						<button type="button" class="num key" data-func="number-key">9</button>
						<button type="button" class="action key" data-action="del">←</button>
					</div>
					<div class="row">
						<button type="button" class="num key" data-func="number-key">4</button>
						<button type="button" class="num key" data-func="number-key">5</button>
						<button type="button" class="num key" data-func="number-key">6</button>
						<button type="button" class="num key" data-func="lo">Lo</button>
					</div>
					<div class="row">
						<button type="button" class="num key" data-func="number-key">1</button>
						<button type="button" class="num key" data-func="number-key">2</button>
						<button type="button" class="num key" data-func="number-key">3</button>
						<button type="button" class="num key" data-func="x">X</button>
					</div>
					<div class="row">
						<button type="button" class="num key" data-func="number-key">0</button>
						<button type="button" class="num key" data-func="number-key">00</button>
						<button type="button" class="num key" data-func="number-key">000</button>
						<button type="button" class="action key" data-action="down">↓</button>
					</div>
					
					<!-- Bet shortcuts row -->
					<div class="row bet-shortcuts">
						<button type="button" class="key bet-key" data-action="bet-preset" data-amount="100">100៛</button>
						<button type="button" class="key bet-key" data-action="bet-preset" data-amount="500">500៛</button>
						<button type="button" class="key bet-key" data-action="bet-preset" data-amount="1000">1000៛</button>
						<button type="button" class="action key" data-action="mode">🔄</button>
					</div>

					<div class="row controls">
						<button type="submit" class="btn btn-teal">Play</button>
						<button type="button" id="clear" class="btn btn-gray">Clear</button>
						<button type="button" id="betAdd" class="btn btn-blue">Bet+100</button>
						<button type="button" id="addTicket" class="btn btn-gray">Add</button>
					</div>
				</div>
			</form>

			<?php if ($message): ?>
				<div class="result"><?= htmlspecialchars($message) ?></div>
			<?php endif; ?>
		</aside>
	</main>

	<!-- Ticket modal (rendered by JS on demand) -->
	<div id="ticketModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:9999;">
		<div id="ticketContent" style="background:#fff;color:#000;border-radius:12px;padding:16px;width:290px;height:600px;margin:20px;overflow:auto;">
			<!-- populated by JS -->
			<button id="closeTicket" style="position:absolute;right:18px;top:12px;background:#e33;border-radius:50%;width:28px;height:28px;border:none;color:#fff;cursor:pointer;">×</button>
		</div>
	</div>

	<script>
	// server-provided plays for JS (includes play_time_raw)
	window.PLAY_HISTORY = <?= $plays_json ?>;
	window.CURRENT_USER = <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>;
	</script>
	<!-- Add HTML2Canvas library for saving ticket as image -->
	<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
	<script src="assets/app.js"></script>
</body>
</html>
