require("dotenv").config();

const path = require("path");
const express = require("express");
const cors = require("cors");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");
const db = require("./db");

const app = express();
const port = process.env.PORT || 4000;
// FRONTEND_ORIGIN may be a single origin or a comma-separated list.
const allowedOrigins = (
  process.env.FRONTEND_ORIGIN ||
  "http://localhost:5173,http://localhost:4000,http://localhost:8000"
)
  .split(",")
  .map((value) => value.trim())
  .filter(Boolean);

app.disable("x-powered-by");
app.use(helmet());
app.use(
  cors({
    origin(origin, callback) {
      // Allow same-origin / non-browser requests (no Origin header) and any allow-listed origin.
      if (!origin || allowedOrigins.includes(origin)) {
        return callback(null, true);
      }
      return callback(new Error(`Origin ${origin} is not allowed by CORS`));
    },
  }),
);
app.use(express.json({ limit: "10mb" }));

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
  const { itemId, itemName, cost, discount } = req.body;

  if (!itemId || !itemName || !isPositiveNumber(Number(cost))) {
    return res.status(400).json({
      error: "itemId, itemName and cost (> 0) are required",
    });
  }

  let normalizedDiscount = null;
  if (discount !== undefined && discount !== null && discount !== "") {
    normalizedDiscount = Number(discount);
    if (!Number.isFinite(normalizedDiscount) || normalizedDiscount < 0 || normalizedDiscount > 100) {
      return res.status(400).json({ error: "discount must be a percentage between 0 and 100" });
    }
  }

  try {
    await db("catalog_items")
      .insert({
        item_id: itemId,
        item_name: itemName,
        cost: Number(cost),
        discount: normalizedDiscount,
      })
      .onConflict("item_id")
      .merge({
        item_name: itemName,
        cost: Number(cost),
        discount: normalizedDiscount,
      });

    res.status(200).json({
      message: "Catalog item created/updated successfully",
      item: { itemId, itemName, cost: Number(cost), discount: normalizedDiscount },
    });
  } catch (error) {
    handleServerError(res, "POST /api/catalog-items", error, "Failed to create/update catalog item");
  }
});

app.post("/api/catalog-items/bulk", async (req, res) => {
  const { items } = req.body;

  if (!Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: "items must be a non-empty array" });
  }

  if (items.length > 10000) {
    return res.status(400).json({ error: "A maximum of 10000 items can be uploaded at once" });
  }

  const validRows = [];
  const errors = [];

  items.forEach((raw, index) => {
    const rowNumber = index + 1;
    const itemId = raw && raw.itemId != null ? String(raw.itemId).trim() : "";
    const itemName = raw && raw.itemName != null ? String(raw.itemName).trim() : "";
    const cost = Number(raw && raw.cost);

    if (!itemId || !itemName || !isPositiveNumber(cost)) {
      errors.push({ row: rowNumber, error: "itemId, itemName and cost (> 0) are required" });
      return;
    }

    let discount = null;
    if (raw.discount !== undefined && raw.discount !== null && String(raw.discount).trim() !== "") {
      discount = Number(raw.discount);
      if (!Number.isFinite(discount) || discount < 0 || discount > 100) {
        errors.push({ row: rowNumber, error: "discount must be between 0 and 100" });
        return;
      }
    }

    validRows.push({
      item_id: itemId.slice(0, 50),
      item_name: itemName.slice(0, 255),
      cost,
      discount,
    });
  });

  if (validRows.length === 0) {
    return res.status(400).json({ error: "No valid rows to import", errors });
  }

  try {
    await db.transaction(async (trx) => {
      for (const row of validRows) {
        await trx("catalog_items")
          .insert(row)
          .onConflict("item_id")
          .merge({ item_name: row.item_name, cost: row.cost, discount: row.discount });
      }
    });

    res.status(200).json({
      message: `Imported ${validRows.length} catalog item(s) successfully.`,
      imported: validRows.length,
      skipped: errors.length,
      errors,
    });
  } catch (error) {
    handleServerError(res, "POST /api/catalog-items/bulk", error, "Failed to import catalog items");
  }
});

