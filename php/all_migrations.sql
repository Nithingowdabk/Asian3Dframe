-- ===============================
-- ADMIN USERS TABLE (for admin login)
-- ===============================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Asian3DFrames: All SQL migrations in one file
-- Run in phpMyAdmin / MySQL client as needed.


USE u470752772_Asian3Dframe;

-- ===============================
-- BASE TABLES (create if missing)
-- ===============================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    old_price DECIMAL(10,2) NULL,
    category ENUM('mobile','normal') NOT NULL DEFAULT 'normal',
    image TEXT NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cod',
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    quantity SMALLINT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    custom_message VARCHAR(500) NULL,
    custom_photo VARCHAR(300) NULL,
    frame_type ENUM('mobile','normal') NOT NULL DEFAULT 'normal',
    frame_size VARCHAR(30) NOT NULL DEFAULT 'A4',
    photo VARCHAR(300) NULL
);

-- ==================================================
-- 1) Categories setup
-- ==================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default categories (only if not present)
INSERT INTO categories (name, image)
SELECT 'Mobile Photo Frame', 'assets/images/cat-classic.jpg'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Mobile Photo Frame');

INSERT INTO categories (name, image)
SELECT 'Normal Photo Frame', 'assets/images/cat-modern.jpg'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Normal Photo Frame');

-- Link products to categories
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS category_id INT DEFAULT NULL;

-- Optional FK (enable if your data is clean)
-- ALTER TABLE products
--   ADD CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES categories(id);


-- ==================================================
-- 2) Convert legacy product category values
-- ==================================================
ALTER TABLE products
    MODIFY COLUMN category ENUM('classic','modern','collage','led','wood','other','mobile','normal') NOT NULL DEFAULT 'normal';

UPDATE products
SET category = CASE
    WHEN category = 'mobile' THEN 'mobile'
    ELSE 'normal'
END;

ALTER TABLE products
    MODIFY COLUMN category ENUM('mobile','normal') NOT NULL DEFAULT 'normal';


-- ==================================================
-- 3) Upgrade base schema to current app expectations
-- ==================================================
ALTER TABLE products
    MODIFY COLUMN name VARCHAR(200) NOT NULL,
    MODIFY COLUMN description TEXT NOT NULL,
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL,
    MODIFY COLUMN image TEXT NOT NULL;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS old_price DECIMAL(10,2) NULL AFTER price,
    ADD COLUMN IF NOT EXISTS category ENUM('mobile','normal') NOT NULL DEFAULT 'normal' AFTER old_price,
    ADD COLUMN IF NOT EXISTS stock INT UNSIGNED NOT NULL DEFAULT 0 AFTER image,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER stock;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'products' AND index_name = 'idx_category'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX idx_category ON products(category)', 'SELECT "idx_category exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS first_name VARCHAR(80) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS last_name VARCHAR(80) NULL AFTER first_name,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER last_name,
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER address,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER city,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NOT NULL DEFAULT 'cod' AFTER notes,
    ADD COLUMN IF NOT EXISTS total DECIMAL(10,2) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending' AFTER total;


UPDATE orders
SET last_name = 'Customer'
WHERE last_name IS NULL OR last_name = '';

UPDATE orders
SET city = 'Unknown'
WHERE city IS NULL OR city = '';


ALTER TABLE orders
    MODIFY COLUMN first_name VARCHAR(80) NOT NULL,
    MODIFY COLUMN last_name VARCHAR(80) NOT NULL,
    MODIFY COLUMN phone VARCHAR(20) NOT NULL,
    MODIFY COLUMN address VARCHAR(255) NOT NULL,
    MODIFY COLUMN city VARCHAR(100) NOT NULL,
    MODIFY COLUMN total DECIMAL(10,2) NOT NULL,
    MODIFY COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_status'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX idx_status ON orders(status)', 'SELECT "idx_status exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_created_at'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX idx_created_at ON orders(created_at)', 'SELECT "idx_created_at exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE order_items
    MODIFY COLUMN order_id INT NOT NULL,
    MODIFY COLUMN product_id INT NULL,
    MODIFY COLUMN quantity SMALLINT NOT NULL DEFAULT 1,
    MODIFY COLUMN photo VARCHAR(300) NULL;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER quantity,
    ADD COLUMN IF NOT EXISTS custom_message VARCHAR(500) NULL AFTER price,
    ADD COLUMN IF NOT EXISTS custom_photo VARCHAR(300) NULL AFTER custom_message,
    ADD COLUMN IF NOT EXISTS frame_type ENUM('mobile','normal') NOT NULL DEFAULT 'normal' AFTER custom_photo,
    ADD COLUMN IF NOT EXISTS frame_size VARCHAR(30) NOT NULL DEFAULT 'A4' AFTER frame_type;

UPDATE order_items
SET custom_photo = photo
WHERE custom_photo IS NULL AND photo IS NOT NULL;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'order_items' AND index_name = 'idx_order_id'
);
SET @sql := IF(@idx_exists = 0, 'CREATE INDEX idx_order_id ON order_items(order_id)', 'SELECT "idx_order_id exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'order_items' AND constraint_name = 'fk_order' AND constraint_type = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE order_items ADD CONSTRAINT fk_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE',
    'SELECT "fk_order exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'order_items' AND constraint_name = 'fk_product' AND constraint_type = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE order_items ADD CONSTRAINT fk_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL',
    'SELECT "fk_product exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ==================================================
-- 4) Global frame pricing table (final definition)
-- ==================================================
-- category_id = 0 is used for global pricing across all categories.
DROP TABLE IF EXISTS category_frame_prices;

CREATE TABLE IF NOT EXISTS category_frame_prices (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL DEFAULT 0,
  frame_type VARCHAR(20) NOT NULL,
  size VARCHAR(50) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_price (category_id, frame_type, size)
);
