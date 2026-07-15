import { useMemo, useState } from "react";

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:4000";

function ApiResult({ data, error }) {
  if (error) return <div className="message error">{error}</div>;
  if (!data) return null;
  return <pre className="message">{JSON.stringify(data, null, 2)}</pre>;
}

function App() {
  const [catalogForm, setCatalogForm] = useState({ itemId: "", itemName: "", cost: "" });
  const [catalogSearchId, setCatalogSearchId] = useState("");
  const [catalogResult, setCatalogResult] = useState(null);
  const [catalogError, setCatalogError] = useState("");

  const [orderForm, setOrderForm] = useState({
    transactionId: "",
    transactionDateTime: "",
    catalogItemId: "",
    quantity: "",
    price: "",
  });
  const [orderResult, setOrderResult] = useState(null);
  const [orderError, setOrderError] = useState("");

  const [orderDate, setOrderDate] = useState("");
  const [orders, setOrders] = useState([]);
  const [ordersError, setOrdersError] = useState("");

  const salesCountText = useMemo(() => `${orders.length} order(s) loaded`, [orders]);

  async function upsertCatalogItem(event) {
    event.preventDefault();
    setCatalogError("");
    setCatalogResult(null);

    try {
      const response = await fetch(`${apiBaseUrl}/api/catalog-items`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...catalogForm,
          cost: Number(catalogForm.cost),
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to save catalog item");
      setCatalogResult(data);
    } catch (error) {
      setCatalogError(error.message);
    }
  }

  async function getCatalogItem(event) {
    event.preventDefault();
    setCatalogError("");
    setCatalogResult(null);

    try {
      const response = await fetch(
        `${apiBaseUrl}/api/catalog-items/${encodeURIComponent(catalogSearchId)}`,
      );
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to fetch catalog item");
      setCatalogResult(data);
    } catch (error) {
      setCatalogError(error.message);
    }
  }

  async function createSalesOrder(event) {
    event.preventDefault();
    setOrderError("");
    setOrderResult(null);

    try {
      const response = await fetch(`${apiBaseUrl}/api/sales-orders`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...orderForm,
          quantity: Number(orderForm.quantity),
          price: Number(orderForm.price),
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to save sales order");
      setOrderResult(data);
    } catch (error) {
      setOrderError(error.message);
    }
  }

  async function loadSalesOrders(event) {
    event.preventDefault();
    setOrdersError("");

    try {
      const query = orderDate ? `?date=${encodeURIComponent(orderDate)}` : "";
      const response = await fetch(`${apiBaseUrl}/api/sales-orders${query}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to load sales orders");
      setOrders(data);
    } catch (error) {
      setOrdersError(error.message);
    }
  }

  return (
    <main className="container">
      <h1>Sales Orders Console</h1>
      <p className="subtitle">Modern React frontend connected to a separate backend API.</p>

      <section className="grid">
        <article className="card">
          <h2>Upsert Catalog Item</h2>
          <form onSubmit={upsertCatalogItem}>
            <label>Item ID</label>
            <input
              value={catalogForm.itemId}
              onChange={(e) => setCatalogForm({ ...catalogForm, itemId: e.target.value })}
              placeholder="ITEM-1001"
              required
            />
            <label>Item Name</label>
            <input
              value={catalogForm.itemName}
              onChange={(e) => setCatalogForm({ ...catalogForm, itemName: e.target.value })}
              placeholder="Bluetooth Mouse"
              required
            />
            <label>Cost</label>
            <input
              type="number"
              min="0.01"
              step="0.01"
              value={catalogForm.cost}
              onChange={(e) => setCatalogForm({ ...catalogForm, cost: e.target.value })}
              placeholder="29.99"
              required
            />
            <button type="submit">Save Catalog Item</button>
          </form>
          <ApiResult data={catalogResult} error={catalogError} />
        </article>

        <article className="card">
          <h2>Get Catalog Item</h2>
          <form onSubmit={getCatalogItem}>
            <label>Item ID</label>
            <input
              value={catalogSearchId}
              onChange={(e) => setCatalogSearchId(e.target.value)}
              placeholder="ITEM-1001"
              required
            />
            <button type="submit">Fetch Catalog Item</button>
          </form>
          <ApiResult data={catalogResult} error={catalogError} />
        </article>

        <article className="card">
          <h2>Create/Update Sales Order</h2>
          <form onSubmit={createSalesOrder}>
            <label>Transaction ID</label>
            <input
              value={orderForm.transactionId}
              onChange={(e) => setOrderForm({ ...orderForm, transactionId: e.target.value })}
              placeholder="TXN-90001"
              required
            />
            <label>Transaction Date-Time</label>
            <input
              type="datetime-local"
              value={orderForm.transactionDateTime}
              onChange={(e) => setOrderForm({ ...orderForm, transactionDateTime: e.target.value })}
              required
            />
            <label>Catalog Item ID</label>
            <input
              value={orderForm.catalogItemId}
              onChange={(e) => setOrderForm({ ...orderForm, catalogItemId: e.target.value })}
              placeholder="ITEM-1001"
              required
            />
            <label>Quantity</label>
            <input
              type="number"
              min="1"
              value={orderForm.quantity}
              onChange={(e) => setOrderForm({ ...orderForm, quantity: e.target.value })}
              required
            />
            <label>Price</label>
            <input
              type="number"
              min="0.01"
              step="0.01"
              value={orderForm.price}
              onChange={(e) => setOrderForm({ ...orderForm, price: e.target.value })}
              required
            />
            <button type="submit">Save Sales Order</button>
          </form>
          <ApiResult data={orderResult} error={orderError} />
        </article>
      </section>

      <section className="card table-wrapper">
        <h2>Sales Orders</h2>
        <form onSubmit={loadSalesOrders}>
          <label>Filter by Date (optional)</label>
          <input type="date" value={orderDate} onChange={(e) => setOrderDate(e.target.value)} />
          <button type="submit">Load Sales Orders</button>
        </form>
        <p className="muted">{salesCountText}</p>
        {ordersError ? <div className="message error">{ordersError}</div> : null}
        <table>
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Transaction Date-Time</th>
              <th>Date</th>
              <th>Catalog Item ID</th>
              <th>Item Name</th>
              <th>Quantity</th>
              <th>Price</th>
            </tr>
          </thead>
          <tbody>
            {orders.map((order) => (
              <tr key={order.transactionId}>
                <td>{order.transactionId}</td>
                <td>{String(order.transactionDateTime)}</td>
                <td>{String(order.transactionDate)}</td>
                <td>{order.catalogItemId}</td>
                <td>{order.itemName}</td>
                <td>{order.quantity}</td>
                <td>{order.price}</td>
              </tr>
            ))}
            {orders.length === 0 ? (
              <tr>
                <td colSpan="7">No sales orders found.</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </section>
    </main>
  );
}

export default App;
