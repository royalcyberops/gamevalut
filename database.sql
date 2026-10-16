-- ============================================================
-- GameVault : database.sql
-- Database : gamevault_db
-- Import via phpMyAdmin > Import > Choose this file > Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS gamevault_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE gamevault_db;

-- ------------------------------------------------------------
-- Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id    INT AUTO_INCREMENT PRIMARY KEY,
  full_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  mobile     VARCHAR(10)  NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash via password_hash()',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: categories (store genres as proper master data)
-- Viva: normalised design — games.category_id references this table,
-- while games.genre is kept as a display copy for simplicity.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50)  NOT NULL UNIQUE,
  slug        VARCHAR(50)  NOT NULL UNIQUE COMMENT 'url-safe: action, rpg...',
  icon        VARCHAR(10)  NOT NULL DEFAULT '🎮' COMMENT 'text/emoji icon, original asset',
  description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: games
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS games (
  game_id          INT AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(150) NOT NULL,
  genre            VARCHAR(50)  NOT NULL COMMENT 'display copy of category name',
  category_id      INT NULL COMMENT 'FK to categories, SET NULL if category deleted',
  platform         VARCHAR(100) NOT NULL COMMENT 'comma separated list',
  price            DECIMAL(10,2) NOT NULL,
  discount_percent INT NOT NULL DEFAULT 0,
  description      TEXT NULL,
  image            VARCHAR(255) NULL DEFAULT NULL COMMENT 'optional file in assets/img/',
  release_date     DATE NULL,
  CONSTRAINT fk_games_category FOREIGN KEY (category_id)
    REFERENCES categories (category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: bookings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  booking_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  game_id        INT NOT NULL,
  quantity       INT NOT NULL DEFAULT 1,
  booking_date   DATE NOT NULL COMMENT 'preferred activation date',
  booking_time   TIME NOT NULL COMMENT 'preferred activation time',
  contact_name   VARCHAR(100) NOT NULL,
  contact_phone  VARCHAR(10)  NOT NULL,
  notes          TEXT NULL,
  booking_status ENUM('Pending','Confirmed','Cancelled') NOT NULL DEFAULT 'Pending',
  total_amount   DECIMAL(10,2) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_game FOREIGN KEY (game_id)
    REFERENCES games (game_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Seed: 7 categories (one per genre) with original emoji icons
-- ------------------------------------------------------------
INSERT INTO categories (name, slug, icon, description) VALUES
('Action', 'action', '⚔️', 'Shooters, samurai combat and high-octane battles.'),
('RPG', 'rpg', '🐉', 'Dragons, quests and 80-hour fantasy epics.'),
('Racing', 'racing', '🏎️', 'Arcade tracks, rally dunes and split-screen chaos.'),
('Sports', 'sports', '⚽', 'Football, cricket and tournament glory.'),
('Strategy', 'strategy', '♟️', 'Sieges, fleets and grand tactical minds.'),
('Horror', 'horror', '👻', 'Haunted stations and deep-sea nightmares.'),
('Indie', 'indie', '🌱', 'Cozy farms and tiny-team space builders.')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ------------------------------------------------------------
-- Sample data: at least 12 games across 7 genres, prices in INR
-- ------------------------------------------------------------
INSERT INTO games (title, genre, platform, price, discount_percent, description, image, release_date) VALUES
('Shadow Strike: Elite Ops', 'Action', 'PC, PS5, Xbox', 1499.00, 50, 'Tactical third-person shooter with co-op missions, stealth routes and explosive set pieces.', 'assets/img/shadow-strike.jpg', '2023-08-15'),
('Elder Realms: Dragonfire', 'RPG', 'PC, PS5', 1999.00, 30, 'Open-world fantasy RPG with dragon taming, branching quests and 80+ hours of story.', 'assets/img/elder-realms.jpg', '2022-11-20'),
('Turbo Velocity GP', 'Racing', 'PC, PS5, Xbox, Switch', 999.00, 20, 'Arcade racing with 40 tracks, split-screen multiplayer and full controller support.', 'assets/img/turbo-velocity.jpg', '2023-03-10'),
('Pro League Football 25', 'Sports', 'PC, PS5, Xbox', 2499.00, 15, 'Realistic football sim with career mode, local clubs pack and online tournaments.', 'assets/img/pro-football.jpg', '2024-01-12'),
('Iron Kingdoms: Siege', 'Strategy', 'PC', 1299.00, 40, 'Medieval real-time strategy with base building, sieges and ranked ladder.', 'assets/img/iron-kingdoms.jpg', '2021-09-05'),
('Nightmare Hollow', 'Horror', 'PC, PS5, Xbox', 1199.00, 50, 'Survival horror in an abandoned hill station. Limited ammo, puzzles and multiple endings.', 'assets/img/nightmare-hollow.jpg', '2023-10-31'),
('Pixel Harvest Tales', 'Indie', 'PC, Switch', 499.00, 10, 'Cozy farming sim with pixel art, festivals and 4-player co-op.', 'assets/img/pixel-harvest.jpg', '2022-05-18'),
('Cyber Ronin 2077', 'Action', 'PC, PS5', 1799.00, 25, 'Neon samurai action with katana combat, hacking and a synthwave city to explore.', 'assets/img/cyber-ronin.jpg', '2023-06-22'),
('Mystic Isles Odyssey', 'RPG', 'PC, Switch', 1599.00, 0, 'Story-rich island adventure with turn-based combat and monster companions.', 'assets/img/mystic-isles.jpg', '2024-02-14'),
('Desert Rally Fury', 'Racing', 'PC, Xbox', 899.00, 60, 'Off-road rally racer across deserts with dune buggies, trucks and time trials.', 'assets/img/desert-rally.jpg', '2021-12-01'),
('Galactic Commanders', 'Strategy', 'PC', 1399.00, 35, 'Sci-fi grand strategy. Command fleets, colonise planets and forge alliances.', 'assets/img/galactic-commanders.jpg', '2022-08-30'),
('Cricket Champions League', 'Sports', 'PC, PS5, Xbox', 1899.00, 20, 'Cricket sim with T20 leagues, realistic physics and India home grounds.', 'assets/img/cricket-league.jpg', '2023-04-07'),
('Echoes of the Deep', 'Horror', 'PC, Switch', 799.00, 0, 'Atmospheric underwater horror mystery with diving, sonar and light management.', 'assets/img/echoes-deep.jpg', '2024-05-25'),
('Starforge Pioneers', 'Indie', 'PC, PS5, Switch', 699.00, 30, 'Relaxing space colony builder from a 3-person indie team. Build, trade, survive.', 'assets/img/starforge.jpg', '2023-09-19');

-- ------------------------------------------------------------
-- Top AAA hits with local cover art (assets/img/*.jpg, Steam CDN headers)
-- ------------------------------------------------------------
INSERT INTO games (title, genre, platform, price, discount_percent, description, image, release_date) VALUES
('Grand Theft Auto V', 'Action', 'PC, PS5, Xbox', 1999.00, 50, 'Open-world crime saga across Los Santos. Heists, free roam and GTA Online with friends.', 'assets/img/gta-v.jpg', '2015-04-14'),
('Palworld', 'Indie', 'PC, Xbox', 1099.00, 20, 'Survive, craft and catch Pals. Build bases, farm and battle in this viral open-world survival hit.', 'assets/img/palworld.jpg', '2024-01-19'),
('Elden Ring', 'RPG', 'PC, PS5, Xbox', 3599.00, 30, 'FromSoftware open-world epic. Explore the Lands Between, conquer demigods and forge your legend.', 'assets/img/elden-ring.jpg', '2022-02-25'),
('Red Dead Redemption 2', 'Action', 'PC, PS5, Xbox', 3199.00, 60, 'Arthur Morgan outlaw saga across the American frontier. Story, hunting and Red Dead Online.', 'assets/img/rdr2.jpg', '2019-12-05'),
('God of War Ragnarok', 'Action', 'PC, PS5', 3999.00, 25, 'Kratos and Atreus face Norse gods. Cinematic combat, realms to explore and father-son story.', 'assets/img/gow-ragnarok.jpg', '2024-09-19'),
('Baldur''s Gate 3', 'RPG', 'PC, PS5', 2999.00, 15, 'GOTY RPG. Deep choices, turn-based combat and co-op adventure in the Forgotten Realms.', 'assets/img/bg3.jpg', '2023-08-03'),
('Cyberpunk 2077', 'RPG', 'PC, PS5, Xbox', 2999.00, 40, 'Night City open world as V. Guns, hacks, romance and the Phantom Liberty expansion.', 'assets/img/cyberpunk.jpg', '2020-12-10'),
('Forza Horizon 5', 'Racing', 'PC, Xbox', 3999.00, 35, 'Mexico festival racer. 500+ cars, dynamic seasons and online convoys.', 'assets/img/forza-h5.jpg', '2021-11-09');

-- Link every game to its category (genre name = category name).
UPDATE games g JOIN categories c ON c.name = g.genre SET g.category_id = c.category_id;

-- ------------------------------------------------------------
-- Shop upgrade: admin flag + orders (see tools/migrate_shop.php,
-- which applies the same changes idempotently to existing installs).
-- Plain ADD COLUMN (no IF NOT EXISTS) for MySQL 8 + MariaDB compat:
-- setup.php early-exits on existing installs, migrate_shop.php checks
-- SHOW COLUMNS first, so this only runs on fresh imports.
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password;

CREATE TABLE IF NOT EXISTS orders (
  order_id       INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  contact_name   VARCHAR(100) NOT NULL,
  contact_phone  VARCHAR(10)  NOT NULL,
  address        TEXT NOT NULL,
  delivery_date  DATE NOT NULL COMMENT 'preferred delivery date',
  delivery_time  TIME NOT NULL COMMENT 'preferred delivery time',
  notes          TEXT NULL,
  payment_method VARCHAR(20) NOT NULL DEFAULT 'CARD',
  order_status   ENUM('Pending','Confirmed','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  total_amount   DECIMAL(10,2) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  item_id    INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  game_id    INT NOT NULL,
  quantity   INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL COMMENT 'discounted unit price at purchase time',
  CONSTRAINT fk_items_order FOREIGN KEY (order_id)
    REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_items_game FOREIGN KEY (game_id)
    REFERENCES games (game_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
