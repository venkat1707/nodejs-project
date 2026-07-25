CREATE DATABASE IF NOT EXISTS sales_db;
USE sales_db;

CREATE TABLE IF NOT EXISTS catalog_items (
  item_id VARCHAR(50) PRIMARY KEY,
  item_name VARCHAR(255) NOT NULL,
  cost DECIMAL(10, 2) NOT NULL,
  discount DECIMAL(5, 2) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_orders (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  transaction_id VARCHAR(50) NOT NULL,
  transaction_datetime DATETIME NOT NULL,
  catalog_item_id VARCHAR(50) NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_transaction_item UNIQUE (transaction_id, catalog_item_id),
  CONSTRAINT fk_sales_catalog_item
    FOREIGN KEY (catalog_item_id)
    REFERENCES catalog_items(item_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
);

CREATE INDEX idx_sales_orders_date
  ON sales_orders (transaction_datetime);

CREATE INDEX idx_sales_orders_transaction
  ON sales_orders (transaction_id);

CREATE TABLE IF NOT EXISTS feedback (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  rating TINYINT NULL,
  improvements TEXT NULL,
  new_features TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_feedback_created
  ON feedback (created_at);

CREATE TABLE IF NOT EXISTS catalog_stock (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  catalog_item_id VARCHAR(50) NOT NULL,
  units_available INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_stock_catalog_item UNIQUE (catalog_item_id),
  CONSTRAINT fk_stock_catalog_item
    FOREIGN KEY (catalog_item_id)
    REFERENCES catalog_items(item_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
);
