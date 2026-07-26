-- Migration: add an optional discount (percentage 0-100) to catalog items.
USE sales_db;

ALTER TABLE catalog_items
  ADD COLUMN discount DECIMAL(5, 2) NULL AFTER cost;
