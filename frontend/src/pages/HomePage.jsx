import { docsUrl } from "../api";

const techFacts = [
  { label: "React", value: "18.3", note: "UI library powering this SPA with hooks & routing" },
  { label: "Node.js", value: "20+", note: "JavaScript runtime (running on v24 LTS)" },
  { label: "Express", value: "4.x", note: "Minimalist web framework for the REST API" },
  { label: "MySQL", value: "Azure", note: "Azure Database for MySQL Flexible Server" },
  { label: "Knex", value: "3.x", note: "SQL query builder used with the mysql2 driver" },
  { label: "Vite", value: "5.x", note: "Lightning-fast build tool & dev server" },
];

const funFacts = [
  "The frontend is a single-page React app, yet every screen you see is its own route — no full page reloads.",
  "The whole thing ships as ONE deployment: the built React app is served as static files by the same Express server that hosts the API.",
  "Data lives in the cloud on Azure Database for MySQL Flexible Server, reached over an encrypted TLS/SSL connection.",
  "An order with many items now shares a single transaction ID, thanks to a composite unique key in the database.",
  "The API is protected out of the box with Helmet security headers, CORS rules, and rate limiting (300 requests / 15 min).",
];

export default function HomePage() {
  return (
    <>
      <section className="card facts-card">
        <h2>Fun Facts About This App</h2>
        <p className="muted">A quick tour of the tech stack that powers this project.</p>
        <div className="tech-badges">
          {techFacts.map((tech) => (
            <div className="tech-badge" key={tech.label} title={tech.note}>
              <span className="tech-badge-value">{tech.value}</span>
              <span className="tech-badge-label">{tech.label}</span>
            </div>
          ))}
        </div>
        <ul className="fun-list">
          {funFacts.map((fact) => (
            <li key={fact}>{fact}</li>
          ))}
        </ul>
      </section>

      <section className="card docs-card">
        <h2>Documentation</h2>
        <p className="muted">
          The full Node.js REST API reference — every endpoint, its parameters, and request/response
          examples — is published as a PHP documentation page.
        </p>
        <ul className="fun-list">
          <li>Endpoints organized by resource: Health, Catalog Items, Stock, Sales Orders, Feedback.</li>
          <li>Each endpoint documents every argument with types, whether it's required, and examples.</li>
          <li>Includes copy-ready <code>curl</code> requests and sample JSON responses.</li>
        </ul>
        <a className="docs-button" href={docsUrl} target="_blank" rel="noreferrer">
          <svg
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
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6" />
            <path d="M8 13h8M8 17h8M8 9h2" />
          </svg>
          Open API Documentation
        </a>
        <p className="docs-hint muted">
          Served by PHP at <code>{docsUrl}</code>. Start it with{" "}
          <code>php -S localhost:8000 -t public</code> from the <code>backend</code> folder.
        </p>
      </section>
    </>
  );
}
