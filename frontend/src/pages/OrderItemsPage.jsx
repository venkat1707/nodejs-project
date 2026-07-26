import { useEffect, useMemo, useState } from "react";
import { apiBaseUrl } from "../api";

const usd = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" });

function nowLocal() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(
    d.getHours(),
  )}:${pad(d.getMinutes())}`;
}

function generateTransactionId() {
  return `TXN-${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

function newLine() {
  return {
    id: typeof crypto !== "undefined" && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`,
    catalogItemId: "",
    quantity: 1,
  };
}

export default function OrderItemsPage() {
  const [items, setItems] = useState([]);
  const [orderDateTime, setOrderDateTime] = useState(nowLocal());
  const [lines, setLines] = useState([newLine()]);
  const [orderResult, setOrderResult] = useState(null);
  const [orderError, setOrderError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const costById = useMemo(() => {
    const map = {};
    for (const item of items) map[item.itemId] = Number(item.cost);
    return map;
  }, [items]);

  function lineTotal(line) {
    const cost = costById[line.catalogItemId] ?? 0;
    const quantity = Number(line.quantity) || 0;
    return cost * quantity;
  }

  const grandTotal = useMemo(
    () => lines.reduce((sum, line) => sum + lineTotal(line), 0),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [lines, costById],
  );

  useEffect(() => {
    let cancelled = false;

    async function loadItems() {
      try {
        const response = await fetch(`${apiBaseUrl}/api/catalog-items`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || "Unable to load catalog items");
        if (!cancelled) setItems(data);
      } catch (error) {
        if (!cancelled) setOrderError(error.message);
      }
    }

    loadItems();
    return () => {
      cancelled = true;
    };
  }, []);

  function updateLine(id, changes) {
    setLines((prev) => prev.map((line) => (line.id === id ? { ...line, ...changes } : line)));
  }

  function addLine() {
    setLines((prev) => [...prev, newLine()]);
  }

  function removeLine(id) {
    setLines((prev) => (prev.length > 1 ? prev.filter((line) => line.id !== id) : prev));
  }

  async function placeOrder(event) {
    event.preventDefault();
    setOrderError("");
    setOrderResult(null);

    const validLines = lines.filter((line) => line.catalogItemId && Number(line.quantity) > 0);
    if (validLines.length === 0) {
      setOrderError("Add at least one item with a valid quantity.");
      return;
    }

    setSubmitting(true);
    try {
      const transactionId = generateTransactionId();
      const orders = validLines.map((line) => ({
        transactionId,
        transactionDateTime: orderDateTime,
        catalogItemId: line.catalogItemId,
        quantity: Number(line.quantity),
        price: lineTotal(line),
      }));

      for (const order of orders) {
        const response = await fetch(`${apiBaseUrl}/api/sales-orders`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(order),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || "Unable to place order");
      }

      setOrderResult({
        message: `Order placed successfully for ${orders.length} item(s).`,
        transactionId,
        total: grandTotal,
        items: orders,
      });
      setLines([newLine()]);
      setOrderDateTime(nowLocal());
    } catch (error) {
      setOrderError(error.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <article className="card order-card">
      <h2>Order Items</h2>
      <form onSubmit={placeOrder}>
        <label>Transaction Date-Time</label>
        <input
          type="datetime-local"
          value={orderDateTime}
          onChange={(e) => setOrderDateTime(e.target.value)}
          required
        />

        <div className="table-wrapper order-table-wrapper">
          <table className="order-table">
            <thead>
              <tr>
                <th>Catalog Item</th>
                <th className="num">Unit Cost</th>
                <th className="num">Quantity</th>
                <th className="num">Price</th>
                <th aria-label="Actions"></th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => {
                const unitCost = costById[line.catalogItemId];
                return (
                  <tr key={line.id}>
                    <td>
                      <select
                        value={line.catalogItemId}
                        onChange={(e) => updateLine(line.id, { catalogItemId: e.target.value })}
                        required
                      >
                        <option value="" disabled>
                          Select an item…
                        </option>
                        {items.map((item) => (
                          <option key={item.itemId} value={item.itemId}>
                            {item.itemId} — {item.itemName}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="num">{unitCost != null ? unitCost.toFixed(2) : "—"}</td>
                    <td className="num">
                      <input
                        type="number"
                        min="1"
                        step="1"
                        value={line.quantity}
                        onChange={(e) => updateLine(line.id, { quantity: e.target.value })}
                        required
                      />
                    </td>
                    <td className="num strong">{lineTotal(line).toFixed(2)}</td>
                    <td className="num">
                      <button
                        type="button"
                        className="icon-btn"
                        onClick={() => removeLine(line.id)}
                        disabled={lines.length === 1}
                        aria-label="Remove item"
                        title="Remove item"
                      >
                        ✕
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan="3" className="strong">
                  Order Total
                </td>
                <td className="num strong">{grandTotal.toFixed(2)}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div className="order-toolbar">
          <button type="button" className="btn-secondary" onClick={addLine}>
            + Add item
          </button>
          <button type="submit" disabled={submitting}>
            {submitting ? "Placing order…" : "Place Order"}
          </button>
        </div>
      </form>
      {orderError ? <div className="message error">{orderError}</div> : null}
      {orderResult ? (
        <div className="order-success" role="status">
          <div className="order-success-badge" aria-hidden="true">
            ✓
          </div>
          <h3 className="order-success-title">Order Placed Successfully</h3>
          <p className="order-success-subtitle">Thank you! Your order has been recorded.</p>
          <div className="order-success-grid">
            <div className="order-success-stat">
              <span className="order-success-label">Items</span>
              <span className="order-success-value">{orderResult.items.length}</span>
            </div>
            <div className="order-success-stat">
              <span className="order-success-label">Transaction ID</span>
              <span className="order-success-value mono">{orderResult.transactionId}</span>
            </div>
            <div className="order-success-stat total">
              <span className="order-success-label">Total Bill</span>
              <span className="order-success-value">{usd.format(orderResult.total)}</span>
            </div>
          </div>
        </div>
      ) : null}
    </article>
  );
}
