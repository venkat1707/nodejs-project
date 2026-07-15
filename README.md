# Sales Orders System (Split Backend + Modern React Frontend)

This workspace is organized for cleaner production deployments:
- `backend/`: Node.js + Express API with MySQL
- `frontend/`: React app built with Vite

## Architecture

- Frontend calls backend APIs using `VITE_API_BASE_URL`
- Backend connects to MySQL and exposes REST endpoints only
- Frontend and backend can be deployed independently

## Folder Layout

- `backend/src/server.js`: API server
- `backend/sql/schema.sql`: MySQL schema
- `backend/.env.example`: backend configuration template
- `frontend/src/App.jsx`: React UI
- `frontend/.env.example`: frontend API URL template

## Setup

### 1) Database

Run MySQL script:

```sql
SOURCE backend/sql/schema.sql;
```

### 1.1) Seed Sample Data (Optional)

To load 1,000 sample catalog items and 100,000 sample sales orders from the last 10 days:

```sql
SOURCE backend/sql/seed_sample_data.sql;
```

### 2) Environment Files

Create `backend/.env` from `backend/.env.example` and update values.

Create `frontend/.env` from `frontend/.env.example`.

Default values:
- Backend API: `http://localhost:4000`
- Frontend Dev Server: `http://localhost:5173`

### 3) Install Dependencies

From workspace root:

```bash
npm run install:all
```

## Run in Development

Run both backend and frontend:

```bash
npm run dev
```

Or individually:

```bash
npm run dev:backend
npm run dev:frontend
```

## Build for Production

Build frontend static assets:

```bash
npm run build:frontend
```

This outputs static files to `frontend/dist`.

Start backend API:

```bash
npm run start:backend
```

## API Endpoints

- `POST /api/catalog-items`: create or update catalog item
- `GET /api/catalog-items/:itemId`: get catalog item by id
- `POST /api/sales-orders`: create or update sales order
- `GET /api/sales-orders`: list sales orders
- `GET /api/sales-orders?date=YYYY-MM-DD`: list by date
- `GET /api/health`: health check
