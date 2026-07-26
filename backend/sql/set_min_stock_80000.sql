-- Ensure every catalog item has a stock level of at least 80,000 units.
-- 1) Create stock records (80,000) for catalog items that have none.
-- 2) Raise existing stock records that are below 80,000 up to 80,000.
-- Items already at or above 80,000 are left unchanged (80,000 is a minimum).
USE sales_db;

INSERT INTO catalog_stock (catalog_item_id, units_available)
SELECT ci.item_id, 80000
FROM catalog_items ci
LEFT JOIN catalog_stock cs ON cs.catalog_item_id = ci.item_id
WHERE cs.catalog_item_id IS NULL;

UPDATE catalog_stock
SET units_available = 80000
WHERE units_available < 80000;
