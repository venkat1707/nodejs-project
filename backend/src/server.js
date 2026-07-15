require("dotenv").config();

const path = require("path");
const express = require("express");
const cors = require("cors");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");
const db = require("./db");

const app = express();
const port = process.env.PORT || 4000;
const frontendOrigin = process.env.FRONTEND_ORIGIN || "http://localhost:5173";

app.disable("x-powered-by");
app.use(helmet());
app.use(
  cors({
    origin: frontendOrigin,
  }),
);
app.use(express.json({ limit: "100kb" }));

const apiLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 300,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: "Too many requests, please try again later" },
});
app.use("/api/", apiLimiter);

function isIsoDate(value) {
  return /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function isPositiveNumber(value) {
  return Number.isFinite(value) && value > 0;
}

function handleServerError(res, context, error, clientMessage) {
  // eslint-disable-next-line no-console
  console.error(`[${context}]`, error);
  res.status(500).json({ error: clientMessage });
}

app.get("/api/health", (_req, res) => {
  res.json({ status: "ok" });
});

app.post("/api/catalog-items", async (req, res) => {
  const { itemId, itemName, cost } = req.body;

  if (!itemId || !itemName || !isPositiveNumber(Number(cost))) {
    return res.status(400).json({
      error: "itemId, itemName and cost (> 0) are required",
    });
  }

  try {
    await db("catalog_items")
      .insert({
        item_id: itemId,
        item_name: itemName,
        cost: Number(cost),
      })
      .onConflict("item_id")
      .merge({
        item_name: itemName,
        cost: Number(cost),
      });

    res.status(200).json({
      message: "Catalog item created/updated successfully",
      item: { itemId, itemName, cost: Number(cost) },
    });
  } catch (error) {
    handleServerError(res, "POST /api/catalog-items", error, "Failed to create/update catalog item");
  }
});

app.get("/api/catalog-items/:itemId", async (req, res) => {
  const { itemId } = req.params;

  try {
    const item = await db("catalog_items")
      .select({ itemId: "item_id", itemName: "item_name", cost: "cost" })
      .where({ item_id: itemId })
      .first();

    if (!item) {
      return res.status(404).json({ error: "Catalog item not found" });
    }

    res.json(item);
  } catch (error) {
    handleServerError(res, "GET /api/catalog-items/:itemId", error, "Failed to get catalog item");
  }
});

app.post("/api/sales-orders", async (req, res) => {
  const { transactionId, transactionDateTime, catalogItemId, quantity, price } = req.body;
  const normalizedTransactionDateTime = String(transactionDateTime || "").replace("T", " ");

  if (
    !transactionId ||
    !normalizedTransactionDateTime ||
    !catalogItemId ||
    !isPositiveNumber(Number(quantity)) ||
    !isPositiveNumber(Number(price))
  ) {
    return res.status(400).json({
      error:
        "transactionId, transactionDateTime, catalogItemId, quantity (> 0), and price (> 0) are required",
    });
  }

  try {
    const catalogItem = await db("catalog_items")
      .select("item_id")
      .where({ item_id: catalogItemId })
      .first();

    if (!catalogItem) {
      return res.status(400).json({ error: "catalogItemId does not exist in catalog_items" });
    }

    await db("sales_orders")
      .insert({
        transaction_id: transactionId,
        transaction_datetime: normalizedTransactionDateTime,
        catalog_item_id: catalogItemId,
        quantity: Number(quantity),
        price: Number(price),
      })
      .onConflict("transaction_id")
      .merge({
        transaction_datetime: normalizedTransactionDateTime,
        catalog_item_id: catalogItemId,
        quantity: Number(quantity),
        price: Number(price),
      });

    res.status(200).json({
      message: "Sales order created/updated successfully",
      order: {
        transactionId,
        transactionDateTime: normalizedTransactionDateTime,
        catalogItemId,
        quantity: Number(quantity),
        price: Number(price),
      },
    });
  } catch (error) {
    handleServerError(res, "POST /api/sales-orders", error, "Failed to create/update sales order");
  }
});

app.get("/api/sales-orders", async (req, res) => {
  const { date } = req.query;

  if (date && !isIsoDate(date)) {
    return res.status(400).json({ error: "date must be in YYYY-MM-DD format" });
  }

  try {
    const query = db({ so: "sales_orders" })
      .join({ ci: "catalog_items" }, "ci.item_id", "so.catalog_item_id")
      .select({
        transactionId: "so.transaction_id",
        transactionDateTime: "so.transaction_datetime",
        transactionDate: db.raw("DATE(so.transaction_datetime)"),
        catalogItemId: "so.catalog_item_id",
        itemName: "ci.item_name",
        quantity: "so.quantity",
        price: "so.price",
      })
      .orderByRaw("DATE(so.transaction_datetime) DESC")
      .orderBy("so.transaction_id", "asc");

    if (date) {
      query.whereRaw("DATE(so.transaction_datetime) = ?", [date]);
    }

    const rows = await query;
    res.json(rows);
  } catch (error) {
    handleServerError(res, "GET /api/sales-orders", error, "Failed to list sales orders");
  }
});

// Serve the built React frontend (single App Service deployment)
const publicDir = path.join(__dirname, "..", "public");
app.use(express.static(publicDir));

// Unmatched API routes return a JSON 404
app.use("/api", (_req, res) => {
  res.status(404).json({ error: "Route not found" });
});

// SPA fallback: send index.html for any non-API route
app.get("*", (_req, res) => {
  res.sendFile(path.join(publicDir, "index.html"));
});

app.listen(port, () => {
  // eslint-disable-next-line no-console
  console.log(`Backend API running at http://localhost:${port}`);
});
