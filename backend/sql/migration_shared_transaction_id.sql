-- Migration: allow multiple line items to share one transaction_id per order.
-- Replaces the UNIQUE(transaction_id) constraint with a composite
-- UNIQUE(transaction_id, catalog_item_id) constraint.
USE sales_db;

-- Drop the old single-column unique key on transaction_id.
-- MySQL created it as an index named `transaction_id`.
ALTER TABLE sales_orders
  DROP INDEX transaction_id;

-- Add the composite unique key so an order can contain several distinct items.
ALTER TABLE sales_orders
  ADD CONSTRAINT uq_transaction_item UNIQUE (transaction_id, catalog_item_id);

-- Helpful index for grouping/filtering by transaction.
CREATE INDEX idx_sales_orders_transaction
  ON sales_orders (transaction_id);
