-- Plays table: stores each ticket row. opts holds JSON (raw, label, etc.)
CREATE TABLE IF NOT EXISTS plays (
	id INT AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(191) NOT NULL,
	num VARCHAR(16) NOT NULL DEFAULT '',         -- padded numeric value (e.g. "0012") or '' for letters-only
	num_raw VARCHAR(32) NOT NULL DEFAULT '',     -- raw digits as entered (no padding)
	label VARCHAR(64) NOT NULL DEFAULT '',       -- letters or label entered
	bet INT NOT NULL DEFAULT 0,
	draw VARCHAR(16) NOT NULL DEFAULT '',
	win INT NOT NULL DEFAULT 0,
	opts TEXT DEFAULT NULL,                      -- JSON with extra metadata
	play_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX (username),
	INDEX (play_time),
	INDEX (draw)
);

-- Users table (simple): adapt to your existing users implementation if you already have one.
CREATE TABLE IF NOT EXISTS users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(191) NOT NULL UNIQUE,
	password_hash VARCHAR(255) DEFAULT NULL,
	balance BIGINT NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Optional refunds table to track refund operations (helps prevent duplicates)
CREATE TABLE IF NOT EXISTS refunds (
	id INT AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(191) NOT NULL,
	play_time DATETIME NOT NULL,
	draw VARCHAR(16) NOT NULL,
	amount INT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uniq_refund (username, play_time, draw)
);
