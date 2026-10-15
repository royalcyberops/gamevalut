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
('Shadow Strike: Elite Ops', 'Action', 'PC, PS5, Xbox', 1499.00, 50, 'Tactical third-person shooter with co-op missions, stealth routes and explosive set pieces.', NULL, '2023-08-15'),
('Elder Realms: Dragonfire', 'RPG', 'PC, PS5', 1999.00, 30, 'Open-world fantasy RPG with dragon taming, branching quests and 80+ hours of story.', NULL, '2022-11-20'),
('Turbo Velocity GP', 'Racing', 'PC, PS5, Xbox, Switch', 999.00, 20, 'Arcade racing with 40 tracks, split-screen multiplayer and full controller support.', NULL, '2023-03-10'),
('Pro League Football 25', 'Sports', 'PC, PS5, Xbox', 2499.00, 15, 'Realistic football sim with career mode, local clubs pack and online tournaments.', NULL, '2024-01-12'),
('Iron Kingdoms: Siege', 'Strategy', 'PC', 1299.00, 40, 'Medieval real-time strategy with base building, sieges and ranked ladder.', NULL, '2021-09-05'),
('Nightmare Hollow', 'Horror', 'PC, PS5, Xbox', 1199.00, 50, 'Survival horror in an abandoned hill station. Limited ammo, puzzles and multiple endings.', NULL, '2023-10-31'),
('Pixel Harvest Tales', 'Indie', 'PC, Switch', 499.00, 10, 'Cozy farming sim with pixel art, festivals and 4-player co-op.', NULL, '2022-05-18'),
('Cyber Ronin 2077', 'Action', 'PC, PS5', 1799.00, 25, 'Neon samurai action with katana combat, hacking and a synthwave city to explore.', NULL, '2023-06-22'),
('Mystic Isles Odyssey', 'RPG', 'PC, Switch', 1599.00, 0, 'Story-rich island adventure with turn-based combat and monster companions.', NULL, '2024-02-14'),
('Desert Rally Fury', 'Racing', 'PC, Xbox', 899.00, 60, 'Off-road rally racer across deserts with dune buggies, trucks and time trials.', NULL, '2021-12-01'),
('Galactic Commanders', 'Strategy', 'PC', 1399.00, 35, 'Sci-fi grand strategy. Command fleets, colonise planets and forge alliances.', NULL, '2022-08-30'),
('Cricket Champions League', 'Sports', 'PC, PS5, Xbox', 1899.00, 20, 'Cricket sim with T20 leagues, realistic physics and India home grounds.', NULL, '2023-04-07'),
('Echoes of the Deep', 'Horror', 'PC, Switch', 799.00, 0, 'Atmospheric underwater horror mystery with diving, sonar and light management.', NULL, '2024-05-25'),
('Starforge Pioneers', 'Indie', 'PC, PS5, Switch', 699.00, 30, 'Relaxing space colony builder from a 3-person indie team. Build, trade, survive.', NULL, '2023-09-19');

-- Link every game to its category (genre name = category name).
UPDATE games g JOIN categories c ON c.name = g.genre SET g.category_id = c.category_id;
