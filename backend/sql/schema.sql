CREATE DATABASE IF NOT EXISTS sales_db;
USE sales_db;

CREATE TABLE IF NOT EXISTS catalog_items (
  item_id VARCHAR(50) PRIMARY KEY,
  item_name VARCHAR(255) NOT NULL,
  cost DECIMAL(10, 2) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_orders (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  transaction_id VARCHAR(50) NOT NULL UNIQUE,
  transaction_datetime DATETIME NOT NULL,
  catalog_item_id VARCHAR(50) NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sales_catalog_item
    FOREIGN KEY (catalog_item_id)
    REFERENCES catalog_items(item_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
);

CREATE INDEX idx_sales_orders_date
  ON sales_orders (transaction_datetime);
