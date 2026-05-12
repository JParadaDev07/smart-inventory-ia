-- Run this as MySQL root (e.g. mysql -u root -p < database/setup-database.sql)
-- Creates database and user for Smart Inventory AI (local development)

CREATE DATABASE IF NOT EXISTS smart_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'smart_inventory'@'localhost' IDENTIFIED BY 'secret';
CREATE USER IF NOT EXISTS 'smart_inventory'@'127.0.0.1' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON smart_inventory.* TO 'smart_inventory'@'localhost';
GRANT ALL PRIVILEGES ON smart_inventory.* TO 'smart_inventory'@'127.0.0.1';
FLUSH PRIVILEGES;
