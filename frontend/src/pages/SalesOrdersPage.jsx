import { useMemo, useState } from "react";
import * as XLSX from "xlsx";
import { apiBaseUrl } from "../api";

const EXPORT_COLUMNS = [
  { key: "transactionId", header: "Transaction ID" },
  { key: "transactionDateTime", header: "Transaction Date-Time" },
  { key: "transactionDate", header: "Date" },
  { key: "catalogItemId", header: "Catalog Item ID" },
  { key: "itemName", header: "Item Name" },
  { key: "quantity", header: "Quantity" },
  { key: "price", header: "Price" },
];

export default function SalesOrdersPage() {
  const [orderDate, setOrderDate] = useState("");
  const [orders, setOrders] = useState([]);
  const [ordersError, setOrdersError] = useState("");

  const salesCountText = useMemo(() => `${orders.length} order(s) loaded`, [orders]);

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

  function exportFileName(extension) {
    const suffix = orderDate ? orderDate : "all";
    return `sales-orders-${suffix}.${extension}`;
  }

  function buildRows() {
    return orders.map((order) =>
      EXPORT_COLUMNS.reduce((row, col) => {
        row[col.header] = order[col.key];
        return row;
      }, {}),
    );
  }

  function exportCsv() {
    if (orders.length === 0) return;
    const worksheet = XLSX.utils.json_to_sheet(buildRows(), {
      header: EXPORT_COLUMNS.map((c) => c.header),
    });
    const csv = XLSX.utils.sheet_to_csv(worksheet);
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    downloadBlob(blob, exportFileName("csv"));
  }

  function exportExcel() {
    if (orders.length === 0) return;
    const worksheet = XLSX.utils.json_to_sheet(buildRows(), {
      header: EXPORT_COLUMNS.map((c) => c.header),
    });
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Sales Orders");
    XLSX.writeFile(workbook, exportFileName("xlsx"));
  }

  function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  function printReport() {
    if (orders.length === 0) return;
    window.print();
  }

  return (
    <section className="card table-wrapper">
      <h2>Sales Orders by Date</h2>
      <p className="print-only print-caption">
        {orderDate ? `Filtered by date: ${orderDate}` : "All dates"} — {orders.length} order(s)
      </p>
      <form className="no-print" onSubmit={loadSalesOrders}>
        <label>Filter by Date (optional)</label>
        <input type="date" value={orderDate} onChange={(e) => setOrderDate(e.target.value)} />
        <button type="submit">Load Sales Orders</button>
      </form>
      <div className="export-toolbar">
        <p className="muted">{salesCountText}</p>
        <div className="export-actions no-print">
          <button
            type="button"
            className="btn-secondary"
            onClick={exportCsv}
            disabled={orders.length === 0}
          >
            Export CSV
          </button>
          <button
            type="button"
            className="btn-secondary"
            onClick={exportExcel}
            disabled={orders.length === 0}
          >
            Export Excel
          </button>
          <button
            type="button"
            className="btn-secondary btn-icon-text"
            onClick={printReport}
            disabled={orders.length === 0}
          >
            <svg
              className="btn-icon"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <polyline points="6 9 6 2 18 2 18 9" />
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
              <rect x="6" y="14" width="12" height="8" />
            </svg>
            Print
          </button>
        </div>
      </div>
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
            <tr key={`${order.transactionId}-${order.catalogItemId}`}>
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
  );
}