app.get("/api/catalog-items", async (req, res) => {
  const { page, limit } = req.query;
  const search = (req.query.search || "").trim();
  const columns = { itemId: "item_id", itemName: "item_name", cost: "cost", discount: "discount" };

  try {
    if (page !== undefined || limit !== undefined) {
      const pageNum = Math.max(1, parseInt(page, 10) || 1);
      const pageSize = Math.min(1000, Math.max(1, parseInt(limit, 10) || 100));
      const offset = (pageNum - 1) * pageSize;

      const rowsQuery = db("catalog_items")
        .select(columns)
        .orderBy("item_id", "asc")
        .limit(pageSize)
        .offset(offset);
      const countQuery = db("catalog_items");

      if (search) {
        const like = `%${search}%`;
        rowsQuery.where((qb) =>
          qb.where("item_id", "like", like).orWhere("item_name", "like", like),
        );
        countQuery.where((qb) =>
          qb.where("item_id", "like", like).orWhere("item_name", "like", like),
        );
      }

      const rows = await rowsQuery;
      const [{ total }] = await countQuery.count({ total: "*" });

      return res.json({ rows, total: Number(total), page: pageNum, limit: pageSize });
    }

    // No pagination: return the full list, optionally filtered by search.
    const listQuery = db("catalog_items").select(columns).orderBy("item_id", "asc");
    if (search) {
      const like = `%${search}%`;
      listQuery.where((qb) =>
        qb.where("item_id", "like", like).orWhere("item_name", "like", like),
      );
    }
    const items = await listQuery;
    res.json(items);
  } catch (error) {
    handleServerError(res, "GET /api/catalog-items", error, "Failed to list catalog items");
  }
});

