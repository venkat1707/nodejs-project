-- Migration: add catalog_stock table to track stock levels per catalog item.
-- References catalog_items(item_id); one stock record per catalog item.
USE sales_db;

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
