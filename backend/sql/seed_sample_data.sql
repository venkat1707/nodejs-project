USE sales_db;

START TRANSACTION;

SET @old_fk_checks = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE sales_orders;
TRUNCATE TABLE catalog_items;
SET FOREIGN_KEY_CHECKS = @old_fk_checks;

INSERT INTO catalog_items (item_id, item_name, cost)
WITH digits AS (
  SELECT 0 AS d
  UNION ALL SELECT 1
  UNION ALL SELECT 2
  UNION ALL SELECT 3
  UNION ALL SELECT 4
  UNION ALL SELECT 5
  UNION ALL SELECT 6
  UNION ALL SELECT 7
  UNION ALL SELECT 8
  UNION ALL SELECT 9
),
catalog_seq AS (
  SELECT (d3.d * 100 + d2.d * 10 + d1.d + 1) AS seq
  FROM digits d1
  CROSS JOIN digits d2
  CROSS JOIN digits d3
)
SELECT
  CONCAT('ITEM-', LPAD(seq, 4, '0')) AS item_id,
  CONCAT('Catalog Item ', LPAD(seq, 4, '0')) AS item_name,
  ROUND(5 + ((seq * 37) % 495) + (((seq * 13) % 100) / 100), 2) AS cost
FROM catalog_seq
WHERE seq <= 1000;

INSERT INTO sales_orders (
  transaction_id,
  transaction_datetime,
  catalog_item_id,
  quantity,
  price
)
WITH digits AS (
  SELECT 0 AS d
  UNION ALL SELECT 1
  UNION ALL SELECT 2
  UNION ALL SELECT 3
  UNION ALL SELECT 4
  UNION ALL SELECT 5
  UNION ALL SELECT 6
  UNION ALL SELECT 7
  UNION ALL SELECT 8
  UNION ALL SELECT 9
),
order_seq AS (
  SELECT (d5.d * 10000 + d4.d * 1000 + d3.d * 100 + d2.d * 10 + d1.d + 1) AS seq
  FROM digits d1
  CROSS JOIN digits d2
  CROSS JOIN digits d3
  CROSS JOIN digits d4
  CROSS JOIN digits d5
)
SELECT
  CONCAT('TXN-', LPAD(os.seq, 7, '0')) AS transaction_id,
  TIMESTAMP(
    DATE_SUB(CURDATE(), INTERVAL ((os.seq - 1) % 10) DAY),
    SEC_TO_TIME((os.seq * 73) % 86400)
  ) AS transaction_datetime,
  ci.item_id AS catalog_item_id,
  ((os.seq * 17) % 5) + 1 AS quantity,
  ROUND(
    ci.cost * (((os.seq * 17) % 5) + 1) * (0.90 + (((os.seq * 29) % 21) / 100)),
    2
  ) AS price
FROM order_seq os
JOIN catalog_items ci
  ON ci.item_id = CONCAT('ITEM-', LPAD(((os.seq - 1) % 1000) + 1, 4, '0'));

COMMIT;

SELECT 'Catalog rows' AS metric, COUNT(*) AS value FROM catalog_items
UNION ALL
SELECT 'Sales order rows' AS metric, COUNT(*) AS value FROM sales_orders;
