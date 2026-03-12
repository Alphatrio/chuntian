-- SQLite schema
PRAGMA journal_mode=WAL;

CREATE TABLE IF NOT EXISTS products (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  category TEXT,
  origin TEXT,
  unit TEXT,
  image TEXT,
  price_cents INTEGER NOT NULL,
  old_price_cents INTEGER,
  tags TEXT, -- JSON array
  featured INTEGER DEFAULT 0,
  available INTEGER NOT NULL DEFAULT 1,
  created_at TEXT,
  updated_at TEXT,
  variants TEXT -- JSON array of { "label": "Entière", "price_cents": 500 }
);

CREATE TABLE IF NOT EXISTS orders (
  id TEXT PRIMARY KEY,
  status TEXT NOT NULL,
  mode TEXT NOT NULL,
  slot TEXT,
  customer_name TEXT,
  customer_email TEXT,
  customer_phone TEXT,
  address TEXT,
  cart TEXT NOT NULL, -- JSON
  amount_cents INTEGER NOT NULL,
  notes TEXT,
  contact_consent INTEGER NOT NULL DEFAULT 0,
  stripe_session_id TEXT,
  stripe_payment_intent TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS slots (
  slot_iso TEXT PRIMARY KEY,
  capacity INTEGER DEFAULT 5,
  used INTEGER DEFAULT 0
);

-- Blocages de créneaux par l'admin (ex. tous les matins d'une semaine)
CREATE TABLE IF NOT EXISTS slot_blocks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  date_from TEXT NOT NULL,
  date_to TEXT NOT NULL,
  time_from TEXT NOT NULL,
  time_to TEXT NOT NULL,
  label TEXT,
  created_at TEXT
);