app.get("/api/catalog-items/:itemId", async (req, res) => {
  const { itemId } = req.params;

  try {
    const item = await db("catalog_items")
      .select({ itemId: "item_id", itemName: "item_name", cost: "cost", discount: "discount" })
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
      .onConflict(["transaction_id", "catalog_item_id"])
      .merge({
        transaction_datetime: normalizedTransactionDateTime,
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

app.post("/api/feedback", async (req, res) => {
  const { name, email, rating, improvements, newFeatures } = req.body;

  const trimmedImprovements = String(improvements || "").trim();
  const trimmedNewFeatures = String(newFeatures || "").trim();

  if (!trimmedImprovements && !trimmedNewFeatures) {
    return res.status(400).json({
      error: "Please share at least one improvement or a new feature idea",
    });
  }

  let normalizedRating = null;
  if (rating !== undefined && rating !== null && rating !== "") {
    normalizedRating = Number(rating);
    if (!Number.isInteger(normalizedRating) || normalizedRating < 1 || normalizedRating > 5) {
      return res.status(400).json({ error: "rating must be an integer between 1 and 5" });
    }
  }

  try {
    const [id] = await db("feedback").insert({
      name: name ? String(name).trim().slice(0, 255) : null,
      email: email ? String(email).trim().slice(0, 255) : null,
      rating: normalizedRating,
      improvements: trimmedImprovements || null,
      new_features: trimmedNewFeatures || null,
    });

    res.status(201).json({
      message: "Thank you! Your feedback has been recorded.",
      id,
    });
  } catch (error) {
    handleServerError(res, "POST /api/feedback", error, "Failed to save feedback");
  }
});

app.get("/api/feedback", async (_req, res) => {
  try {
    const rows = await db("feedback")
      .select({
        id: "id",
        name: "name",
        email: "email",
        rating: "rating",
        improvements: "improvements",
        newFeatures: "new_features",
        createdAt: "created_at",
      })
      .orderBy("created_at", "desc");

    res.json(rows);
  } catch (error) {
    handleServerError(res, "GET /api/feedback", error, "Failed to list feedback");
  }
});

function isNonNegativeInteger(value) {
  return Number.isInteger(value) && value >= 0;
}

app.post("/api/stock", async (req, res) => {
  const { catalogItemId, unitsAvailable } = req.body;
  const units = Number(unitsAvailable);

  if (!catalogItemId || !isNonNegativeInteger(units)) {
    return res.status(400).json({
      error: "catalogItemId and unitsAvailable (integer >= 0) are required",
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

    await db("catalog_stock")
      .insert({ catalog_item_id: catalogItemId, units_available: units })
      .onConflict("catalog_item_id")
      .merge({ units_available: units });

    res.status(200).json({
      message: "Stock record created/updated successfully",
      stock: { catalogItemId, unitsAvailable: units },
    });
  } catch (error) {
    handleServerError(res, "POST /api/stock", error, "Failed to create/update stock record");
  }
});

app.post("/api/stock/bulk", async (req, res) => {
  const { items } = req.body;

  if (!Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: "items must be a non-empty array" });
  }

  if (items.length > 10000) {
    return res.status(400).json({ error: "A maximum of 10000 items can be uploaded at once" });
  }

  const validRows = [];
  const errors = [];

  items.forEach((raw, index) => {
    const rowNumber = index + 1;
    const catalogItemId = raw && raw.catalogItemId != null ? String(raw.catalogItemId).trim() : "";
    const units = Number(raw && raw.unitsAvailable);

    if (!catalogItemId || !isNonNegativeInteger(units)) {
      errors.push({
        row: rowNumber,
        error: "catalogItemId and unitsAvailable (integer >= 0) are required",
      });
      return;
    }

    validRows.push({ catalog_item_id: catalogItemId.slice(0, 50), units_available: units });
  });

  if (validRows.length === 0) {
    return res.status(400).json({ error: "No valid rows to import", errors });
  }

  try {
    const knownItems = await db("catalog_items").select("item_id");
    const knownIds = new Set(knownItems.map((i) => i.item_id));

    const importable = [];
    validRows.forEach((row, index) => {
      if (knownIds.has(row.catalog_item_id)) {
        importable.push(row);
      } else {
        errors.push({
          row: index + 1,
          error: `catalogItemId '${row.catalog_item_id}' does not exist in catalog_items`,
        });
      }
    });

    if (importable.length === 0) {
      return res.status(400).json({ error: "No valid rows to import", errors });
    }

    await db.transaction(async (trx) => {
      for (const row of importable) {
        await trx("catalog_stock")
          .insert(row)
          .onConflict("catalog_item_id")
          .merge({ units_available: row.units_available });
      }
    });

    res.status(200).json({
      message: `Imported ${importable.length} stock record(s) successfully.`,
      imported: importable.length,
      skipped: errors.length,
      errors,
    });
  } catch (error) {
    handleServerError(res, "POST /api/stock/bulk", error, "Failed to import stock records");
  }
});

app.get("/api/stock", async (req, res) => {
  const { page, limit } = req.query;
  const search = (req.query.search || "").trim();

  const columns = {
    catalogItemId: "s.catalog_item_id",
    itemName: "ci.item_name",
    unitsAvailable: "s.units_available",
    createdAt: "s.created_at",
    updatedAt: "s.updated_at",
  };

  const baseQuery = () =>
    db({ s: "catalog_stock" }).join({ ci: "catalog_items" }, "ci.item_id", "s.catalog_item_id");

  const applySearch = (qb) => {
    if (search) {
      const like = `%${search}%`;
      qb.where((w) =>
        w.where("s.catalog_item_id", "like", like).orWhere("ci.item_name", "like", like),
      );
    }
  };

  try {
    if (page !== undefined || limit !== undefined) {
      const pageNum = Math.max(1, parseInt(page, 10) || 1);
      const pageSize = Math.min(1000, Math.max(1, parseInt(limit, 10) || 100));
      const offset = (pageNum - 1) * pageSize;

      const rowsQuery = baseQuery()
        .select(columns)
        .orderBy("s.catalog_item_id", "asc")
        .limit(pageSize)
        .offset(offset);
      applySearch(rowsQuery);

      const countQuery = baseQuery();
      applySearch(countQuery);
      const [{ total }] = await countQuery.count({ total: "*" });

      const rows = await rowsQuery;
      return res.json({ rows, total: Number(total), page: pageNum, limit: pageSize });
    }

    const listQuery = baseQuery().select(columns).orderBy("s.catalog_item_id", "asc");
    applySearch(listQuery);
    const rows = await listQuery;
    res.json(rows);
  } catch (error) {
    handleServerError(res, "GET /api/stock", error, "Failed to list stock records");
  }
});

app.get("/api/stock/:catalogItemId", async (req, res) => {
  const { catalogItemId } = req.params;

  try {
    const row = await db({ s: "catalog_stock" })
      .join({ ci: "catalog_items" }, "ci.item_id", "s.catalog_item_id")
      .select({
        catalogItemId: "s.catalog_item_id",
        itemName: "ci.item_name",
        unitsAvailable: "s.units_available",
        createdAt: "s.created_at",
        updatedAt: "s.updated_at",
      })
      .where({ "s.catalog_item_id": catalogItemId })
      .first();

    if (!row) {
      return res.status(404).json({ error: "Stock record not found" });
    }

    res.json(row);
  } catch (error) {
    handleServerError(res, "GET /api/stock/:catalogItemId", error, "Failed to get stock record");
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
