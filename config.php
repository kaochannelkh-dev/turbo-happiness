<?php
// Include DbManager class
require_once __DIR__ . '/DbManager.php';

// DB connection constants - adjust DB_PASS if needed
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', ''); // set DB password if any
define('DB_NAME', 'playthelottery'); // database name
define('TABLE_NAME', 'playthelottery'); // table name
define('USERS_TABLE', 'users');

// Global DbManager instance
$GLOBALS['db_manager'] = null;

/**
 * Get the global DbManager instance
 * 
 * @return DbManager The database manager
 */
function get_db_manager() {
    if ($GLOBALS['db_manager'] === null) {
        $GLOBALS['db_manager'] = new DbManager([
            'host' => DB_HOST,
            'user' => DB_USER,
            'password' => DB_PASS,
            'database' => DB_NAME
        ]);
    }
    return $GLOBALS['db_manager'];
}

/**
 * Connect to database using mysqli (legacy method)
 * 
 * @return mysqli|null Database connection or null on failure
 */
function db_connect() {
    $db = get_db_manager();
    if (!$db->isConnected()) {
        error_log('DB connect error: ' . $db->getLastError());
        return null;
    }
    
    // Return mysqli connection for backward compatibility
    return $db->getConnection();
}

// ensure required tables exist (users + play table)
function ensure_schema($mysqli = null) {
    $db = get_db_manager();
    
    // Create users table
    $db->createTable(USERS_TABLE, [
        "id INT AUTO_INCREMENT",
        "username VARCHAR(100) NOT NULL UNIQUE",
        "password_hash VARCHAR(255) NOT NULL",
        "balance BIGINT NOT NULL DEFAULT 0",
        "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
    ], [
        'primary_key' => 'id'
    ]);

    // Create play history table
    $db->createTable(TABLE_NAME, [
        "id INT AUTO_INCREMENT",
        "username VARCHAR(100) NOT NULL",
        "play_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "num VARCHAR(16) NOT NULL",
        "bet INT NOT NULL",
        "draw VARCHAR(16) NOT NULL",
        "win INT NOT NULL",
        "opts TEXT"
    ], [
        'primary_key' => 'id',
        'indices' => [
            'idx_username' => 'INDEX(username)',
            'idx_play_time' => 'INDEX(play_time)'
        ]
    ]);
}

// get user by username (returns associative array or null)
function get_user_by_username($mysqli = null, $username) {
    $db = get_db_manager();
    return $db->fetchRow(
        "SELECT id, username, password_hash, balance FROM " . USERS_TABLE . " WHERE username = ? LIMIT 1",
        "s",
        [$username]
    );
}

// create a new user; returns new user id or false
function create_user($mysqli = null, $username, $password_hash, $initial_balance = 1000) {
    $db = get_db_manager();
    return $db->insert(USERS_TABLE, [
        'username' => $username,
        'password_hash' => $password_hash,
        'balance' => $initial_balance
    ]);
}

// update user balance (by id)
function update_user_balance($mysqli = null, $userId, $newBalance) {
    $db = get_db_manager();
    $result = $db->update(
        USERS_TABLE,
        ['balance' => $newBalance],
        "id = ?",
        "i",
        [$userId]
    );
    return $result !== false;
}

/*
Recommended table (for manual review):

CREATE TABLE playthelottery (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  play_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  num VARCHAR(16) NOT NULL,
  bet INT NOT NULL,
  draw VARCHAR(16) NOT NULL,
  win INT NOT NULL,
  opts TEXT,
  INDEX(username),
  INDEX(play_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

