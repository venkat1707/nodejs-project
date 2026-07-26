import { useEffect, useMemo, useRef, useState } from "react";
import * as XLSX from "xlsx";
import { apiBaseUrl } from "../api";

const HEADER_ALIASES = {
  catalogitemid: "catalogItemId",
  "catalog item id": "catalogItemId",
  itemid: "catalogItemId",
  "item id": "catalogItemId",
  id: "catalogItemId",
  unitsavailable: "unitsAvailable",
  "units available": "unitsAvailable",
  units: "unitsAvailable",
  stock: "unitsAvailable",
  quantity: "unitsAvailable",
};

function normalizeRow(row) {
  const out = {};
  for (const [key, value] of Object.entries(row)) {
    const mapped = HEADER_ALIASES[String(key).trim().toLowerCase()];
    if (mapped) out[mapped] = value;
  }
  return out;
}

const EXPORT_COLUMNS = [
  { key: "catalogItemId", header: "Catalog Item ID" },
  { key: "itemName", header: "Item Name" },
  { key: "unitsAvailable", header: "Units Available" },
  { key: "createdAt", header: "Created At" },
  { key: "updatedAt", header: "Updated At" },
];

const PAGE_SIZE = 100;

export default function StockPage() {
  const [items, setItems] = useState([]);

  const [stockForm, setStockForm] = useState({ catalogItemId: "", unitsAvailable: "" });
  const [stockResult, setStockResult] = useState(null);
  const [stockError, setStockError] = useState("");
  const [loadedFrom, setLoadedFrom] = useState("");

  const fileInputRef = useRef(null);
  const [fileName, setFileName] = useState("");
  const [bulkResult, setBulkResult] = useState(null);
  const [bulkError, setBulkError] = useState("");
  const [uploading, setUploading] = useState(false);

  const [pageRows, setPageRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [listError, setListError] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  const [printRows, setPrintRows] = useState([]);
  const [printSignal, setPrintSignal] = useState(0);

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const countText = useMemo(() => {
    if (total === 0) return search ? "No matching records" : "0 stock record(s)";
    const start = (page - 1) * PAGE_SIZE + 1;
    const end = Math.min(page * PAGE_SIZE, total);
    return `Showing ${start}–${end} of ${total} stock record(s)`;
  }, [total, page, search]);

  async function loadItems() {
    try {
      const response = await fetch(`${apiBaseUrl}/api/catalog-items`);
      const data = await response.json();
      if (response.ok) setItems(data);
    } catch {
      // Non-fatal: dropdown will populate on next load.
    }
  }

  async function loadPage(targetPage, searchTerm) {
    setListError("");
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(targetPage), limit: String(PAGE_SIZE) });
      if (searchTerm) params.set("search", searchTerm);
      const response = await fetch(`${apiBaseUrl}/api/stock?${params.toString()}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to load stock records");
      setPageRows(data.rows);
      setTotal(data.total);
    } catch (error) {
      setListError(error.message);
    } finally {
      setLoading(false);
    }
  }

  async function fetchAllStock() {
    const params = new URLSearchParams();
    if (debouncedSearch) params.set("search", debouncedSearch);
    const qs = params.toString();
    const response = await fetch(`${apiBaseUrl}/api/stock${qs ? `?${qs}` : ""}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || "Unable to load stock records");
    return data;
  }

  useEffect(() => {
    loadItems();
  }, []);

  // Debounce the search box so we don't hit the server on every keystroke.
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch]);

  useEffect(() => {
    loadPage(page, debouncedSearch);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, debouncedSearch]);

  // Trigger the browser print dialog once the print-only rows have rendered.
  useEffect(() => {
    if (printSignal > 0 && printRows.length > 0) {
      window.print();
    }
  }, [printSignal, printRows]);

  function goToPage(target) {
    const clamped = Math.min(Math.max(1, target), totalPages);
    if (clamped !== page) setPage(clamped);
  }

  function handleItemIdChange(value) {
    setStockResult(null);
    setLoadedFrom("");
    setStockForm((prev) => ({ ...prev, catalogItemId: value }));
  }

  async function lookupStock(value) {
    const id = value.trim();
    if (!id) {
      setLoadedFrom("");
      return;
    }
    const local = pageRows.find((row) => row.catalogItemId === id);
    if (local) {
      setStockForm({ catalogItemId: id, unitsAvailable: String(local.unitsAvailable) });
      setLoadedFrom(id);
      return;
    }
    try {
      const response = await fetch(`${apiBaseUrl}/api/stock/${encodeURIComponent(id)}`);
      if (!response.ok) {
        setLoadedFrom("");
        return;
      }
      const row = await response.json();
      setStockForm({ catalogItemId: row.catalogItemId, unitsAvailable: String(row.unitsAvailable) });
      setLoadedFrom(row.catalogItemId);
    } catch {
      setLoadedFrom("");
    }
  }

  async function upsertStock(event) {
    event.preventDefault();
    setStockError("");
    setStockResult(null);

    try {
      const response = await fetch(`${apiBaseUrl}/api/stock`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          catalogItemId: stockForm.catalogItemId,
          unitsAvailable: Number(stockForm.unitsAvailable),
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to save stock record");
      setStockResult(data);
      await loadPage(page, debouncedSearch);
    } catch (error) {
      setStockError(error.message);
    }
  }

  async function handleBulkUpload(event) {
    event.preventDefault();
    setBulkError("");
    setBulkResult(null);

    const file = fileInputRef.current?.files?.[0];
    if (!file) {
      setBulkError("Please choose a CSV or Excel file first.");
      return;
    }

    setUploading(true);
    try {
      const buffer = await file.arrayBuffer();
      const workbook = XLSX.read(buffer, { type: "array" });
      const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
      const rawRows = XLSX.utils.sheet_to_json(firstSheet, { defval: "" });

      const rows = rawRows
        .map(normalizeRow)
        .filter((row) => row.catalogItemId || row.unitsAvailable !== undefined);

      if (rows.length === 0) {
        throw new Error(
          "No rows found. Ensure the file has columns: Catalog Item ID, Units Available.",
        );
      }

      const response = await fetch(`${apiBaseUrl}/api/stock/bulk`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: rows }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Bulk upload failed");

      setBulkResult(data);
      setFileName("");
      if (fileInputRef.current) fileInputRef.current.value = "";
      await loadPage(page, debouncedSearch);
    } catch (error) {
      setBulkError(error.message);
    } finally {
      setUploading(false);
    }
  }

  function buildRows(source) {
    return source.map((row) =>
      EXPORT_COLUMNS.reduce((acc, col) => {
        acc[col.header] = row[col.key] == null ? "" : row[col.key];
        return acc;
      }, {}),
    );
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

  function exportCsvFrom(source, filename) {
    if (source.length === 0) return;
    const worksheet = XLSX.utils.json_to_sheet(buildRows(source), {
      header: EXPORT_COLUMNS.map((c) => c.header),
    });
    const csv = XLSX.utils.sheet_to_csv(worksheet);
    downloadBlob(new Blob([csv], { type: "text/csv;charset=utf-8;" }), filename);
  }

  function exportExcelFrom(source, filename) {
    if (source.length === 0) return;
    const worksheet = XLSX.utils.json_to_sheet(buildRows(source), {
      header: EXPORT_COLUMNS.map((c) => c.header),
    });
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Stock");
    XLSX.writeFile(workbook, filename);
  }

  function exportJsonFrom(source, filename) {
    if (source.length === 0) return;
    const json = JSON.stringify(source, null, 2);
    downloadBlob(new Blob([json], { type: "application/json;charset=utf-8;" }), filename);
  }

  // All exports/print operate on the full stock list (respecting the active filter),
  // fetched from the server on demand.
  async function withAllStock(action) {
    if (total === 0 || busy) return;
    setBusy(true);
    setListError("");
    try {
      const all = await fetchAllStock();
      action(all);
    } catch (error) {
      setListError(error.message);
    } finally {
      setBusy(false);
    }
  }

  function exportName(extension) {
    return `stock${debouncedSearch ? "-filtered" : ""}.${extension}`;
  }

  function exportCsv() {
    withAllStock((all) => exportCsvFrom(all, exportName("csv")));
  }
  function exportExcel() {
    withAllStock((all) => exportExcelFrom(all, exportName("xlsx")));
  }
  function exportJson() {
    withAllStock((all) => exportJsonFrom(all, exportName("json")));
  }
  function printReport() {
    withAllStock((all) => {
      setPrintRows(all);
      setPrintSignal((s) => s + 1);
    });
  }

  return (
    <>
      <section className="grid no-print">
        <article className="card">
          <h2>Update Stock</h2>
          <form onSubmit={upsertStock}>
            <label>Catalog Item ID</label>
            <input
              list="stock-item-ids"
              value={stockForm.catalogItemId}
              onChange={(e) => handleItemIdChange(e.target.value)}
              onBlur={(e) => lookupStock(e.target.value)}
              placeholder="Type an Item ID, then tab out to load its stock…"
              autoComplete="off"
              required
            />
            <datalist id="stock-item-ids">
              {items.map((item) => (
                <option key={item.itemId} value={item.itemId}>
                  {item.itemName}
                </option>
              ))}
            </datalist>
            {loadedFrom ? (
              <p className="muted">
                Editing stock for <strong>{loadedFrom}</strong>.
              </p>
            ) : null}

            <label>Units Available</label>
            <input
              type="number"
              min="0"
              step="1"
              value={stockForm.unitsAvailable}
              onChange={(e) => setStockForm({ ...stockForm, unitsAvailable: e.target.value })}
              placeholder="e.g. 120"
              required
            />
            <button type="submit">Save Stock</button>
          </form>
          {stockError ? (
            <div className="catalog-result error" role="alert">
              <span className="catalog-result-icon" aria-hidden="true">
                ⚠
              </span>
              <div>
                <strong>Update stock unsuccessful</strong>
                <p>{stockError}</p>
              </div>
            </div>
          ) : null}
          {stockResult ? (
            <div className="catalog-result success" role="status">
              <span className="catalog-result-icon" aria-hidden="true">
                ✓
              </span>
              <div>
                <strong>{stockResult.message}</strong>
                <p>
                  <span className="catalog-chip">{stockResult.stock.catalogItemId}</span>
                  {stockResult.stock.unitsAvailable} unit(s) available
                </p>
              </div>
            </div>
          ) : null}
        </article>

        <article className="card">
          <h2>Bulk Upload from CSV / Excel</h2>
          <p className="muted">
            Upload a <strong>.csv</strong>, <strong>.xlsx</strong> or <strong>.xls</strong> file
            with columns: <code>Catalog Item ID</code> and <code>Units Available</code>. Existing
            stock for the same item is updated.
          </p>
          <form onSubmit={handleBulkUpload}>
            <label>Choose file</label>
            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
              onChange={(e) => setFileName(e.target.files?.[0]?.name ?? "")}
            />
            {fileName ? (
              <p className="muted">
                Selected: <strong>{fileName}</strong>
              </p>
            ) : null}
            <button type="submit" disabled={uploading}>
              {uploading ? "Uploading…" : "Upload Stock"}
            </button>
          </form>

          {bulkError ? (
            <div className="catalog-result error" role="alert">
              <span className="catalog-result-icon" aria-hidden="true">
                ⚠
              </span>
              <div>
                <strong>Bulk upload unsuccessful</strong>
                <p>{bulkError}</p>
              </div>
            </div>
          ) : null}
          {bulkResult ? (
            <div className="catalog-result success" role="status">
              <span className="catalog-result-icon" aria-hidden="true">
                ✓
              </span>
              <div>
                <strong>{bulkResult.message}</strong>
                <p>
                  Imported <strong>{bulkResult.imported}</strong> record(s)
                  {bulkResult.skipped ? `, skipped ${bulkResult.skipped} row(s)` : ""}.
                </p>
                {bulkResult.errors && bulkResult.errors.length > 0 ? (
                  <ul className="bulk-errors">
                    {bulkResult.errors.slice(0, 5).map((err) => (
                      <li key={err.row}>
                        Row {err.row}: {err.error}
                      </li>
                    ))}
                    {bulkResult.errors.length > 5 ? (
                      <li>…and {bulkResult.errors.length - 5} more.</li>
                    ) : null}
                  </ul>
                ) : null}
              </div>
            </div>
          ) : null}
        </article>
      </section>

      <section className="card table-wrapper">
        <div className="section-head">
          <h2>Stock Levels</h2>
          <p className="muted">{loading ? "Loading…" : countText}</p>
        </div>
        <p className="print-only print-caption">Stock Levels — {printRows.length} record(s)</p>
        <div className="search-bar no-print">
          <svg
            className="search-icon"
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
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.3-4.3" />
          </svg>
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by Item ID or Item Name…"
            aria-label="Search stock by Item ID or Item Name"
          />
          {search ? (
            <button type="button" className="search-clear" onClick={() => setSearch("")}>
              Clear
            </button>
          ) : null}
        </div>
        <div className="toolbar no-print">
          <span className="toolbar-label">
            {debouncedSearch
              ? `Export the ${total} matching record(s):`
              : "Export all stock records:"}
          </span>
          <div className="toolbar-actions">
            <button
              type="button"
              className="btn-secondary"
              onClick={exportCsv}
              disabled={total === 0 || busy}
            >
              CSV
            </button>
            <button
              type="button"
              className="btn-secondary"
              onClick={exportExcel}
              disabled={total === 0 || busy}
            >
              Excel
            </button>
            <button
              type="button"
              className="btn-secondary"
              onClick={exportJson}
              disabled={total === 0 || busy}
            >
              JSON
            </button>
            <button
              type="button"
              className="btn-secondary btn-icon-text"
              onClick={printReport}
              disabled={total === 0 || busy}
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
        {busy ? <p className="muted no-print">Preparing stock export…</p> : null}
        {listError ? <div className="message error">{listError}</div> : null}

        {/* On-screen paginated table (hidden when printing) */}
        <table className="no-print">
          <thead>
            <tr>
              <th>Catalog Item ID</th>
              <th>Item Name</th>
              <th>Units Available</th>
              <th>Created At</th>
              <th>Updated At</th>
            </tr>
          </thead>
          <tbody>
            {pageRows.map((row) => (
              <tr key={row.catalogItemId}>
                <td>{row.catalogItemId}</td>
                <td>{row.itemName}</td>
                <td>{row.unitsAvailable}</td>
                <td>{String(row.createdAt)}</td>
                <td>{String(row.updatedAt)}</td>
              </tr>
            ))}
            {!loading && pageRows.length === 0 ? (
              <tr>
                <td colSpan="5">
                  {search ? `No records match "${search}".` : "No stock records found."}
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>

        {/* Print-only table: current filter/all records */}
        <table className="print-only">
          <thead>
            <tr>
              <th>Catalog Item ID</th>
              <th>Item Name</th>
              <th>Units Available</th>
              <th>Created At</th>
              <th>Updated At</th>
            </tr>
          </thead>
          <tbody>
            {printRows.map((row) => (
              <tr key={row.catalogItemId}>
                <td>{row.catalogItemId}</td>
                <td>{row.itemName}</td>
                <td>{row.unitsAvailable}</td>
                <td>{String(row.createdAt)}</td>
                <td>{String(row.updatedAt)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        {totalPages > 1 ? (
          <div className="pagination no-print">
            <button
              type="button"
              className="btn-secondary"
              onClick={() => goToPage(page - 1)}
              disabled={page === 1 || loading}
            >
              Previous
            </button>
            <span className="muted">
              Page {page} of {totalPages}
            </span>
            <button
              type="button"
              className="btn-secondary"
              onClick={() => goToPage(page + 1)}
              disabled={page === totalPages || loading}
            >
              Next
            </button>
          </div>
        ) : null}
      </section>
    </>
  );
}
