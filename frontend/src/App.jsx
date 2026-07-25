import { NavLink, Route, Routes } from "react-router-dom";
import HomePage from "./pages/HomePage";
import ManageCatalogItemsPage from "./pages/ManageCatalogItemsPage";
import OrderItemsPage from "./pages/OrderItemsPage";
import SalesOrdersPage from "./pages/SalesOrdersPage";
import StockPage from "./pages/StockPage";
import FeedbackPage from "./pages/FeedbackPage";
import { docsUrl } from "./api";

function Icon({ children }) {
  return (
    <svg
      className="nav-icon"
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {children}
    </svg>
  );
}

const navItems = [
  {
    to: "/",
    end: true,
    label: "Home",
    icon: (
      <>
        <path d="M3 9.5 12 3l9 6.5" />
        <path d="M5 10v10h14V10" />
        <path d="M9 20v-6h6v6" />
      </>
    ),
  },
  {
    to: "/manage-catalog-items",
    label: "Manage Catalog Items",
    icon: (
      <>
        <path d="M12 20h9" />
        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
      </>
    ),
  },
  {
    to: "/stock",
    label: "Manage Stock",
    icon: (
      <>
        <path d="M21 8V7a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 7v10a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 17" />
        <path d="m3.3 7 8.7 5 8.7-5" />
        <path d="M12 22V12" />
      </>
    ),
  },
  {
    to: "/order-items",
    label: "Order Items",
    icon: (
      <>
        <circle cx="9" cy="21" r="1" />
        <circle cx="20" cy="21" r="1" />
        <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6" />
      </>
    ),
  },
  {
    to: "/sales-orders",
    label: "Sales Orders by Date",
    icon: (
      <>
        <rect x="3" y="4" width="18" height="18" rx="2" />
        <path d="M16 2v4M8 2v4M3 10h18" />
      </>
    ),
  },
  {
    to: "/feedback",
    label: "Share Feedback",
    icon: (
      <>
        <path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7A8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5Z" />
      </>
    ),
  },
];

function App() {
  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="9" cy="21" r="1" />
              <circle cx="20" cy="21" r="1" />
              <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6" />
            </svg>
          </span>
          <span className="brand-text">
            <span className="brand-name">Swiftcart</span>
            <span className="brand-tag">Sales &amp; Catalog Portal</span>
          </span>
        </div>

        <nav className="side-nav">
          {navItems.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end}>
              <Icon>{item.icon}</Icon>
              <span>{item.label}</span>
            </NavLink>
          ))}
          <a href={docsUrl} target="_blank" rel="noreferrer">
            <Icon>
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
              <path d="M14 2v6h6" />
              <path d="M8 13h8M8 17h8M8 9h2" />
            </Icon>
            <span>Documents</span>
          </a>
        </nav>

        <div className="sidebar-footer">React &middot; Node.js &middot; Azure MySQL</div>
      </aside>

      <div className="main">
        <div className="content">
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/manage-catalog-items" element={<ManageCatalogItemsPage />} />
            <Route path="/stock" element={<StockPage />} />
            <Route path="/order-items" element={<OrderItemsPage />} />
            <Route path="/sales-orders" element={<SalesOrdersPage />} />
            <Route path="/feedback" element={<FeedbackPage />} />
          </Routes>
        </div>
      </div>
    </div>
  );
}

export default App;
