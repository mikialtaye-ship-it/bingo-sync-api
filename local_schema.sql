CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT,
    name TEXT,
    balance REAL NOT NULL DEFAULT 0.00,
    balance_imported INTEGER DEFAULT 0,
    mac_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS generated_licenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    alias_name TEXT,
    mode TEXT NOT NULL,
    action_details TEXT NOT NULL,
    mac_address TEXT,
    topup_amount REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
