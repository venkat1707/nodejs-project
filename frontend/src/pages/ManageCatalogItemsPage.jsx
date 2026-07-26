import { useEffect, useMemo, useRef, useState } from "react";
import * as XLSX from "xlsx";
import { apiBaseUrl } from "../api";

const HEADER_ALIASES = {
  itemid: "itemId",
  "item id": "itemId",
  id: "itemId",
  itemname: "itemName",
  "item name": "itemName",
  name: "itemName",
  cost: "cost",
  price: "cost",
  discount: "discount",
  "discount %": "discount",
  "discount percent": "discount",
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
  { key: "itemId", header: "Item ID" },
  { key: "itemName", header: "Item Name" },
  { key: "cost", header: "Cost" },
  { key: "discount", header: "Discount %" },
];

const PAGE_SIZE = 100;

export default function ManageCatalogItemsPage() {
  const [catalogForm, setCatalogForm] = useState({
    itemId: "",
    itemName: "",
    cost: "",
    discount: "",
  });
  const [pageRows, setPageRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [catalogResult, setCatalogResult] = useState(null);
  const [catalogError, setCatalogError] = useState("");
  const [loadedFrom, setLoadedFrom] = useState("");

  const fileInputRef = useRef(null);
  const [fileName, setFileName] = useState("");
  const [bulkResult, setBulkResult] = useState(null);
  const [bulkError, setBulkError] = useState("");
  const [uploading, setUploading] = useState(false);

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
    if (total === 0) return search ? "No matching items" : "0 catalog item(s)";
    const start = (page - 1) * PAGE_SIZE + 1;
    const end = Math.min(page * PAGE_SIZE, total);
    return `Showing ${start}–${end} of ${total} catalog item(s)`;
  }, [total, page, search]);

  async function loadPage(targetPage, searchTerm) {
    setListError("");
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(targetPage), limit: String(PAGE_SIZE) });
      if (searchTerm) params.set("search", searchTerm);
      const response = await fetch(`${apiBaseUrl}/api/catalog-items?${params.toString()}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to load catalog items");
      setPageRows(data.rows);
      setTotal(data.total);
    } catch (error) {
      setListError(error.message);
    } finally {
      setLoading(false);
    }
  }

  async function fetchAllItems() {
    const params = new URLSearchParams();
    if (debouncedSearch) params.set("search", debouncedSearch);
    const qs = params.toString();
    const response = await fetch(`${apiBaseUrl}/api/catalog-items${qs ? `?${qs}` : ""}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || "Unable to load catalog items");
    return data;
  }

  // Debounce the search box so we don't hit the server on every keystroke.
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timer);
  }, [search]);

  // Reset to the first page whenever the search term changes.
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

  async function lookupItem(value) {
    const id = value.trim();
    if (!id) {
      setLoadedFrom("");
      return;
    }
    // Reuse a row already on screen when possible to avoid a request.
    const local = pageRows.find((item) => item.itemId === id);
    if (local) {
      setCatalogForm({
        itemId: local.itemId,
        itemName: local.itemName,
        cost: String(local.cost),
        discount: local.discount == null ? "" : String(local.discount),
      });
      setLoadedFrom(local.itemId);
      return;
    }
    try {
      const response = await fetch(`${apiBaseUrl}/api/catalog-items/${encodeURIComponent(id)}`);
      if (!response.ok) {
        setLoadedFrom("");
        return;
      }
      const item = await response.json();
      setCatalogForm({
        itemId: item.itemId,
        itemName: item.itemName,
        cost: String(item.cost),
        discount: item.discount == null ? "" : String(item.discount),
      });
      setLoadedFrom(item.itemId);
    } catch {
      setLoadedFrom("");
    }
  }

  function handleItemIdChange(value) {
    setCatalogResult(null);
    setLoadedFrom("");
    setCatalogForm((prev) => ({ ...prev, itemId: value }));
  }

  async function upsertCatalogItem(event) {
    event.preventDefault();
    setCatalogError("");
    setCatalogResult(null);

    try {
      const response = await fetch(`${apiBaseUrl}/api/catalog-items`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          itemId: catalogForm.itemId,
          itemName: catalogForm.itemName,
          cost: Number(catalogForm.cost),
          discount: catalogForm.discount === "" ? null : Number(catalogForm.discount),
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to save catalog item");
      setCatalogResult(data);
      await loadPage(page, debouncedSearch);
    } catch (error) {
      setCatalogError(error.message);
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
        .filter((row) => row.itemId || row.itemName || row.cost);

      if (rows.length === 0) {
        throw new Error(
          "No rows found. Ensure the file has columns: Item ID, Item Name, Cost, Discount (optional).",
        );
      }

      const response = await fetch(`${apiBaseUrl}/api/catalog-items/bulk`, {
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
    return source.map((item) =>
      EXPORT_COLUMNS.reduce((acc, col) => {
        acc[col.header] = item[col.key] == null ? "" : item[col.key];
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
    XLSX.utils.book_append_sheet(workbook, worksheet, "Catalog Items");
    XLSX.writeFile(workbook, filename);
  }

  function exportJsonFrom(source, filename) {
    if (source.length === 0) return;
    const json = JSON.stringify(source, null, 2);
    downloadBlob(new Blob([json], { type: "application/json;charset=utf-8;" }), filename);
  }

  // All exports/print operate on the full catalog, fetched from the server on demand.
  async function withAllItems(action) {
    if (total === 0 || busy) return;
    setBusy(true);
    setListError("");
    try {
      const all = await fetchAllItems();
      action(all);
    } catch (error) {
      setListError(error.message);
    } finally {
      setBusy(false);
    }
  }

  function exportName(extension) {
    return `catalog-items${debouncedSearch ? "-filtered" : ""}.${extension}`;
  }

  function exportCsv() {
    withAllItems((all) => exportCsvFrom(all, exportName("csv")));
  }
  function exportExcel() {
    withAllItems((all) => exportExcelFrom(all, exportName("xlsx")));
  }
  function exportJson() {
    withAllItems((all) => exportJsonFrom(all, exportName("json")));
  }
  function printReport() {
    withAllItems((all) => {
      setPrintRows(all);
      setPrintSignal((s) => s + 1);
    });
  }

  return (
    <>
      <section className="grid no-print">
        <article className="card">
          <h2>Update Catalog Items</h2>
          <form onSubmit={upsertCatalogItem}>
            <label>Item ID</label>
            <input
              list="catalog-item-ids"
              value={catalogForm.itemId}
              onChange={(e) => handleItemIdChange(e.target.value)}
              onBlur={(e) => lookupItem(e.target.value)}
              placeholder="Type an Item ID, then tab out to load it…"
              autoComplete="off"
              required
            />
            <datalist id="catalog-item-ids">
              {pageRows.map((item) => (
                <option key={item.itemId} value={item.itemId}>
                  {item.itemName}
                </option>
              ))}
            </datalist>
            {loadedFrom ? (
              <p className="muted">
                Editing existing item <strong>{loadedFrom}</strong>.
              </p>
            ) : null}

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
            <label>Discount % (optional)</label>
            <input
              type="number"
              min="0"
              max="100"
              step="0.01"
              value={catalogForm.discount}
              onChange={(e) => setCatalogForm({ ...catalogForm, discount: e.target.value })}
              placeholder="e.g. 10"
            />
            <button type="submit">Save Catalog Item</button>
          </form>
          {catalogError ? (
            <div className="catalog-result error" role="alert">
              <span className="catalog-result-icon" aria-hidden="true">
                ⚠
              </span>
              <div>
                <strong>Add/Update Catalog item unsuccessful</strong>
                <p>{catalogError}</p>
              </div>
            </div>
          ) : null}
          {catalogResult ? (
            <div className="catalog-result success" role="status">
              <span className="catalog-result-icon" aria-hidden="true">
                ✓
              </span>
              <div>
                <strong>{catalogResult.message}</strong>
                <p>
                  <span className="catalog-chip">{catalogResult.item.itemId}</span>
                  {catalogResult.item.itemName} &middot; Cost $
                  {Number(catalogResult.item.cost).toFixed(2)}
                  {catalogResult.item.discount != null
                    ? ` \u00b7 ${Number(catalogResult.item.discount)}% off`
                    : ""}
                </p>
              </div>
            </div>
          ) : null}
        </article>

        <article className="card">
          <h2>Bulk Upload from CSV / Excel</h2>
          <p className="muted">
            Upload a <strong>.csv</strong>, <strong>.xlsx</strong> or <strong>.xls</strong> file
            with columns: <code>Item ID</code>, <code>Item Name</code>, <code>Cost</code> and
            optionally <code>Discount</code>. Existing items with the same Item ID are updated.
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
              {uploading ? "Uploading…" : "Upload Items"}
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
                  Imported <strong>{bulkResult.imported}</strong> item(s)
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
          <h2>Catalog Items</h2>
          <p className="muted">{loading ? "Loading…" : countText}</p>
        </div>
        <p className="print-only print-caption">Catalog Items — {printRows.length} item(s)</p>
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
            aria-label="Search catalog items by Item ID or Item Name"
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
              ? `Export the ${total} matching item(s):`
              : "Export the full catalog:"}
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
        {busy ? <p className="muted no-print">Preparing full catalog…</p> : null}
        {listError ? <div className="message error">{listError}</div> : null}

        {/* On-screen paginated table (hidden when printing) */}
        <table className="no-print">
          <thead>
            <tr>
              <th>Item ID</th>
              <th>Item Name</th>
              <th>Cost</th>
              <th>Discount %</th>
            </tr>
          </thead>
          <tbody>
            {pageRows.map((item) => (
              <tr key={item.itemId}>
                <td>{item.itemId}</td>
                <td>{item.itemName}</td>
                <td>{item.cost}</td>
                <td>{item.discount == null ? "—" : item.discount}</td>
              </tr>
            ))}
            {!loading && pageRows.length === 0 ? (
              <tr>
                <td colSpan="4">
                  {search ? `No items match "${search}".` : "No catalog items found."}
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>

        {/* Print-only table: current page (Print) or all items (Print All) */}
        <table className="print-only">
          <thead>
            <tr>
              <th>Item ID</th>
              <th>Item Name</th>
              <th>Cost</th>
              <th>Discount %</th>
            </tr>
          </thead>
          <tbody>
            {printRows.map((item) => (
              <tr key={item.itemId}>
                <td>{item.itemId}</td>
                <td>{item.itemName}</td>
                <td>{item.cost}</td>
                <td>{item.discount == null ? "—" : item.discount}</td>
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
