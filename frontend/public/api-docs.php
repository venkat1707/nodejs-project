<?php
// Swiftcart — API documentation page (PHP).
// Detailed reference for the Node.js/Express REST API, organized by resource.

// Base URL of the Node.js/Express API that this documentation describes.
// Set the BASE_URL App Setting (environment variable) in production; falls back
// to the local dev server when unset.
$baseUrl = getenv('BASE_URL') ?: 'http://localhost:4000';

// The Swiftcart portal (React SPA + API) is served by the Node server at this URL.
// The docs page runs on a separate PHP server, so the "Back" link must be absolute.
// Set the PORTAL_URL App Setting in production; falls back to BASE_URL, then local dev.
$portalUrl = getenv('PORTAL_URL') ?: ($baseUrl ?: 'http://localhost:4000');

/*
 * Each section groups related endpoints. Every endpoint documents:
 *   - method, path, description
 *   - params: name, in (path|query|body), type, required, description, example
 *   - request: a full curl example
 *   - response: a sample JSON response
 */
$sections = [
    // ------------------------------------------------------------------ Health
    [
        'id' => 'health',
        'title' => 'Health',
        'description' => 'A lightweight endpoint to confirm the API process is running. Useful for uptime checks and load-balancer probes.',
        'endpoints' => [
            [
                'method' => 'GET',
                'path' => '/api/health',
                'description' => 'Returns a simple status object. Does not touch the database, so it responds even if the DB is temporarily unavailable.',
                'params' => [],
                'request' => 'curl http://localhost:4000/api/health',
                'response' => "{\n  \"status\": \"ok\"\n}",
            ],
        ],
    ],

    // ----------------------------------------------------------- Catalog Items
    [
        'id' => 'catalog-items',
        'title' => 'Catalog Items',
        'description' => 'Catalog items are the products Swiftcart sells. Each item has a unique ID, a name, a cost, and an optional discount percentage. Items are referenced by sales orders and stock records via foreign keys, so an item must exist before it can be ordered or stocked.',
        'endpoints' => [
            [
                'method' => 'POST',
                'path' => '/api/catalog-items',
                'description' => 'Creates a new catalog item, or updates an existing one when the same itemId is sent (an "upsert" keyed on itemId). Returns the stored item.',
                'params' => [
                    ['name' => 'itemId', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Unique identifier for the item (max 50 chars). If it already exists, that item is updated.', 'example' => '"ITEM-1001"'],
                    ['name' => 'itemName', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Human-readable product name (max 255 chars).', 'example' => '"Bluetooth Mouse"'],
                    ['name' => 'cost', 'in' => 'body', 'type' => 'number', 'required' => true, 'description' => 'Unit cost. Must be a positive number greater than 0.', 'example' => '29.99'],
                    ['name' => 'discount', 'in' => 'body', 'type' => 'number', 'required' => false, 'description' => 'Optional discount percentage between 0 and 100. Omit or send null for no discount.', 'example' => '10'],
                ],
                'request' => "curl -X POST http://localhost:4000/api/catalog-items \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"itemId\": \"ITEM-1001\",\n    \"itemName\": \"Bluetooth Mouse\",\n    \"cost\": 29.99,\n    \"discount\": 10\n  }'",
                'response' => "{\n  \"message\": \"Catalog item created/updated successfully\",\n  \"item\": {\n    \"itemId\": \"ITEM-1001\",\n    \"itemName\": \"Bluetooth Mouse\",\n    \"cost\": 29.99,\n    \"discount\": 10\n  }\n}",
            ],
            [
                'method' => 'POST',
                'path' => '/api/catalog-items/bulk',
                'description' => 'Imports many catalog items in a single request (upsert per itemId). Rows that fail validation are skipped and reported; valid rows are still imported. Maximum 10,000 items per request.',
                'params' => [
                    ['name' => 'items', 'in' => 'body', 'type' => 'array', 'required' => true, 'description' => 'A non-empty array (max 10,000) of item objects.', 'example' => '[ { … }, { … } ]'],
                    ['name' => 'items[].itemId', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Unique item ID for the row (max 50 chars).', 'example' => '"ITEM-1001"'],
                    ['name' => 'items[].itemName', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Item name for the row (max 255 chars).', 'example' => '"Bluetooth Mouse"'],
                    ['name' => 'items[].cost', 'in' => 'body', 'type' => 'number', 'required' => true, 'description' => 'Unit cost greater than 0.', 'example' => '29.99'],
                    ['name' => 'items[].discount', 'in' => 'body', 'type' => 'number', 'required' => false, 'description' => 'Optional discount percentage (0–100).', 'example' => '5'],
                ],
                'request' => "curl -X POST http://localhost:4000/api/catalog-items/bulk \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"items\": [\n      { \"itemId\": \"ITEM-1001\", \"itemName\": \"Mouse\", \"cost\": 29.99, \"discount\": 5 },\n      { \"itemId\": \"ITEM-1002\", \"itemName\": \"Keyboard\", \"cost\": 49.00 }\n    ]\n  }'",
                'response' => "{\n  \"message\": \"Imported 2 catalog item(s) successfully.\",\n  \"imported\": 2,\n  \"skipped\": 0,\n  \"errors\": []\n}",
            ],
            [
                'method' => 'GET',
                'path' => '/api/catalog-items',
                'description' => 'Lists catalog items. Behaviour depends on the query string: with no parameters it returns the full array of items; with page/limit it returns a paginated envelope; search filters by item ID or name and can be combined with pagination.',
                'params' => [
                    ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'required' => false, 'description' => 'Page number (1-based). When provided, the response is a paginated envelope { rows, total, page, limit }.', 'example' => '1'],
                    ['name' => 'limit', 'in' => 'query', 'type' => 'integer', 'required' => false, 'description' => 'Rows per page (1–1000, default 100). Triggers the paginated envelope.', 'example' => '100'],
                    ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false, 'description' => 'Case-insensitive substring match on item ID or item name. Alone (no page/limit) it returns all matching rows as an array.', 'example' => 'ITEM-10'],
                ],
                'request' => "curl 'http://localhost:4000/api/catalog-items?page=1&limit=2&search=ITEM-10'",
                'response' => "{\n  \"rows\": [\n    { \"itemId\": \"ITEM-1001\", \"itemName\": \"Bluetooth Mouse\", \"cost\": \"29.99\", \"discount\": 10 },\n    { \"itemId\": \"ITEM-1002\", \"itemName\": \"Keyboard\", \"cost\": \"49.00\", \"discount\": null }\n  ],\n  \"total\": 2,\n  \"page\": 1,\n  \"limit\": 2\n}",
            ],
            [
                'method' => 'GET',
                'path' => '/api/catalog-items/:itemId',
                'description' => 'Fetches a single catalog item by its ID. Returns 404 if no item matches.',
                'params' => [
                    ['name' => 'itemId', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'The ID of the item to retrieve.', 'example' => 'ITEM-1001'],
                ],
                'request' => 'curl http://localhost:4000/api/catalog-items/ITEM-1001',
                'response' => "{\n  \"itemId\": \"ITEM-1001\",\n  \"itemName\": \"Bluetooth Mouse\",\n  \"cost\": \"29.99\",\n  \"discount\": 10\n}",
            ],
        ],
    ],

    // ------------------------------------------------------------------- Stock
    [
        'id' => 'stock',
        'title' => 'Stock',
        'description' => 'Stock records track how many units of each catalog item are available. There is at most one stock record per catalog item; created and modified timestamps are maintained automatically.',
        'endpoints' => [
            [
                'method' => 'POST',
                'path' => '/api/stock',
                'description' => 'Sets the available units for a catalog item (upsert keyed on catalogItemId). The referenced catalog item must already exist, otherwise a 400 is returned.',
                'params' => [
                    ['name' => 'catalogItemId', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'ID of an existing catalog item to set stock for.', 'example' => '"ITEM-1001"'],
                    ['name' => 'unitsAvailable', 'in' => 'body', 'type' => 'integer', 'required' => true, 'description' => 'Number of units in stock. Must be an integer greater than or equal to 0.', 'example' => '120'],
                ],
                'request' => "curl -X POST http://localhost:4000/api/stock \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"catalogItemId\": \"ITEM-1001\",\n    \"unitsAvailable\": 120\n  }'",
                'response' => "{\n  \"message\": \"Stock record created/updated successfully\",\n  \"stock\": {\n    \"catalogItemId\": \"ITEM-1001\",\n    \"unitsAvailable\": 120\n  }\n}",
            ],
            [
                'method' => 'POST',
                'path' => '/api/stock/bulk',
                'description' => 'Imports many stock records in one request (upsert per catalogItemId). Rows whose catalog item does not exist, or that fail validation, are skipped and reported. Maximum 10,000 rows per request.',
                'params' => [
                    ['name' => 'items', 'in' => 'body', 'type' => 'array', 'required' => true, 'description' => 'A non-empty array (max 10,000) of stock objects.', 'example' => '[ { … }, { … } ]'],
                    ['name' => 'items[].catalogItemId', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'ID of an existing catalog item.', 'example' => '"ITEM-1001"'],
                    ['name' => 'items[].unitsAvailable', 'in' => 'body', 'type' => 'integer', 'required' => true, 'description' => 'Units in stock (integer ≥ 0).', 'example' => '500'],
                ],
                'request' => "curl -X POST http://localhost:4000/api/stock/bulk \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"items\": [\n      { \"catalogItemId\": \"ITEM-1001\", \"unitsAvailable\": 500 },\n      { \"catalogItemId\": \"ITEM-1002\", \"unitsAvailable\": 250 }\n    ]\n  }'",
                'response' => "{\n  \"message\": \"Imported 2 stock record(s) successfully.\",\n  \"imported\": 2,\n  \"skipped\": 0,\n  \"errors\": []\n}",
            ],
            [
                'method' => 'GET',
                'path' => '/api/stock',
                'description' => 'Lists stock levels joined with the item name. Like catalog items, it returns the full array with no parameters, or a paginated envelope with page/limit; search filters by catalog item ID or item name.',
                'params' => [
                    ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'required' => false, 'description' => 'Page number (1-based). Triggers the paginated envelope.', 'example' => '1'],
                    ['name' => 'limit', 'in' => 'query', 'type' => 'integer', 'required' => false, 'description' => 'Rows per page (1–1000, default 100).', 'example' => '100'],
                    ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false, 'description' => 'Substring match on catalog item ID or item name.', 'example' => 'ITEM-10'],
                ],
                'request' => "curl 'http://localhost:4000/api/stock?page=1&limit=2'",
                'response' => "{\n  \"rows\": [\n    {\n      \"catalogItemId\": \"ITEM-1001\",\n      \"itemName\": \"Bluetooth Mouse\",\n      \"unitsAvailable\": 120,\n      \"createdAt\": \"2026-07-23T10:15:00.000Z\",\n      \"updatedAt\": \"2026-07-23T10:15:00.000Z\"\n    }\n  ],\n  \"total\": 1,\n  \"page\": 1,\n  \"limit\": 2\n}",
            ],
            [
                'method' => 'GET',
                'path' => '/api/stock/:catalogItemId',
                'description' => 'Fetches the stock record for a single catalog item (including its name). Returns 404 if no stock record exists for that item.',
                'params' => [
                    ['name' => 'catalogItemId', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'ID of the catalog item whose stock to retrieve.', 'example' => 'ITEM-1001'],
                ],
                'request' => 'curl http://localhost:4000/api/stock/ITEM-1001',
                'response' => "{\n  \"catalogItemId\": \"ITEM-1001\",\n  \"itemName\": \"Bluetooth Mouse\",\n  \"unitsAvailable\": 120,\n  \"createdAt\": \"2026-07-23T10:15:00.000Z\",\n  \"updatedAt\": \"2026-07-23T10:15:00.000Z\"\n}",
            ],
        ],
    ],

    // ------------------------------------------------------------- Sales Orders
    [
        'id' => 'sales-orders',
        'title' => 'Sales Orders',
        'description' => 'A sales order records the sale of a catalog item. An order can contain several line items that share one transaction ID (uniqueness is enforced on the transaction ID + catalog item pair). The transaction date-time accepts either a space or a "T" between the date and time.',
        'endpoints' => [
            [
                'method' => 'POST',
                'path' => '/api/sales-orders',
                'description' => 'Creates or updates a single sales-order line (upsert keyed on transactionId + catalogItemId). The referenced catalog item must exist. Send one request per line item; reuse the same transactionId to group them into one order.',
                'params' => [
                    ['name' => 'transactionId', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Order/transaction identifier (max 50 chars). Reuse across line items to group them into one order.', 'example' => '"TXN-90001"'],
                    ['name' => 'transactionDateTime', 'in' => 'body', 'type' => 'string (datetime)', 'required' => true, 'description' => 'Date and time of the transaction. Accepts "YYYY-MM-DD HH:mm" or "YYYY-MM-DDTHH:mm" (the T is normalised to a space).', 'example' => '"2026-07-21 14:30"'],
                    ['name' => 'catalogItemId', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'ID of an existing catalog item being sold.', 'example' => '"ITEM-1001"'],
                    ['name' => 'quantity', 'in' => 'body', 'type' => 'number', 'required' => true, 'description' => 'Number of units sold. Must be greater than 0.', 'example' => '2'],
                    ['name' => 'price', 'in' => 'body', 'type' => 'number', 'required' => true, 'description' => 'Line price (typically quantity × unit cost). Must be greater than 0.', 'example' => '59.98'],
                ],
                'request' => "curl -X POST http://localhost:4000/api/sales-orders \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"transactionId\": \"TXN-90001\",\n    \"transactionDateTime\": \"2026-07-21 14:30\",\n    \"catalogItemId\": \"ITEM-1001\",\n    \"quantity\": 2,\n    \"price\": 59.98\n  }'",
                'response' => "{\n  \"message\": \"Sales order created/updated successfully\",\n  \"order\": {\n    \"transactionId\": \"TXN-90001\",\n    \"transactionDateTime\": \"2026-07-21 14:30\",\n    \"catalogItemId\": \"ITEM-1001\",\n    \"quantity\": 2,\n    \"price\": 59.98\n  }\n}",
            ],
            [
                'method' => 'GET',
                'path' => '/api/sales-orders',
                'description' => 'Lists sales-order lines joined with the item name, newest date first. Optionally filter to a single day with the date query parameter.',
                'params' => [
                    ['name' => 'date', 'in' => 'query', 'type' => 'string (YYYY-MM-DD)', 'required' => false, 'description' => 'Restrict results to orders whose transaction date equals this day. Must be in YYYY-MM-DD format, otherwise a 400 is returned.', 'example' => '2026-07-21'],
                ],
                'request' => "curl 'http://localhost:4000/api/sales-orders?date=2026-07-21'",
                'response' => "[\n  {\n    \"transactionId\": \"TXN-90001\",\n    \"transactionDateTime\": \"2026-07-21T14:30:00.000Z\",\n    \"transactionDate\": \"2026-07-21\",\n    \"catalogItemId\": \"ITEM-1001\",\n    \"itemName\": \"Bluetooth Mouse\",\n    \"quantity\": 2,\n    \"price\": \"59.98\"\n  }\n]",
            ],
        ],
    ],

    // ---------------------------------------------------------------- Feedback
    [
        'id' => 'feedback',
        'title' => 'Feedback',
        'description' => 'Stores user feedback about the application — suggested improvements and desired new features, with an optional rating and contact details.',
        'endpoints' => [
            [
                'method' => 'POST',
                'path' => '/api/feedback',
                'description' => 'Saves a feedback entry. At least one of improvements or newFeatures must be provided; the other fields are optional.',
                'params' => [
                    ['name' => 'name', 'in' => 'body', 'type' => 'string', 'required' => false, 'description' => 'Optional name of the person leaving feedback (max 255 chars).', 'example' => '"Jane Doe"'],
                    ['name' => 'email', 'in' => 'body', 'type' => 'string', 'required' => false, 'description' => 'Optional contact email (max 255 chars).', 'example' => '"jane@example.com"'],
                    ['name' => 'rating', 'in' => 'body', 'type' => 'integer', 'required' => false, 'description' => 'Optional overall rating as an integer from 1 to 5.', 'example' => '5'],
                    ['name' => 'improvements', 'in' => 'body', 'type' => 'string', 'required' => 'conditional', 'description' => 'What could be improved. Required if newFeatures is not provided.', 'example' => '"Faster search"'],
                    ['name' => 'newFeatures', 'in' => 'body', 'type' => 'string', 'required' => 'conditional', 'description' => 'Ideas for new features. Required if improvements is not provided.', 'example' => '"Export to CSV"'],
                ],
                'request' => "curl -X POST http://localhost:4000/api/feedback \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"name\": \"Jane\",\n    \"email\": \"jane@example.com\",\n    \"rating\": 5,\n    \"improvements\": \"Faster search\",\n    \"newFeatures\": \"Export to CSV\"\n  }'",
                'response' => "{\n  \"message\": \"Thank you! Your feedback has been recorded.\",\n  \"id\": 12\n}",
            ],
            [
                'method' => 'GET',
                'path' => '/api/feedback',
                'description' => 'Lists all submitted feedback, newest first.',
                'params' => [],
                'request' => 'curl http://localhost:4000/api/feedback',
                'response' => "[\n  {\n    \"id\": 12,\n    \"name\": \"Jane\",\n    \"email\": \"jane@example.com\",\n    \"rating\": 5,\n    \"improvements\": \"Faster search\",\n    \"newFeatures\": \"Export to CSV\",\n    \"createdAt\": \"2026-07-23T10:20:00.000Z\"\n  }\n]",
            ],
        ],
    ],
];

$howto = [
    'Start the backend with <code>npm run dev</code> (from <code>lab1</code>); it listens on <code>http://localhost:4000</code>.',
    'Send requests with any HTTP client — <code>curl</code>, Postman, or the browser <code>fetch()</code> API (this frontend uses <code>fetch</code>).',
    'For <code>POST</code> requests, set the header <code>Content-Type: application/json</code> and send a JSON body.',
    'Create catalog items first — stock records and sales orders reference them via a foreign key, so the item must exist first.',
    'Successful calls return <code>2xx</code> with JSON; validation problems return <code>400</code> and missing records return <code>404</code>, each with an <code>error</code> message.',
];

// Technology stack powering THIS PHP documentation page.
$phpStack = [
    ['label' => 'PHP', 'value' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION],
    ['label' => 'SAPI', 'value' => php_sapi_name()],
    ['label' => 'Web server', 'value' => 'Built-in'],
    ['label' => 'Framework', 'value' => 'None (vanilla)'],
    ['label' => 'Templating', 'value' => 'Inline PHP'],
    ['label' => 'Styling', 'value' => 'Plain CSS'],
];

$phpFunFacts = [
    'This page is rendered entirely server-side by PHP — there is no JavaScript framework here, unlike the React portal.',
    'It runs on PHP\'s lightweight built-in development server (<code>php -S</code>), completely independent of the Node.js backend and React frontend.',
    'Every endpoint card, parameter table and example you see is generated by looping over plain PHP arrays — change the data, and the docs update automatically.',
    'All dynamic output is escaped with <code>htmlspecialchars()</code> to keep the page safe from HTML/script injection.',
    'The page is a single self-contained <code>.php</code> file with inline CSS — no build step, no dependencies, no database.',
];

// "Start Here" — a friendly, jargon-free on-ramp for complete beginners.
$startHere = [
    [
        'heading' => 'What is Swiftcart, in one minute?',
        'body' => '<p>Swiftcart is a small web app for running a shop: you can add products to a <em>catalog</em>, track how much <em>stock</em> you have, record <em>sales orders</em>, and collect <em>feedback</em>. It is a learning project — its job is to show, end to end, how a real Node.js web app is built and how it can be hosted on Microsoft Azure.</p>'
            . '<p>Every web app like this has two halves that talk to each other:</p>'
            . '<ul>'
            . '<li><strong>The frontend</strong> — the screens you see and click, running in your browser (built with React).</li>'
            . '<li><strong>The backend</strong> — the "brain" that stores and fetches information, running on a server (built with Node.js).</li>'
            . '</ul>'
            . '<p>Behind the backend sits a <strong>database</strong> — think of it as a giant, well-organised spreadsheet in the cloud.</p>'
            . '<p><em>A simple example:</em> when you type in the search box, the frontend sends a little message to the backend ("find items matching mouse"); the backend asks the database; the database returns the matching rows; and the backend passes them back so the frontend can show them on screen. That round-trip is the heart of almost everything this app does.</p>',
    ],
    [
        'heading' => 'Key words in plain English',
        'body' => '<p>You will meet these words throughout the docs. Here is what each one really means:</p>'
            . '<ul>'
            . '<li><strong>Node.js</strong> — a way to run the JavaScript language on a server (not just in a browser). It powers the backend.</li>'
            . '<li><strong>npm</strong> — Node\'s "app store" for reusable code packages, plus the tool that installs them (<code>npm install</code>).</li>'
            . '<li><strong>Frontend / Backend</strong> — the part you see (browser) vs. the part that does the work behind the scenes (server).</li>'
            . '<li><strong>API</strong> — an "order window" the frontend talks to. It is a set of web addresses the backend answers.</li>'
            . '<li><strong>Endpoint</strong> — one specific address in that API, e.g. <code>/api/stock</code>.</li>'
            . '<li><strong>REST</strong> — a common, tidy style for designing those addresses (use <code>GET</code> to read, <code>POST</code> to save).</li>'
            . '<li><strong>JSON</strong> — the simple text format the frontend and backend use to exchange data (a list of name/value pairs).</li>'
            . '<li><strong>Database / SQL</strong> — where the data lives; SQL is the language used to ask it questions.</li>'
            . '<li><strong>Deploy / Host</strong> — putting the finished app on a computer in the cloud so other people can use it.</li>'
            . '<li><strong>Container</strong> — a sealed box that holds the app plus everything it needs, so it runs the same everywhere.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'How to read these docs',
        'body' => '<p>The sections build up gently, so reading top to bottom works well:</p>'
            . '<ol>'
            . '<li><strong>About Application</strong> — the big picture: what pieces exist and why each technology was chosen.</li>'
            . '<li><strong>Frontend State &amp; Routing</strong> — how the screens remember things and move between pages.</li>'
            . '<li><strong>Alternative Frontend Frameworks</strong> — what would change if we used Angular, Vue or Next.js.</li>'
            . '<li><strong>Knex &amp; the Database</strong> — how the backend safely talks to the database.</li>'
            . '<li><strong>Hosting on Azure</strong> — the different ways to put the app online, step by step.</li>'
            . '<li><strong>API Reference</strong> — the exact list of endpoints, for when you want the precise details.</li>'
            . '</ol>'
            . '<p>Tip for beginners: skim the everyday <em>analogies</em> first to get the idea, then come back for the code examples when you are ready to try them.</p>',
    ],
];

// "About Application" — architecture and technology rationale.
$aboutApp = [
    [
        'heading' => 'Architecture at a glance',
        'body' => '<p>Swiftcart is a classic <strong>three-tier web application</strong> with a clean separation of concerns:</p>'
            . '<ul>'
            . '<li><strong>Presentation tier</strong> — a React single-page application (SPA) built with Vite.</li>'
            . '<li><strong>Application tier</strong> — a Node.js + Express REST API that holds all business rules and validation.</li>'
            . '<li><strong>Data tier</strong> — Azure Database for MySQL, accessed through the Knex query builder over the mysql2 driver.</li>'
            . '</ul>'
            . '<p>In production the whole thing ships as <strong>one deployment</strong>: Express serves the compiled React assets as static files <em>and</em> exposes the <code>/api</code> routes from the same origin, so the browser talks to a single host with no CORS overhead. This documentation site is the only separate piece, served by PHP.</p>',
    ],
    [
        'heading' => 'Why React (with Vite) for the UI',
        'body' => '<p><strong>React</strong> provides a declarative, component-based model: the UI is described as a function of state, and React efficiently reconciles changes through its virtual DOM. Reusable components (forms, tables, toolbars) and hooks (<code>useState</code>, <code>useEffect</code>, <code>useMemo</code>) keep the code composable and predictable.</p>'
            . '<p><strong>Benefits over alternatives:</strong> compared to Angular it is lighter and less opinionated with a gentler learning curve; compared to jQuery/vanilla DOM scripting it offers declarative state and reusable components instead of manual DOM mutation; compared to server-rendered templates it delivers rich, reload-free interactivity (client-side routing, instant search, pagination). Its ecosystem and hiring pool are among the largest in the industry.</p>'
            . '<p>In Swiftcart every screen is its own route (React Router), and features like debounced search, server-side pagination, CSV/Excel/JSON export and print are all self-contained components.</p>',
    ],
    [
        'heading' => 'Why Node.js for the backend',
        'body' => '<p><strong>Node.js</strong> lets us write the backend in the <em>same language as the frontend</em> — JavaScript — so the team only has to think in one language across the whole app.</p>'
            . '<p><em>The key idea, with an analogy:</em> imagine one very efficient waiter. Instead of taking an order and then standing frozen at the kitchen until that meal is cooked, the waiter takes your order, hands it to the kitchen, and immediately serves other tables while the food is being prepared. Node.js works the same way — while it waits for the database to answer one request, it happily handles many others. This is called <strong>non-blocking</strong>, and it is why Node is great for apps (like Swiftcart) that spend most of their time waiting on the database.</p>'
            . '<p><strong>Why not something else?</strong> Older styles (and some other languages) traditionally dedicate one busy worker per request, which uses more resources under load. Node\'s "one tireless waiter" model handles lots of simultaneous requests efficiently, and its huge library of ready-made packages (npm) speeds up building.</p>'
            . '<p><strong>The Node ideas used in Swiftcart, in plain terms:</strong></p>'
            . '<ul>'
            . '<li><strong>The event loop / non-blocking I/O</strong> — the "efficient waiter" that never stands idle.</li>'
            . '<li><strong>async / await</strong> — a clean way to say "wait for the database, then continue" without freezing everything else. Every endpoint uses it.</li>'
            . '<li><strong>Middleware</strong> — a checklist each request passes through (security checks, reading the JSON body) before it reaches the code that answers it.</li>'
            . '<li><strong>Modules</strong> — code split into small files that <code>require</code> (import) each other.</li>'
            . '<li><strong>Environment variables</strong> (<code>process.env</code>) — settings like the database password kept <em>outside</em> the code so they can change per environment and stay secret.</li>'
            . '<li><strong>Error handling</strong> — every request is wrapped so that if something goes wrong, the app returns a tidy error message instead of crashing.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Why Express — and how the APIs were designed',
        'body' => '<p><strong>Express</strong> is a minimal, unopinionated web framework built around composable <strong>middleware</strong>. It gives just enough structure — routing and a request/response pipeline — without the boilerplate of a heavier framework.</p>'
            . '<p><strong>Benefits over alternatives:</strong> lighter than NestJS for a focused REST API, more mature and widely documented than newer frameworks, and far more convenient than the raw <code>http</code> module.</p>'
            . '<p>The API follows <strong>REST</strong> principles:</p>'
            . '<ul>'
            . '<li><strong>Resource-oriented routes</strong> — <code>/api/catalog-items</code>, <code>/api/stock</code>, <code>/api/sales-orders</code>, <code>/api/feedback</code>.</li>'
            . '<li><strong>Correct HTTP semantics</strong> — <code>GET</code> to read, <code>POST</code> to create/update, with status codes <code>200</code>/<code>201</code> on success and <code>400</code>/<code>404</code>/<code>500</code> on errors, always returning JSON.</li>'
            . '<li><strong>Stateless</strong> — every request is self-contained; no server-side session.</li>'
            . '<li><strong>Idempotent upserts</strong> — writes use unique keys so re-sending the same payload is safe.</li>'
            . '<li><strong>Consistent validation & errors</strong> — inputs are validated at the boundary and failures return a uniform <code>{ "error": "…" }</code> shape.</li>'
            . '</ul>'
            . '<p>The middleware order is deliberate: <code>helmet</code> (security headers) → <code>cors</code> (origin allow-list) → <code>express-rate-limit</code> → JSON body parser → route handlers → static React assets → JSON 404 → SPA fallback. Security is on by default (Helmet, CORS, rate limiting, parameterized queries, <code>x-powered-by</code> disabled).</p>',
    ],
    [
        'heading' => 'Role of Knex in the solution',
        'body' => '<p><strong>Knex</strong> is a SQL <em>query builder</em> — not a heavy ORM. It lets us compose database-agnostic queries in fluent JavaScript while keeping full control over the generated SQL.</p>'
            . '<p><strong>Benefits over alternatives:</strong> versus hand-written SQL strings it is safer (automatic parameter binding prevents SQL injection) and more composable; versus a full ORM like Sequelize or TypeORM it is lighter and more transparent, with no entity-mapping layer hiding the queries — important for tuning pagination and joins.</p>'
            . '<p><strong>Concepts used here:</strong> connection <em>pooling</em>; <em>upserts</em> via <code>onConflict().merge()</code>; <em>joins</em> (stock ↔ catalog items); server-side <em>pagination</em> with <code>limit</code>/<code>offset</code> plus a <code>count</code>; case-insensitive <em>search</em> with <code>where … like</code>; and <em>transactions</em> that make bulk imports all-or-nothing.</p>',
    ],
    [
        'heading' => 'Role of Vite in the solution',
        'body' => '<p><strong>Vite</strong> is the build tool and dev server for the React app. In development it serves native ES modules with near-instant <strong>hot module replacement</strong>; for production it bundles optimized static assets with Rollup.</p>'
            . '<p><strong>Benefits over alternatives:</strong> dramatically faster cold starts and HMR than Create-React-App/Webpack, with far simpler configuration; a first-class React plugin and a modern ESM-based pipeline.</p>'
            . '<p><strong>Concepts used here:</strong> the lightning-fast dev server for local work; a production build whose static output is copied into the backend so Express can serve it same-origin; and environment handling via <code>import.meta.env</code> (e.g. an empty <code>VITE_API_BASE_URL</code> in <code>.env.production</code> so the deployed SPA calls the API using relative <code>/api</code> paths).</p>',
    ],
];

// "Frontend State & Routing" — plain-English explanation of the SPA's state, routing and cookies.
$frontendNotes = [
    [
        'heading' => 'How state is managed (in simple terms)',
        'body' => '<p>State is simply "the data the screen is currently showing" — the rows in a table, what you typed in the search box, which page you are on, or whether an action succeeded.</p>'
            . '<ul>'
            . '<li>Swiftcart keeps this data using React\'s <strong>built-in hooks</strong>: <code>useState</code> holds values (like the list of items or a form\'s fields), <code>useEffect</code> loads data from the API when a page opens or the search changes, and <code>useMemo</code> calculates derived values (like an order total) without extra work.</li>'
            . '<li>Each page <strong>owns its own state</strong> — the Manage Catalog Items page tracks its own rows, page number and search text, separate from the Stock page. There is no single shared global store.</li>'
            . '<li>Data is fetched from the Node.js API with the browser\'s <code>fetch()</code> and kept <strong>in memory</strong> only while that page is open. Refreshing the browser re-fetches fresh data from the server.</li>'
            . '</ul>'
            . '<p>This keeps things simple and predictable: the screen is always a direct reflection of the current state, and the database remains the single source of truth.</p>',
    ],
    [
        'heading' => 'What libraries handle state management',
        'body' => '<p>There is <strong>no dedicated state-management library</strong> — no Redux, MobX, Zustand, Recoil, or even React Context for global state. State is handled entirely by <strong>React\'s own hooks</strong> (<code>useState</code>, <code>useEffect</code>, <code>useMemo</code>) from the <code>react</code> package.</p>'
            . '<p>Because the app\'s data lives in the database and is fetched per page, a heavy client-side store would add complexity without benefit. React\'s built-in hooks are enough, which keeps the bundle smaller and the code easier to follow.</p>',
    ],
    [
        'heading' => 'How routing (moving between pages) works',
        'body' => '<p>"Routing" simply means <strong>moving between screens</strong> — going from the Home page to the Stock page, for example. Swiftcart does this in a special way that makes it feel instant.</p>'
            . '<p><em>An analogy:</em> an old-style website is like a <strong>flip-book</strong> — each time you turn to a new page, the browser fetches a whole fresh sheet from the server, and the screen flashes blank for a moment. Swiftcart is a <strong>single-page application (SPA)</strong>, which is more like a <strong>whiteboard</strong>: the page is drawn once, and when you "change pages" the app just wipes the board and re-draws the new screen — no fetching a new sheet, no flashing.</p>'
            . '<p><em>What happens when you click "Manage Stock" in the sidebar, step by step:</em></p>'
            . '<ol>'
            . '<li>You click the <strong>Manage Stock</strong> link.</li>'
            . '<li>A helper called <strong>React Router</strong> (think of it as a traffic controller) notices the link points to the address <code>/stock</code>.</li>'
            . '<li>Instead of asking the server for a brand-new page, it just changes the address bar to end in <code>/stock</code> and <strong>instantly swaps the visible screen</strong> to the Stock page.</li>'
            . '<li>It also <strong>highlights "Manage Stock"</strong> in the sidebar so you can see where you are.</li>'
            . '</ol>'
            . '<p>All of that happens in the browser in a blink — the server is never asked for the page itself.</p>'
            . '<p><em>The pieces that make it work (from the <code>react-router-dom</code> library), in plain terms:</em></p>'
            . '<ul>'
            . '<li><strong>BrowserRouter</strong> — the main switch that turns the whole routing system on.</li>'
            . '<li><strong>Route</strong> — one rule that pairs an address with a screen, e.g. "<code>/stock</code> shows the Stock page".</li>'
            . '<li><strong>NavLink</strong> — a smart sidebar link that moves you without a reload and highlights the page you are on.</li>'
            . '</ul>'
            . '<p><em>One more thing — refreshing or sharing a link:</em> because it is really one page, if you <strong>refresh</strong> while on <code>/stock</code> (or paste that link to a colleague), the browser <em>does</em> ask the server for "<code>/stock</code>". The server has no separate <code>/stock</code> file, so it is set up to always hand back the single app page. The app then reads the address, sees <code>/stock</code>, and immediately shows the Stock screen. This trick is called the <strong>"SPA fallback"</strong>, and it is why bookmarks and refreshes still land you on the right screen.</p>',
    ],
    [
        'heading' => 'Does the application use cookies?',
        'body' => '<p><strong>No.</strong> Swiftcart does not create, read, or manage any cookies, and it does not install any cookie library.</p>'
            . '<ul>'
            . '<li>There is no login or session feature, so there is nothing to store in a cookie.</li>'
            . '<li>It also does not use <code>localStorage</code> or <code>sessionStorage</code> — all state is held in memory and resets when you refresh the page.</li>'
            . '<li>API calls are plain <code>fetch()</code> requests without credentials, so no session cookies are attached to them.</li>'
            . '</ul>'
            . '<p>In short, the app is stateless from a browser-storage perspective: nothing is persisted on your device between visits.</p>',
    ],
    [
        'heading' => 'When would Swiftcart need a state-management library?',
        'body' => '<p>First, what is "state"? It is just <strong>the information a screen is currently remembering</strong> — what you typed in the search box, which page you are on, the rows in a table. Today Swiftcart remembers these things <em>one page at a time</em>, and React\'s built-in tools handle that perfectly.</p>'
            . '<p>A <strong>state-management library</strong> is an extra tool that gives the app a <strong>shared memory</strong> — one place any screen can read from and write to. Think of it like a <strong>noticeboard in the office hallway</strong>: instead of each room keeping its own private notes, everyone reads and updates the same board. You only need such a board when several rooms must agree on the same information. Here are the everyday situations where Swiftcart would want one:</p>'
            . '<ul>'
            . '<li><strong>A shopping cart that follows you around.</strong> Right now you build an order on a single page. Imagine instead that you could add items while browsing the catalog on one page, then review them on a separate "Cart" page. Both pages must show the <em>same</em> cart. That shared cart is exactly the kind of thing you would put on the "noticeboard" (a shared store like Redux or Zustand).</li>'
            . '<li><strong>Knowing who is logged in.</strong> If Swiftcart had a login, then almost every screen would need to know "who is this person, and are they an admin?" — to decide what buttons to show. Rather than telling every screen separately, you pin that answer to the shared board once and every screen reads it.</li>'
            . '<li><strong>Remembering answers from the server so pages open instantly.</strong> Today each page re-asks the server for its data every time you open it. A data library like <strong>TanStack Query</strong> or <strong>SWR</strong> acts like a <em>smart notepad</em>: it keeps a copy of recent answers, shows them immediately, quietly refreshes them in the background, and avoids asking the same question twice.</li>'
            . '<li><strong>Lots of moving parts that must stay in sync (plus undo).</strong> Suppose a screen had many settings that affect each other — changing one automatically changes others — and you also wanted an "undo" button that steps back through every change. Keeping all of that correct by hand gets tricky. A library like <strong>Redux</strong> is built for exactly this: it records every change in one orderly place, so you can replay or undo them reliably. (This is what "interdependent state" means — pieces of information that are tied together, so touching one affects the rest.)</li>'
            . '<li><strong>Passing a value through many layers ("prop drilling").</strong> Picture a value that lives at the top of the app but is only needed by a button buried deep inside — say, five boxes-within-boxes down. Normally you have to hand it from box to box, all the way down, even though the boxes in between do not care about it. That tedious hand-me-down chain is nicknamed <strong>"prop drilling"</strong> (a "prop" is just a value passed into a component). A shared store, or React\'s built-in <strong>Context</strong>, lets the deep button grab the value directly instead of passing it down step by step.</li>'
            . '</ul>'
            . '<p><em>Rule of thumb:</em> reach for one of these tools only when <strong>different screens need to share the same information</strong>, or your data-loading needs get fancy. For today\'s "each page minds its own business" design, React\'s built-in tools are the right, lightweight choice.</p>',
    ],
    [
        'heading' => 'When would Swiftcart need more routing capability?',
        'body' => '<p>Today <code>react-router-dom</code> does everything Swiftcart needs: click a sidebar link, see the matching screen. You would only reach for its more advanced tricks in situations like these — each explained with the jargon <em>and</em> a plain example:</p>'
            . '<ul>'
            . '<li><strong>Protected (authenticated) routes.</strong> "Authenticated" just means "you have logged in". Imagine adding a login to Swiftcart so that only signed-in staff can open the <em>Manage Stock</em> page. A <strong>route guard</strong> is a gatekeeper you put in front of a page: before showing it, the guard checks "is this person logged in?" — if not, it <strong>redirects</strong> them (sends them) to the login screen. Without guards, anyone could reach protected pages by typing the address.'
                . '<br><br><em>Is it a library or a tool?</em> Neither — a route guard is not something you install. It is a <strong>pattern</strong> (a way of arranging code) that you write yourself using pieces React Router already gives you. The most common way is a tiny wrapper component. For example:'
                . '<pre><code>// RequireLogin wraps any page that needs a logged-in user.\nfunction RequireLogin({ children }) {\n  const isLoggedIn = Boolean(localStorage.getItem("token"));\n  // &lt;Navigate&gt; is React Router\'s way of redirecting.\n  if (!isLoggedIn) return &lt;Navigate to="/login" replace /&gt;;\n  return children; // logged in -&gt; show the real page\n}\n\n// Then \"guard\" a route by wrapping its page:\n&lt;Route\n  path="/stock"\n  element={&lt;RequireLogin&gt;&lt;StockPage /&gt;&lt;/RequireLogin&gt;}\n/&gt;</code></pre>'
                . 'So the "guard" is just that <code>RequireLogin</code> check running before the page renders. React Router 6.4+ can also do this check inside a route <strong>loader</strong> (see below) instead of a wrapper component — same idea, different spot.</li>'
            . '<li><strong>Shared nested layouts.</strong> A <strong>layout</strong> is the frame that stays the same while the inside changes. "Nested" means a screen inside another screen. Example: suppose you added a <em>Reports</em> area with three tabs — Sales, Stock, Feedback. All three should share the same heading and tab bar at the top, and only the content below the tabs should change as you switch. React Router\'s <strong>nested routes</strong> draw that shared frame once, and a placeholder called <code>&lt;Outlet&gt;</code> marks the spot where the changing inner page slides in — so you do not repeat the tab bar on every page.</li>'
            . '<li><strong>Code-splitting / lazy loading.</strong> When you build the app, all the pages\' code is normally bundled into one big file the browser must download before anything shows. <strong>Code-splitting</strong> chops that file into smaller pieces; <strong>lazy loading</strong> means "only download a page\'s piece the moment someone actually opens that page". Example: a rarely-used <em>Reports</em> page would no longer slow down the first load of the Home page, because its code is fetched only when needed (using <code>React.lazy</code> + <code>Suspense</code>).</li>'
            . '<li><strong>Route data loaders &amp; actions.</strong> Today each page loads its own data <em>after</em> it appears on screen (using a hook called <code>useEffect</code>), so you briefly see an empty page, then the data pops in. A <strong>loader</strong> flips this around: React Router fetches the data <em>first</em> and only shows the page once the data is ready — no empty flash. An <strong>action</strong> is the same idea for saving: it handles a form submission (like adding a catalog item) through the router instead of your own <code>fetch</code> call. This is available in React Router 6.4 and newer.</li>'
            . '<li><strong>SEO or server-side rendering (SSR).</strong> <strong>SEO</strong> ("search engine optimisation") is about letting Google find and list your pages. <strong>Server-side rendering</strong> means the server builds the finished HTML for a page <em>before</em> sending it, so it arrives ready-to-read (faster, and search engines can index it). Swiftcart is an internal, login-style tool with nothing to advertise on Google, so it does not need this. But a public shop front <em>would</em>, and then you would move to a framework such as <strong>Next.js</strong> or <strong>Remix</strong>, which build these features on top of the same routing ideas.</li>'
            . '</ul>'
            . '<p><em>Rule of thumb:</em> add these only when a real need appears — a login (guards), a section with shared tabs (nested layouts), a big app that loads slowly (code-splitting), or public pages that must show up on Google (SSR/SEO).</p>',
    ],
    [
        'heading' => 'Frontend libraries at a glance',
        'body' => '<ul>'
            . '<li><strong>react</strong> &amp; <strong>react-dom</strong> — the UI library and its hooks (this is also what manages state).</li>'
            . '<li><strong>react-router-dom</strong> — client-side routing between pages.</li>'
            . '<li><strong>xlsx</strong> — used in the browser to read uploaded spreadsheets and to generate CSV/Excel exports.</li>'
            . '<li><strong>vite</strong> (build tooling) — dev server and production bundler.</li>'
            . '</ul>'
            . '<p>Notably absent: any Redux-style store, and any cookie/session library.</p>',
    ],
];

// "Alternative Frameworks" — how the stack would differ with Angular, Vue or Next.js.
$altFrameworks = [
    [
        'heading' => 'The short answer',
        'body' => '<p>Only the <strong>frontend</strong> would change — the backend (Node.js + Express + Knex + MySQL) stays the same in every case. Swiftcart is a data-heavy internal admin app with no SEO need, so React + Vite is already a strong fit. Each alternative shines in a specific area, summarised below.</p>'
            . '<table class="params">'
            . '<thead><tr><th>Concern</th><th>React + Vite (today)</th><th>Angular</th><th>Vue 3</th><th>Next.js</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Routing</td><td>react-router-dom</td><td>Angular Router (built-in)</td><td>Vue Router (official)</td><td>File-based (built-in)</td></tr>'
            . '<tr><td>State</td><td>React hooks</td><td>RxJS + services / NgRx</td><td>Pinia (official)</td><td>Hooks + Server Components</td></tr>'
            . '<tr><td>Forms</td><td>manual checks</td><td>Reactive Forms (built-in)</td><td>VeeValidate</td><td>React Hook Form + Zod / Server Actions</td></tr>'
            . '<tr><td>Rendering</td><td>client-side SPA</td><td>client-side SPA</td><td>client-side SPA</td><td>server + static + client</td></tr>'
            . '</tbody></table>',
    ],
    [
        'heading' => 'Angular — "batteries included"',
        'body' => '<p>Stack: <strong>Angular</strong> + Angular CLI + RxJS + Angular Router + <code>HttpClient</code> + Reactive Forms, in TypeScript.</p>'
            . '<ul>'
            . '<li><strong>Form validation (biggest win):</strong> today Swiftcart checks <code>cost &gt; 0</code> and discount 0–100 by hand. Angular declares those rules once and shows errors automatically.</li>'
            . '<li><strong>Security:</strong> auto-escapes template output (XSS protection) and <code>HttpClient</code> has built-in XSRF-token support.</li>'
            . '<li><strong>Routing:</strong> built-in <em>guards</em> make "login required" pages trivial.</li>'
            . '<li><strong>State:</strong> RxJS services (or NgRx) give a structured, testable data flow.</li>'
            . '<li><strong>Trade-off:</strong> heavier with more ceremony — great for large enterprise teams, arguably overkill for a small tool.</li>'
            . '</ul>'
            . '<p><em>Example — the Add/Update Catalog Item form:</em></p>'
            . '<pre class="code">this.form = fb.group({\n  itemId:   [\'\', Validators.required],\n  cost:     [null, [Validators.required, Validators.min(0.01)]],\n  discount: [null, [Validators.min(0), Validators.max(100)]],\n});</pre>',
    ],
    [
        'heading' => 'Vue 3 — "React-like, lighter syntax"',
        'body' => '<p>Stack: <strong>Vue 3</strong> + <strong>Vite</strong> (Vue uses Vite too, so build speed is identical) + Vue Router + Pinia + VeeValidate.</p>'
            . '<ul>'
            . '<li><strong>Performance:</strong> lightweight and fast, with a small bundle — on par with React, sometimes smaller.</li>'
            . '<li><strong>Security:</strong> auto-escapes template output like Angular.</li>'
            . '<li><strong>State:</strong> Pinia (official) is simpler than Redux; reactivity makes features like the search box almost free.</li>'
            . '<li><strong>Routing / forms:</strong> official Vue Router (guards, lazy loading) and VeeValidate for declarative validation.</li>'
            . '<li><strong>Verdict:</strong> a lateral move from React — comparable performance with slightly less boilerplate.</li>'
            . '</ul>'
            . '<p><em>Example — the live search box becomes two lines:</em></p>'
            . '<pre class="code">&lt;input v-model="search" /&gt;\n&lt;!-- the table auto-updates from a computed/watched value --&gt;</pre>',
    ],
    [
        'heading' => 'Next.js — "React, but server-rendered"',
        'body' => '<p>Stack: <strong>Next.js</strong> (React-based) + file-based routing + Server Components/SSR + API routes (which could even replace Express). Your existing React components largely carry over.</p>'
            . '<ul>'
            . '<li><strong>Performance (biggest win):</strong> the Sales Orders or 8,500-item catalog page can be <em>server-rendered</em>, so the first screen arrives fully populated (faster first paint, and crawlable if SEO ever mattered). Automatic code-splitting and image optimization come for free.</li>'
            . '<li><strong>Security &amp; simplicity:</strong> data fetching runs on the server, so credentials never reach the browser; <strong>API routes could replace the separate Express layer</strong>, unifying front and back end.</li>'
            . '<li><strong>Forms:</strong> with <em>Server Actions</em>, submitting the catalog form validates and writes on the server — no separate <code>/api</code> endpoint, no CORS.</li>'
            . '<li><strong>Routing:</strong> file-based, with built-in layouts, loading states and middleware for auth.</li>'
            . '<li><strong>Trade-off:</strong> more moving parts (a server runtime); the SSR/SEO benefit is smaller for a login-gated internal tool.</li>'
            . '</ul>'
            . '<p><em>Example — a Server Action replaces the fetch + API endpoint:</em></p>'
            . '<pre class="code">async function saveItem(formData) {\n  "use server";\n  // validate + insert directly on the server — no fetch, no CORS\n}</pre>',
    ],
    [
        'heading' => 'So, would they benefit Swiftcart?',
        'body' => '<ul>'
            . '<li><strong>Form validation</strong> is the clearest upgrade in <em>any</em> of them — Angular (Reactive Forms), Vue (VeeValidate), or even staying with React and adding <strong>React Hook Form + Zod</strong> — replacing today\'s manual checks with clean, declarative rules.</li>'
            . '<li><strong>Next.js</strong> pays off if you want faster first-load of large lists, server-side data fetching, or to collapse the Express API into one app.</li>'
            . '<li><strong>Angular</strong> pays off for a large team that wants an opinionated, all-in-one structure with routing, DI and forms built in.</li>'
            . '<li><strong>Vue</strong> offers React-like benefits with arguably simpler syntax and official state/router libraries — a modest, comfortable gain.</li>'
            . '<li><strong>Performance &amp; security</strong> are already good today; the alternatives mostly change <em>developer experience</em> and built-in conveniences rather than dramatically outperforming the current stack for this kind of app.</li>'
            . '</ul>',
    ],
];

// "Knex & the Database" — how Knex talks to MySQL and how it compares with the alternatives.
$knexNotes = [
    [
        'heading' => 'How Knex talks to the MySQL database',
        'body' => '<p>Knex is a <strong>SQL query builder</strong>. You describe a query with chained JavaScript methods; Knex compiles that into real SQL, binds your values safely as parameters, and sends it to MySQL through the <code>mysql2</code> driver over a <strong>connection pool</strong> (Swiftcart keeps 2–10 pooled connections open so requests don\'t pay to reconnect each time).</p>'
            . '<p><em>Example — the catalog search + pagination query:</em></p>'
            . '<pre class="code">db("catalog_items")\n  .select({ itemId: "item_id", itemName: "item_name", cost: "cost", discount: "discount" })\n  .where("item_id", "like", `%${search}%`)   // value is bound, not concatenated\n  .orderBy("item_id", "asc")\n  .limit(100)\n  .offset(0);</pre>'
            . '<p>…which Knex turns into parameterized SQL:</p>'
            . '<pre class="code">SELECT item_id AS itemId, item_name AS itemName, cost, discount\nFROM catalog_items\nWHERE item_id LIKE ?          -- ? = "%search%"\nORDER BY item_id ASC\nLIMIT 100 OFFSET 0;</pre>'
            . '<p>Because the search term is a bound <code>?</code> parameter, it can never be interpreted as SQL — that is how Knex prevents SQL injection automatically. Swiftcart also uses Knex for <strong>joins</strong> (stock ↔ catalog items), <strong>upserts</strong> (<code>onConflict().merge()</code>), <strong>counts</strong> for pagination, and <strong>transactions</strong> that make bulk imports all-or-nothing.</p>',
    ],
    [
        'heading' => 'Knex vs Raw SQL vs ORM vs Schema toolkits — at a glance',
        'body' => '<p>Four common ways to talk to a database, from lowest-level to highest-level:</p>'
            . '<table class="params">'
            . '<thead><tr><th>Aspect</th><th>Raw SQL (mysql2)</th><th>Query builder (Knex — today)</th><th>ORM (Sequelize / TypeORM)</th><th>Schema toolkit (Prisma / Drizzle)</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Performance</td><td>Fastest, zero overhead</td><td>Near-raw, thin layer</td><td>Some overhead (object hydration, N+1 risk)</td><td>Thin (Drizzle) to moderate (Prisma engine)</td></tr>'
            . '<tr><td>Injection safety</td><td>Only if you bind manually</td><td>Safe by default (auto-bind)</td><td>Safe by default</td><td>Safe by default</td></tr>'
            . '<tr><td>Productivity</td><td>Low for dynamic queries</td><td>High for dynamic queries</td><td>High for CRUD &amp; relations</td><td>High, with generated client</td></tr>'
            . '<tr><td>Change management</td><td>Hand-written migrations</td><td>Built-in migrations &amp; seeds</td><td>Model-driven migrations</td><td>Declarative schema migrations</td></tr>'
            . '<tr><td>Control over SQL</td><td>Total</td><td>High (drop to <code>db.raw</code>)</td><td>Lower (generated SQL)</td><td>Medium–high</td></tr>'
            . '<tr><td>Type-safety</td><td>None</td><td>Minimal</td><td>Strong (TypeORM)</td><td>Strongest (generated types)</td></tr>'
            . '<tr><td>Learning curve</td><td>Just SQL</td><td>SQL + small API</td><td>Larger (associations, lifecycle)</td><td>Schema language + client</td></tr>'
            . '<tr><td>DB portability</td><td>Tied to dialect</td><td>Abstracts dialects</td><td>Abstracts dialects</td><td>Abstracts dialects</td></tr>'
            . '</tbody></table>',
    ],
    [
        'heading' => 'Performance',
        'body' => '<p><strong>Raw SQL</strong> is the ceiling — nothing is faster than the exact query you hand-write. <strong>Knex</strong> sits just below it: it is a thin builder that emits the SQL you designed, so for Swiftcart\'s indexed <code>LIMIT/OFFSET</code> pagination and simple joins the difference from raw is negligible. <strong>ORMs</strong> can be slower because they turn rows into objects and may trigger extra queries (the classic "N+1" problem when loading related records lazily). <strong>Prisma</strong> historically ran a separate query engine (small overhead); <strong>Drizzle</strong> is deliberately thin and fast.</p>'
            . '<p><em>Swiftcart example:</em> loading page 5 of 8,500 catalog items is one indexed query in Knex — the same query you would write by hand. An ORM might additionally hydrate 100 model instances per page, which is fine here but adds up on hot paths.</p>',
    ],
    [
        'heading' => 'Security',
        'body' => '<p>All three managed approaches (Knex, ORM, toolkit) bind values as parameters automatically, so they are safe from SQL injection by default. <strong>Raw SQL is only safe if you remember to parameterize every value yourself</strong> — string concatenation like <code>"… WHERE item_id = \'" + input + "\'"</code> is exactly how injection happens.</p>'
            . '<p><em>Swiftcart example:</em> the search box feeds user text straight into <code>.where("item_id", "like", `%${search}%`)</code>. Knex sends it as a bound <code>?</code>, so typing <code>\' OR 1=1 --</code> searches for that literal string instead of breaking out of the query.</p>',
    ],
    [
        'heading' => 'Developer productivity',
        'body' => '<p><strong>Knex</strong> shines for <em>dynamic</em> queries: Swiftcart conditionally adds a <code>WHERE</code> only when a search term is present, then reuses the same builder for both the rows and the count — hard to do cleanly with raw string concatenation.</p>'
            . '<pre class="code">const rows = db("catalog_items").select(cols).orderBy("item_id").limit(100).offset(off);\nconst count = db("catalog_items");\nif (search) {                     // add the filter to BOTH queries\n  rows.where("item_id", "like", `%${search}%`);\n  count.where("item_id", "like", `%${search}%`);\n}</pre>'
            . '<p><strong>ORMs</strong> are most productive for record-centric CRUD and relationships (<code>order.getItems()</code>), and <strong>toolkits like Prisma</strong> generate a fully typed client from a schema, which is very fast to work with. <strong>Raw SQL</strong> is the least productive for anything that changes shape at runtime.</p>',
    ],
    [
        'heading' => 'Ease of change management',
        'body' => '<p>Swiftcart\'s schema has grown over time — discount column added, composite unique key for shared transaction IDs, new feedback and stock tables. Each approach handles that evolution differently:</p>'
            . '<ul>'
            . '<li><strong>Raw SQL:</strong> you write and run <code>ALTER TABLE</code> scripts by hand (which is essentially what Swiftcart\'s <code>backend/sql</code> migration files do today).</li>'
            . '<li><strong>Knex:</strong> ships a <em>built-in migration and seed system</em> — versioned migration files with <code>up</code>/<code>down</code> functions, run in order and tracked in a table, so schema changes are repeatable across environments.</li>'
            . '<li><strong>ORM:</strong> can generate migrations by diffing your models, or auto-sync in development.</li>'
            . '<li><strong>Prisma:</strong> a single declarative <code>schema.prisma</code> file drives <code>prisma migrate</code> — arguably the smoothest schema workflow, at the cost of adopting its modelling language.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Other factors worth weighing',
        'body' => '<ul>'
            . '<li><strong>Predictability / debuggability:</strong> with Knex you can call <code>query.toSQL()</code> to see the exact SQL — no hidden "magic". ORMs can generate surprising queries.</li>'
            . '<li><strong>Escape hatch:</strong> Knex lets you drop to <code>db.raw()</code> for anything the builder can\'t express, so you\'re never boxed in.</li>'
            . '<li><strong>Transactions:</strong> Knex\'s <code>db.transaction()</code> wraps Swiftcart\'s bulk imports so a bad row rolls the whole batch back.</li>'
            . '<li><strong>Dependency weight:</strong> Knex is light; heavier ORMs/toolkits add more to install, learn and keep updated.</li>'
            . '<li><strong>Team familiarity:</strong> Knex rewards existing SQL knowledge; ORMs ask the team to learn a new mental model (entities, associations, lifecycles).</li>'
            . '<li><strong>Testing:</strong> query builders are easy to unit-test and mock; ORM internals are harder to isolate.</li>'
            . '<li><strong>Connection pooling &amp; portability:</strong> Knex gives pooling and cross-dialect support out of the box (swap MySQL for Postgres by changing config, not queries).</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Why Knex fits Swiftcart',
        'body' => '<p>Swiftcart is an SQL-shaped, data-heavy app with dynamic list/search/pagination queries, joins, upserts and bulk transactions — but no need for a rich object/relationship model. Knex hits the sweet spot: <strong>raw-SQL performance and control, automatic injection safety, and query-builder productivity</strong>, without the weight or abstraction of a full ORM. If the domain later grew many interrelated entities and the team wanted strong types and generated clients, moving to <strong>Prisma</strong> (or TypeORM) would be the natural next step.</p>',
    ],
];

// "Hosting on Azure" — beginner-friendly stories about deploying Swiftcart.
$azureHosting = [
    [
        'heading' => 'Is Swiftcart a monolith or microservices? (a quick story)',
        'body' => '<p>Imagine a small corner shop run by one shopkeeper who handles the till, the shelves and the stockroom. That is a <strong>monolith</strong> — one person (one app) doing everything. Now imagine a big supermarket where a separate team runs the bakery, another the deli, another the checkout, each able to open or close independently. That is <strong>microservices</strong> — many small, independently deployed services.</p>'
            . '<p><strong>Swiftcart is a monolith</strong> (more precisely, a tidy "modular monolith"). One Express app serves <em>all</em> the endpoints — catalog, stock, sales orders and feedback are just groups of routes inside the same <code>server.js</code>, talking to one MySQL database, and shipped as a single unit that also serves the React app.</p>'
            . '<p>If Swiftcart were microservices, you might have a separate Catalog service, Stock service and Orders service — each its own deployable app, often its own database, talking over HTTP. That adds power and independence but also a lot of moving parts. For an app this size, the monolith is simpler, cheaper and easier to run. You would only split it up later if different parts needed to scale or ship on very different schedules.</p>',
    ],
    [
        'heading' => 'What actually needs hosting?',
        'body' => '<p>Good news: there are only two real pieces to run.</p>'
            . '<ul>'
            . '<li><strong>The app</strong> — the Node.js/Express server. It does double duty: it serves the compiled React files <em>and</em> answers the <code>/api</code> calls from the same place.</li>'
            . '<li><strong>The database</strong> — already hosted as <strong>Azure Database for MySQL Flexible Server</strong>, a fully managed service (backups and patching are handled for you).</li>'
            . '</ul>'
            . '<p>So "hosting Swiftcart" mostly means "finding a home for the Node app" and pointing it at the managed database. (The PHP documentation site is a small optional extra you can host anywhere PHP runs.)</p>',
    ],
    [
        'heading' => 'Ways to host on Azure — at a glance',
        'body' => '<table class="params">'
            . '<thead><tr><th>Azure service</th><th>Think of it as…</th><th>Effort</th><th>Best for</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>App Service</td><td>A fully-managed apartment</td><td>Lowest</td><td>The recommended home for this monolith</td></tr>'
            . '<tr><td>Container Apps</td><td>Shipping containers, auto-managed</td><td>Low–medium</td><td>Docker, scale-to-zero, future microservices</td></tr>'
            . '<tr><td>AKS (Kubernetes)</td><td>A large container port with cranes</td><td>High</td><td>Many microservices at big scale</td></tr>'
            . '<tr><td>Virtual Machine</td><td>Your own house (you fix the plumbing)</td><td>Highest</td><td>Full control / special requirements</td></tr>'
            . '</tbody></table>'
            . '<p>The rest of this section walks through each one as a short story.</p>',
    ],
    [
        'heading' => 'Option A — Azure App Service (the easy default)',
        'body' => '<p><em>Story:</em> renting a fully-managed apartment. You bring your furniture (your code); the building handles water, power, security and repairs (the OS, HTTPS, patching, load balancing).</p>'
            . '<p>You point App Service at your Node app; it runs <code>npm start</code>, gives you a public HTTPS URL, a custom domain, and one-click scaling. Because Swiftcart is a single Node app that also serves the React build, App Service is the simplest and best fit. Set your database connection and secrets as <em>App Settings</em>, and you are live.</p>',
    ],
    [
        'heading' => 'Option B — Azure Container Apps (containers, made easy)',
        'body' => '<p><em>Story:</em> shipping your shop inside a standard container so it runs the same everywhere. You write a small <code>Dockerfile</code>, build an image, and Container Apps runs it for you — no servers to manage.</p>'
            . '<p>Its superpowers are <strong>scale-to-zero</strong> (pay nothing when idle) and easy scaling on HTTP traffic. It is also a gentle stepping stone if you ever break Swiftcart into microservices, since each service becomes its own container app.</p>',
    ],
    [
        'heading' => 'Option C — Azure Kubernetes Service (AKS)',
        'body' => '<p><em>Story:</em> a giant container port with cranes, schedules and traffic control. Kubernetes orchestrates many containers across many machines, with fine-grained control over networking, scaling and rollouts.</p>'
            . '<p>It is extremely powerful but has a steep learning curve — genuinely useful when you run <em>many</em> microservices at large scale, and usually overkill for a single monolith like Swiftcart today.</p>',
    ],
    [
        'heading' => 'Option D — Virtual Machine (full control)',
        'body' => '<p><em>Story:</em> buying your own house. You get total control, but you also install Node, configure the web server, set up HTTPS, apply security patches and keep it running yourself.</p>'
            . '<p>Choose a VM only when you have special requirements the managed options cannot meet. For most teams it is more maintenance than it is worth.</p>',
    ],
    [
        'heading' => 'Can we containerize Swiftcart for ACA or AKS? (yes!)',
        'body' => '<p><strong>Yes — and it is a great fit.</strong> Being a monolith does not stop containerization; a container simply packages the <em>one</em> Node app into a portable image. Both Azure Container Apps (ACA) and Azure Kubernetes Service (AKS) run that image happily.</p>'
            . '<p>The good news is that Swiftcart is already "container-friendly":</p>'
            . '<ul>'
            . '<li>It is <strong>stateless</strong> — no sessions kept in memory — so you can run many copies safely.</li>'
            . '<li>It reads <strong>all configuration from environment variables</strong> (via <code>process.env</code>), so nothing is hard-coded.</li>'
            . '<li>It <strong>listens on a configurable port</strong> (<code>PORT</code>, default 4000) and serves the React build from the same process.</li>'
            . '<li>It exposes <code>/api/health</code>, perfect for container health probes.</li>'
            . '</ul>'
            . '<p>The only thing missing today is a <strong>Dockerfile</strong> (the recipe that turns the code into an image). Here is the short checklist of what to add:</p>'
            . '<ol>'
            . '<li>Add a <strong>Dockerfile</strong> that builds the React app, then packages the Node backend with that build inside it.</li>'
            . '<li>Add a <strong>.dockerignore</strong> so junk (node_modules, the local <code>.env</code>) is not copied into the image.</li>'
            . '<li><strong>Do not bake secrets into the image.</strong> Pass the database host/user/password as environment variables or secrets at run time — the app already reads them from <code>process.env</code>.</li>'
            . '<li>Push the image to <strong>Azure Container Registry (ACR)</strong>.</li>'
            . '<li>Point the platform\'s health probe at <code>/api/health</code>.</li>'
            . '</ol>'
            . '<p>No application code changes are required — just these packaging additions.</p>',
    ],
    [
        'heading' => 'Step 1 — Add a Dockerfile and .dockerignore',
        'body' => '<p>Create <code>Dockerfile</code> at the project root. It uses two stages: the first builds the React app, the second runs the Node server with that build copied in.</p>'
            . '<pre class="code"># ---- Stage 1: build the React frontend ----\nFROM node:20-alpine AS frontend\nWORKDIR /app/frontend\nCOPY frontend/package*.json ./\nRUN npm ci\nCOPY frontend/ ./\nRUN npm run build          # produces frontend/dist\n\n# ---- Stage 2: backend that also serves the built React app ----\nFROM node:20-alpine AS backend\nWORKDIR /app/backend\nCOPY backend/package*.json ./\nRUN npm ci --omit=dev\nCOPY backend/ ./\n# copy the compiled React app into backend/public\nCOPY --from=frontend /app/frontend/dist ./public\n\nENV NODE_ENV=production\nENV PORT=4000\nEXPOSE 4000\nCMD ["node", "src/server.js"]</pre>'
            . '<p>And a <code>.dockerignore</code> so the image stays small and safe:</p>'
            . '<pre class="code">**/node_modules\n**/.env\nfrontend/dist\nbackend/public</pre>'
            . '<p>Test it locally: <code>docker build -t swiftcart:v1 .</code> then <code>docker run -p 4000:4000 --env-file backend/.env swiftcart:v1</code>.</p>',
    ],
    [
        'heading' => 'Step 2 — Push the image to Azure Container Registry',
        'body' => '<p>Both ACA and AKS pull the image from a registry. The easiest way is to let ACR build it for you in the cloud:</p>'
            . '<pre class="code"># create a registry once\naz acr create --resource-group swiftcart-rg --name swiftcartacr --sku Basic\n\n# build the image from the Dockerfile and store it in ACR\naz acr build --registry swiftcartacr --image swiftcart:v1 .</pre>'
            . '<p>You now have <code>swiftcartacr.azurecr.io/swiftcart:v1</code> ready to deploy.</p>',
    ],
    [
        'heading' => 'Hosting on Azure Container Apps (ACA) — step by step',
        'body' => '<p><em>Simplest container option.</em> ACA runs your image, gives it an HTTPS address, and scales on traffic (even to zero when idle).</p>'
            . '<pre class="code"># 1) create the Container Apps environment (once)\naz containerapp env create \\\n  --name swiftcart-env --resource-group swiftcart-rg --location eastus\n\n# 2) create the app from the image\naz containerapp create \\\n  --name swiftcart \\\n  --resource-group swiftcart-rg \\\n  --environment swiftcart-env \\\n  --image swiftcartacr.azurecr.io/swiftcart:v1 \\\n  --target-port 4000 \\\n  --ingress external \\\n  --registry-server swiftcartacr.azurecr.io \\\n  --secrets db-password=YOUR_DB_PASSWORD \\\n  --env-vars \\\n      DB_HOST=vt-mysql-flex-01.mysql.database.azure.com \\\n      DB_USER=vtadmin \\\n      DB_NAME=sales_db \\\n      DB_SSL=true \\\n      DB_PASSWORD=secretref:db-password</pre>'
            . '<p>In plain terms: create an environment, then a container app that listens on port 4000, is reachable from the internet, and gets its database settings as environment variables — with the password stored as a secret (<code>secretref</code>). Scaling rules and a health probe on <code>/api/health</code> can be added with a few more flags. This is the recommended container path for a single monolith.</p>',
    ],
    [
        'heading' => 'Hosting on Azure Kubernetes Service (AKS) — step by step',
        'body' => '<p><em>More power, more moving parts.</em> You describe the app in a YAML manifest and Kubernetes runs and heals it.</p>'
            . '<pre class="code"># 1) create a cluster and let it pull from your registry\naz aks create --resource-group swiftcart-rg --name swiftcart-aks \\\n  --node-count 2 --attach-acr swiftcartacr --generate-ssh-keys\n\n# 2) get credentials so kubectl can talk to the cluster\naz aks get-credentials --resource-group swiftcart-rg --name swiftcart-aks\n\n# 3) store the DB password as a Kubernetes secret\nkubectl create secret generic swiftcart-secrets \\\n  --from-literal=db-password=YOUR_DB_PASSWORD</pre>'
            . '<p>Then apply a <code>deployment.yaml</code> describing the app and how to reach it:</p>'
            . '<pre class="code">apiVersion: apps/v1\nkind: Deployment\nmetadata:\n  name: swiftcart\nspec:\n  replicas: 2\n  selector:\n    matchLabels:\n      app: swiftcart\n  template:\n    metadata:\n      labels:\n        app: swiftcart\n    spec:\n      containers:\n        - name: swiftcart\n          image: swiftcartacr.azurecr.io/swiftcart:v1\n          ports:\n            - containerPort: 4000\n          env:\n            - name: DB_HOST\n              value: "vt-mysql-flex-01.mysql.database.azure.com"\n            - name: DB_USER\n              value: "vtadmin"\n            - name: DB_NAME\n              value: "sales_db"\n            - name: DB_SSL\n              value: "true"\n            - name: DB_PASSWORD\n              valueFrom:\n                secretKeyRef:\n                  name: swiftcart-secrets\n                  key: db-password\n          readinessProbe:\n            httpGet:\n              path: /api/health\n              port: 4000\n---\napiVersion: v1\nkind: Service\nmetadata:\n  name: swiftcart\nspec:\n  type: LoadBalancer\n  selector:\n    app: swiftcart\n  ports:\n    - port: 80\n      targetPort: 4000</pre>'
            . '<pre class="code"># 4) deploy it, then let it scale on CPU\nkubectl apply -f deployment.yaml\nkubectl autoscale deployment swiftcart --cpu-percent=70 --min=2 --max=10</pre>'
            . '<p>In plain terms: the Deployment runs two copies of the container (reading the DB password from a secret and health-checked on <code>/api/health</code>); the Service gives them one public address; and the autoscaler adds copies when CPU gets busy.</p>',
    ],
    [
        'heading' => 'ACA or AKS — which should Swiftcart use?',
        'body' => '<p>For a single monolith like Swiftcart today, <strong>Azure Container Apps is the better choice</strong>: it is simpler, cheaper, scales to zero, and needs no Kubernetes knowledge. <strong>AKS</strong> earns its keep when you run many microservices, need fine-grained networking/rollout control, or already have a Kubernetes platform team. A common path is to start on ACA and graduate to AKS only if that complexity is truly needed.</p>',
    ],
    [
        'heading' => 'Things to consider before you host',
        'body' => '<ul>'
            . '<li><strong>Database connection:</strong> keep TLS/SSL on, and lock down access — allow only your app (firewall rule or, better, a Private Endpoint so the DB is not on the public internet).</li>'
            . '<li><strong>Secrets:</strong> never ship the <code>.env</code> file. Put connection details in App Settings or, best, <strong>Azure Key Vault</strong>, and read them at runtime.</li>'
            . '<li><strong>Same-origin frontend:</strong> because Express serves the React build, the browser and API share one origin — simple and CORS-free. Keep it that way in production.</li>'
            . '<li><strong>Region:</strong> host the app in the <em>same Azure region</em> as the MySQL server so database calls are fast.</li>'
            . '<li><strong>Stateless app:</strong> Swiftcart keeps no session in memory, so you can safely run multiple copies behind a load balancer — this is what makes scaling out easy.</li>'
            . '<li><strong>Cost &amp; scale needs:</strong> start small; pick a plan you can scale up (bigger box) or out (more boxes) later.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Best practices — Security',
        'body' => '<ul>'
            . '<li><strong>HTTPS only:</strong> redirect all traffic to HTTPS (App Service can enforce this with a toggle).</li>'
            . '<li><strong>Secrets in Key Vault:</strong> store the DB password/connection and any keys in Key Vault; even better, use a <strong>Managed Identity</strong> so the app authenticates to MySQL passwordless-ly.</li>'
            . '<li><strong>Lock down the database:</strong> Private Endpoint or tight firewall rules; never leave it open to all IPs.</li>'
            . '<li><strong>Keep the built-in guards:</strong> Swiftcart already uses Helmet (security headers), a CORS allow-list, rate limiting and parameterized queries — keep them on.</li>'
            . '<li><strong>Add a WAF:</strong> front the app with Azure Front Door or Application Gateway (Web Application Firewall) to block common attacks.</li>'
            . '<li><strong>Patch dependencies:</strong> run <code>npm audit</code> regularly and keep packages current.</li>'
            . '<li><strong>Least privilege:</strong> give the app\'s database user only the permissions it needs.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Best practices — Performance',
        'body' => '<ul>'
            . '<li><strong>Serve static assets fast:</strong> put a CDN or Azure Front Door in front so the React files load from an edge near the user.</li>'
            . '<li><strong>Compression:</strong> enable gzip/brotli so responses are smaller.</li>'
            . '<li><strong>Reuse DB connections:</strong> Knex pooling is already on — keep the pool sized sensibly for your plan.</li>'
            . '<li><strong>Index &amp; paginate:</strong> Swiftcart already uses indexes and server-side pagination/search, which keeps big lists (8,500+ items) fast.</li>'
            . '<li><strong>Cache hot reads:</strong> for very frequent, rarely-changing data, add Azure Cache for Redis in front of the database.</li>'
            . '<li><strong>Scale out, not just up:</strong> because the app is stateless, adding more instances usually helps more than a bigger single box.</li>'
            . '<li><strong>Right region &amp; keep-alive:</strong> co-locate with the DB and reuse HTTP connections to cut latency.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Monitoring & scaling out — per hosting option',
        'body' => '<p><strong>Monitoring (everywhere):</strong> wire in <strong>Application Insights</strong> to see request rates, response times, failures and live traces, and use <strong>Azure Monitor / Log Analytics</strong> for logs and alerts. Swiftcart already exposes a <code>/api/health</code> endpoint — point the platform\'s health check at it so unhealthy instances are replaced automatically.</p>'
            . '<ul>'
            . '<li><strong>App Service:</strong> turn on <em>Autoscale</em> rules (add instances when CPU/memory crosses a threshold, or on a schedule); built-in metrics + App Insights.</li>'
            . '<li><strong>Container Apps:</strong> scale rules on HTTP concurrency or KEDA triggers, including <em>scale-to-zero</em> when idle.</li>'
            . '<li><strong>AKS:</strong> Horizontal Pod Autoscaler for pods plus the Cluster Autoscaler for nodes; monitor with Container Insights.</li>'
            . '<li><strong>Virtual Machine:</strong> use a Virtual Machine Scale Set to add/remove VMs based on load.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'CI/CD with Azure DevOps Pipelines',
        'body' => '<p><em>Story:</em> a conveyor belt. Every time you push code, the belt builds the app and delivers it to Azure automatically — no manual copying. "CI" builds and checks your code; "CD" ships it.</p>'
            . '<p>Create an <code>azure-pipelines.yml</code> in your repo:</p>'
            . '<pre class="code">trigger:\n  - main                      # run on every push to main\n\npool:\n  vmImage: ubuntu-latest\n\nsteps:\n  - task: NodeTool@0\n    inputs:\n      versionSpec: "20.x"\n\n  - script: npm ci\n    workingDirectory: frontend\n    displayName: Install frontend deps\n\n  - script: npm run build\n    workingDirectory: frontend\n    displayName: Build React app\n\n  - script: npm ci\n    workingDirectory: backend\n    displayName: Install backend deps\n\n  - script: node scripts/copy-frontend.js\n    displayName: Copy React build into backend/public\n\n  - task: AzureWebApp@1        # the CD step: deploy to App Service\n    inputs:\n      azureSubscription: "my-azure-connection"\n      appName: "swiftcart-api"\n      package: backend</pre>'
            . '<p>In plain terms: install and build the React app, copy it into the backend, then hand the backend folder to App Service. The <code>azureSubscription</code> is a one-time "service connection" you set up in Azure DevOps so it is allowed to deploy.</p>',
    ],
    [
        'heading' => 'CI/CD with GitHub Actions',
        'body' => '<p>The same conveyor belt, but driven from GitHub. Add a file at <code>.github/workflows/deploy.yml</code>:</p>'
            . '<pre class="code">name: Deploy Swiftcart\n\non:\n  push:\n    branches: [ main ]         # run on every push to main\n\njobs:\n  build-and-deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - uses: actions/checkout@v4\n      - uses: actions/setup-node@v4\n        with:\n          node-version: 20\n\n      - run: npm ci\n        working-directory: frontend\n      - run: npm run build\n        working-directory: frontend\n\n      - run: npm ci\n        working-directory: backend\n      - run: node scripts/copy-frontend.js\n\n      - uses: azure/webapps-deploy@v3    # the CD step\n        with:\n          app-name: swiftcart-api\n          package: backend\n          publish-profile: ${{ secrets.AZURE_WEBAPP_PUBLISH_PROFILE }}</pre>'
            . '<p>The steps mirror the Azure DevOps version. The one secret — <code>AZURE_WEBAPP_PUBLISH_PROFILE</code> — is copied from your App Service and stored in the repository\'s encrypted Secrets, so the workflow can deploy without exposing any password.</p>',
    ],
    [
        'heading' => 'Recommended setup for Swiftcart',
        'body' => '<p>For a beginner-friendly, production-ready home:</p>'
            . '<ul>'
            . '<li><strong>Azure App Service (Linux, Node 20)</strong> to run the app.</li>'
            . '<li><strong>Azure Database for MySQL Flexible Server</strong> (already in use) in the same region, reached over a Private Endpoint.</li>'
            . '<li><strong>Azure Key Vault</strong> for secrets + a <strong>Managed Identity</strong> for passwordless DB access.</li>'
            . '<li><strong>Application Insights</strong> for monitoring, with Autoscale rules and the <code>/api/health</code> check.</li>'
            . '<li><strong>Azure Front Door / CDN</strong> (optional) for a WAF and fast global static delivery.</li>'
            . '<li><strong>GitHub Actions or Azure DevOps</strong> for automated build-and-deploy.</li>'
            . '</ul>'
            . '<p>This keeps the monolith simple to operate while ticking the security, performance, monitoring and automation boxes — and leaves room to grow into containers or microservices later if you ever need to.</p>',
    ],
];

$saasEvolution = [
    [
        'heading' => 'What changes when Swiftcart becomes a SaaS?',
        'body' => '<p><strong>SaaS</strong> ("Software as a Service") means many different customers pay to use <em>one</em> running copy of your app over the internet — think Gmail or Shopify. Today Swiftcart is a single-company tool; as a SaaS it would serve, say, hundreds of shops at once, each seeing only their own catalog, stock and orders.</p>'
            . '<p>That shift puts pressure on three things, and this section walks through the JavaScript patterns, frameworks and libraries that help with each:</p>'
            . '<ul>'
            . '<li><strong>Security</strong> — now that strangers on the internet log in, you must prove <em>who</em> each user is, keep one customer\'s data away from another\'s, and defend against attackers.</li>'
            . '<li><strong>Performance</strong> — more users means more requests; each one must stay fast without melting the server or the database.</li>'
            . '<li><strong>Scale-out</strong> — you can no longer rely on one bigger machine; you must be able to run <em>many copies</em> of the app side by side and add more on demand.</li>'
            . '</ul>'
            . '<p>The good news: Swiftcart\'s current design (stateless app, config from environment variables, Knex pooling, server-side pagination) is already a solid foundation. The steps below are additions, not rewrites.</p>',
    ],
    [
        'heading' => 'Multi-tenancy — serving many customers from one app',
        'body' => '<p>A <strong>tenant</strong> is one customer organisation (one shop) using your SaaS. <strong>Multi-tenancy</strong> is the art of letting many tenants share the same app and database while making sure they can never see each other\'s data. This is the single most important idea in SaaS.</p>'
            . '<p>Three common approaches, from simplest to most isolated:</p>'
            . '<ul>'
            . '<li><strong>Shared database, shared tables (a <code>tenant_id</code> column).</strong> Every row carries a <code>tenant_id</code>, and <em>every</em> query filters by it. Cheapest and easiest to run — the usual starting point. Example: <code>catalog_items</code> gains a <code>tenant_id</code>, and "list items" becomes "list items <em>where tenant_id = the current user\'s tenant</em>".</li>'
            . '<li><strong>Shared database, one schema per tenant.</strong> Each tenant gets its own set of tables inside the same database — more separation, more bookkeeping.</li>'
            . '<li><strong>One database per tenant.</strong> Strongest isolation (great for big or regulated customers), but the most infrastructure to manage.</li>'
            . '</ul>'
            . '<p>The critical safety rule: <em>never</em> trust a tenant id sent from the browser. Derive it from the logged-in user\'s token (next topic) and apply it automatically on the server. A small pattern is to put the tenant on the request and force every query through a helper that adds the filter:</p>'
            . '<pre class="code">// After login middleware runs, req.tenantId is set from the user\'s token.\n// A helper guarantees every query is scoped to that tenant.\nfunction tenantScoped(query, req) {\n  return query.where("tenant_id", req.tenantId);\n}\n\napp.get("/api/catalog-items", async (req, res) => {\n  const rows = await tenantScoped(db("catalog_items"), req).select("*");\n  res.json(rows); // only THIS tenant\'s items, always\n});</pre>'
            . '<p>Libraries that help: an ORM like <strong>Prisma</strong> or <strong>Sequelize</strong> can enforce tenant filters in one place; <strong>PostgreSQL Row-Level Security</strong> (if you move to Postgres) pushes the rule into the database itself as a safety net.</p>',
    ],
    [
        'heading' => 'Authentication & authorization — who you are vs what you may do',
        'body' => '<p>Two words that sound alike but mean different things:</p>'
            . '<ul>'
            . '<li><strong>Authentication (authn) = "who are you?"</strong> Proving identity, usually by logging in.</li>'
            . '<li><strong>Authorization (authz) = "what are you allowed to do?"</strong> e.g. a shop\'s <em>manager</em> may edit prices but a <em>clerk</em> may only view them.</li>'
            . '</ul>'
            . '<p>The modern approach uses a <strong>JWT</strong> ("JSON Web Token") — a small, digitally-signed badge the server hands you at login. On every later request your browser shows the badge; the server checks the signature to confirm it is genuine (and un-tampered) without looking anything up in a database. Because nothing is stored on the server, JWTs are perfect for scale-out: any copy of the app can verify the badge.</p>'
            . '<pre class="code">import jwt from "jsonwebtoken";\n\n// Middleware: read the badge, verify it, attach user + tenant to the request.\nfunction requireAuth(req, res, next) {\n  const token = req.headers.authorization?.replace("Bearer ", "");\n  if (!token) return res.status(401).json({ error: "Not logged in" });\n  try {\n    const claims = jwt.verify(token, process.env.JWT_SECRET);\n    req.userId = claims.sub;\n    req.tenantId = claims.tenantId;   // tenant comes from the TOKEN, not the client\n    req.role = claims.role;\n    next();\n  } catch {\n    return res.status(401).json({ error: "Invalid or expired token" });\n  }\n}\n\n// Authorization: only certain roles may pass.\nfunction requireRole(...allowed) {\n  return (req, res, next) =>\n    allowed.includes(req.role) ? next() : res.status(403).json({ error: "Forbidden" });\n}\n\n// Only managers of the current tenant can change prices:\napp.post("/api/catalog-items", requireAuth, requireRole("manager"), saveItem);</pre>'
            . '<p><strong>Do not build login from scratch if you can avoid it.</strong> Proven options: <strong>Passport.js</strong> (flexible auth toolkit), or a hosted identity provider such as <strong>Microsoft Entra ID (External ID)</strong>, <strong>Auth0</strong>, or <strong>Clerk</strong>. They handle passwords, multi-factor auth, "sign in with Google", password resets and security patches for you — using the industry standards <strong>OAuth 2.0</strong> and <strong>OpenID Connect</strong> (the protocols behind those "Sign in with…" buttons).</p>',
    ],
    [
        'heading' => 'Validate every input — Zod (and TypeScript)',
        'body' => '<p>On the public internet, assume every request could be malformed or malicious. <strong>Input validation</strong> means checking incoming data before you trust it — the front line against bad data and many attacks.</p>'
            . '<p><strong>Zod</strong> is a popular library that lets you describe the shape you expect once, then automatically reject anything that does not match, with clear error messages:</p>'
            . '<pre class="code">import { z } from "zod";\n\nconst NewItem = z.object({\n  itemName: z.string().min(1).max(200),\n  cost: z.number().nonnegative(),\n  discount: z.number().min(0).max(100).optional(),\n});\n\napp.post("/api/catalog-items", requireAuth, (req, res) => {\n  const result = NewItem.safeParse(req.body);\n  if (!result.success) {\n    return res.status(400).json({ error: result.error.issues }); // reject bad input\n  }\n  // result.data is now clean and safe to use\n});</pre>'
            . '<p>Alternatives: <strong>Joi</strong> and <strong>express-validator</strong> do the same job. Pairing this with <strong>TypeScript</strong> (which catches type mistakes while you code, before users ever see them) removes a whole category of bugs. Together they make a growing codebase far safer to change — worth adopting early as the team and app expand.</p>',
    ],
    [
        'heading' => 'Hardening security for the open internet',
        'body' => '<p>Swiftcart already ships several defences (Helmet security headers, a CORS allow-list, rate limiting, parameterized queries via Knex). As a public SaaS, layer on more:</p>'
            . '<ul>'
            . '<li><strong>Per-tenant / per-user rate limiting.</strong> Basic <code>express-rate-limit</code> counts per IP; at scale, count per tenant or per API key so one noisy customer cannot slow everyone. Back it with <strong>Redis</strong> so the limit is shared across all app copies.</li>'
            . '<li><strong>Secrets in a vault, not in code.</strong> Keep DB passwords, JWT signing keys and API keys in <strong>Azure Key Vault</strong>, and prefer a <strong>Managed Identity</strong> so the app authenticates to the database with no password at all.</li>'
            . '<li><strong>A Web Application Firewall (WAF).</strong> Put <strong>Azure Front Door</strong> or <strong>Application Gateway</strong> in front to block common attacks (SQL injection probes, bots) before they reach your code.</li>'
            . '<li><strong>Guard against the OWASP Top 10.</strong> This is the industry\'s list of the ten most common web vulnerabilities (injection, broken access control, etc.). Validate inputs, enforce authz on every route, and keep secrets out of logs.</li>'
            . '<li><strong>Patch dependencies.</strong> Run <code>npm audit</code> in your pipeline and use <strong>Dependabot</strong> to auto-open update PRs for vulnerable packages.</li>'
            . '<li><strong>Audit logging.</strong> Record who did what (which user, which tenant, which action) — essential for trust, debugging and compliance.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Performance — cache hot data with Redis',
        'body' => '<p>The database is usually the first thing to get overwhelmed. A <strong>cache</strong> is a small, ultra-fast memory store that keeps copies of data you read often, so you avoid hitting the database every time. <strong>Redis</strong> (available as <strong>Azure Cache for Redis</strong>) is the standard choice.</p>'
            . '<p>The everyday pattern is <strong>cache-aside</strong>: look in the cache first; only touch the database on a "miss", then remember the answer for next time.</p>'
            . '<pre class="code">async function getCatalogItem(id, tenantId) {\n  const key = `item:${tenantId}:${id}`;\n  const cached = await redis.get(key);\n  if (cached) return JSON.parse(cached);          // fast path — no DB\n\n  const item = await db("catalog_items")\n    .where({ item_id: id, tenant_id: tenantId }).first();\n\n  await redis.set(key, JSON.stringify(item), "EX", 300); // remember for 5 min\n  return item;\n}</pre>'
            . '<p>Cache things that are read a lot but change rarely (a product\'s details, a tenant\'s settings). Remember to clear or update the cached copy when the underlying row changes, so users never see stale data. Redis also doubles as the shared store for rate limits, background-job queues and — if you ever need them — user sessions.</p>',
    ],
    [
        'heading' => 'Performance — use every CPU core (clustering, PM2, Fastify)',
        'body' => '<p>Node.js runs your JavaScript on a <strong>single thread</strong> (one worker), so by default it uses just one CPU core even on an 8-core machine. Two easy wins:</p>'
            . '<ul>'
            . '<li><strong>Run one Node process per core.</strong> Node\'s built-in <code>cluster</code> module (or the <strong>PM2</strong> process manager in cluster mode) starts several copies that share the same port, roughly multiplying throughput. PM2 also restarts a copy automatically if it crashes.</li>'
            . '<li><strong>Consider Fastify instead of Express.</strong> <strong>Fastify</strong> is a newer web framework with an Express-like feel but notably higher requests-per-second and built-in schema validation. It is a drop-in-style upgrade worth evaluating when raw throughput matters.</li>'
            . '</ul>'
            . '<pre class="code"># PM2: run as many copies as there are CPU cores, and keep them alive\nnpm install -g pm2\npm2 start src/server.js -i max --name swiftcart\npm2 save</pre>'
            . '<p>Note: once you host on Azure Container Apps or AKS and run <em>multiple instances</em>, the platform already spreads load across machines — so in the cloud you often scale by adding instances rather than clustering inside one box. Clustering shines on a single larger server or VM.</p>',
    ],
    [
        'heading' => 'Scale-out — move slow work to background jobs (BullMQ)',
        'body' => '<p>Some tasks are too slow to make a user wait: importing a 10,000-row CSV, sending emails, generating a report, resizing images. If you do them <em>inside</em> the web request, that request hangs and ties up a worker. The fix is a <strong>background job queue</strong>: the web request quickly drops the task into a <strong>queue</strong> and replies "started"; separate <strong>worker</strong> processes pick tasks off the queue and do the heavy lifting.</p>'
            . '<p><strong>BullMQ</strong> (built on Redis) is the go-to library:</p>'
            . '<pre class="code">// --- In the web app: enqueue the work and return immediately ---\nimport { Queue } from "bullmq";\nconst importQueue = new Queue("catalog-import", { connection: redis });\n\napp.post("/api/catalog-items/bulk", requireAuth, async (req, res) => {\n  await importQueue.add("import", { tenantId: req.tenantId, rows: req.body.items });\n  res.status(202).json({ message: "Import started" }); // user is not kept waiting\n});\n\n// --- In a separate worker process: do the slow job ---\nimport { Worker } from "bullmq";\nnew Worker("catalog-import", async (job) => {\n  await importManyItems(job.data.tenantId, job.data.rows);\n}, { connection: redis });</pre>'
            . '<p>Why this helps scale-out: web instances stay fast and responsive, and you can add or remove worker instances independently based on how much background work is piling up. This cleanly separates "answer users quickly" from "grind through heavy work".</p>',
    ],
    [
        'heading' => 'Scale-out — the app tier and the database tier',
        'body' => '<p><strong>Scale up</strong> = a bigger machine; <strong>scale out</strong> = more machines. SaaS relies on scaling <em>out</em>, and that only works if the app is <strong>stateless</strong> — it keeps nothing important in its own memory, so any instance can handle any request. Swiftcart is already stateless, and JWT auth keeps it that way (no server-side session to pin a user to one instance, so you avoid fragile "sticky sessions").</p>'
            . '<ul>'
            . '<li><strong>Load balancer.</strong> A single public address spreads incoming requests across all instances. Azure App Service, Container Apps and AKS all provide this for you.</li>'
            . '<li><strong>The database becomes the bottleneck.</strong> Ten app copies can overwhelm one database. Mitigate with: sensible <strong>connection pooling</strong> limits (each instance × its pool must stay within the DB\'s max connections), a connection proxy (<strong>ProxySQL</strong> for MySQL, <strong>PgBouncer</strong> for Postgres) to share connections, and <strong>read replicas</strong> — extra read-only copies of the database that absorb heavy "list/search" traffic while writes go to the primary.</li>'
            . '<li><strong>Autoscaling.</strong> Set rules to add instances when CPU or request volume climbs and remove them when it drops — you pay for what you need, when you need it.</li>'
            . '</ul>'
            . '<p>Intuition: making the <em>app</em> scale out is the easy part (add copies); the real engineering is making sure the <em>database</em> and shared resources keep up.</p>',
    ],
    [
        'heading' => 'API design for scale — versioning and a gateway',
        'body' => '<p>As external customers (and maybe their developers) depend on your API, small changes can break them. A few patterns keep growth manageable:</p>'
            . '<ul>'
            . '<li><strong>Version your API.</strong> Serve endpoints under <code>/api/v1/…</code> so you can later ship <code>/api/v2</code> with changes <em>without</em> breaking existing customers on v1.</li>'
            . '<li><strong>Put an API gateway in front.</strong> <strong>Azure API Management</strong> sits between clients and your app and centralises cross-cutting concerns — API keys, per-customer rate limits/quotas, request logging, and even response caching — so your app code stays focused on business logic.</li>'
            . '<li><strong>Keep responses paginated and filterable.</strong> Swiftcart already returns <code>{ rows, total, page, limit }</code> and supports search — exactly the shape that stays fast as data grows into the millions of rows.</li>'
            . '<li><strong>Return consistent errors.</strong> A predictable error shape (status code + machine-readable message) makes life easy for the apps that consume you.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Observability — know what is happening in production',
        'body' => '<p>Once real customers depend on Swiftcart, "it seems fine on my laptop" is not enough. <strong>Observability</strong> means being able to answer "is it healthy, and if not, why?" from the outside. Three pillars:</p>'
            . '<ul>'
            . '<li><strong>Structured logging.</strong> Swap ad-hoc <code>console.log</code> for a fast JSON logger like <strong>pino</strong> (or <strong>winston</strong>). JSON logs are searchable — you can later find "all errors for tenant X in the last hour". Always include the tenant and request id, never secrets.</li>'
            . '<li><strong>Metrics &amp; tracing.</strong> <strong>OpenTelemetry</strong> is the open standard for collecting timings and traces; wired to <strong>Application Insights</strong> it shows request rates, slow endpoints, failures and a request\'s journey across services.</li>'
            . '<li><strong>Health &amp; readiness checks.</strong> Swiftcart\'s <code>/api/health</code> already lets the platform detect and replace a sick instance automatically. Add a "readiness" check (can I reach the database?) so traffic is only sent to instances that are truly ready.</li>'
            . '</ul>'
            . '<p>Add <strong>alerts</strong> (e.g. "error rate above 2% for 5 minutes") so you hear about problems before your customers do.</p>',
    ],
    [
        'heading' => 'Consider a structured framework as the code grows — NestJS',
        'body' => '<p>Express is wonderfully flexible, but a large SaaS with dozens of endpoints, guards, background jobs and teams can become hard to keep tidy. <strong>NestJS</strong> is a framework built <em>on top of</em> Express (or Fastify) that adds structure: it organises code into <strong>modules</strong>, wires pieces together with <strong>dependency injection</strong> (so parts are easy to test and swap), and offers first-class <strong>guards</strong> (for authn/authz), <strong>interceptors</strong> and <strong>pipes</strong> (for validation) — the very patterns described above, standardised.</p>'
            . '<p>It also embraces <strong>TypeScript</strong> throughout. You would not rewrite Swiftcart into NestJS on day one, but it is a natural destination if the codebase and team grow large enough that consistency and testability start to matter more than raw simplicity. Lighter alternatives if you want less ceremony: <strong>Fastify</strong> with a clear folder structure, or <strong>AdonisJS</strong>.</p>',
    ],
    [
        'heading' => 'A phased roadmap — from tool to large-scale SaaS',
        'body' => '<p>You do not need all of this at once. A sensible order that adds capability without over-engineering:</p>'
            . '<ol>'
            . '<li><strong>Phase 1 — Make it multi-tenant &amp; secure.</strong> Add <code>tenant_id</code> everywhere, add login with JWT (or a hosted identity provider), enforce authz on every route, and validate inputs with Zod. Adopt TypeScript.</li>'
            . '<li><strong>Phase 2 — Make it fast.</strong> Add Redis caching for hot reads, gzip/brotli compression, and a CDN/Front Door for static files. Add structured logging and Application Insights.</li>'
            . '<li><strong>Phase 3 — Make it scale out.</strong> Containerise, run multiple instances behind a load balancer with autoscaling, add read replicas and a connection proxy, and move slow work to BullMQ background workers.</li>'
            . '<li><strong>Phase 4 — Make it robust &amp; manageable.</strong> Version the API behind Azure API Management, add per-tenant rate limits and quotas, a WAF, alerts, and automated dependency updates. Introduce NestJS if the codebase demands more structure.</li>'
            . '</ol>'
            . '<p>Each phase stands on its own and leaves Swiftcart working. That is the beauty of the current design: because it is already stateless, config-driven and cleanly layered, you can evolve it into a large-scale SaaS step by step — never a big scary rewrite.</p>',
    ],
];

$architectureScenarios = [
    [
        'heading' => 'How to read these architectures',
        'body' => '<p>Below are four <strong>reference architectures</strong> — ready-made blueprints for running Swiftcart as a SaaS on Azure, from a shoestring first launch to a global, always-on service. Each is drawn as a simple boxes-and-arrows diagram, followed by a plain-English walkthrough, when to pick it, and roughly what it costs in effort.</p>'
            . '<p>How to read the diagrams: a <strong>box</strong> is an Azure service (or your app), an <strong>arrow</strong> means "sends a request to" or "reads from", and the flow generally runs top (the user\'s browser) to bottom (the database). Later scenarios simply add boxes to the earlier ones — you grow into them.</p>'
            . '<p>One thing stays constant in every scenario: <strong>the app itself does not change</strong>. Because Swiftcart is stateless and reads all its settings from environment variables, the <em>same</em> code drops into each architecture — you are only changing what surrounds it.</p>',
    ],
    [
        'heading' => 'Scenario 1 — Starter SaaS (launch on a budget)',
        'body' => '<p><em>Goal:</em> get a paying-customer-ready SaaS live quickly and cheaply, with good security hygiene. Best for your first handful of tenants and a small team.</p>'
            . '<div class="arch-diagram" style="margin:1.1rem 0;overflow-x:auto">'
                . '<svg viewBox="0 0 650 450" role="img" aria-label="Scenario 1 starter architecture" style="width:100%;height:auto;max-width:660px;display:block;margin:0 auto;background:#fbfdff;border:1px solid #e2e8f0;border-radius:10px">'
                . '<defs><marker id="ar1" markerWidth="10" markerHeight="10" refX="7.5" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L7.5,3 L0,6 z" fill="#5b7086"/></marker></defs>'
                . '<rect x="225" y="16" width="200" height="42" rx="8" fill="#f2f2f2" stroke="#888" stroke-width="1.5"/>'
                . '<text x="325" y="42" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Shop staff (browser)</text>'
                . '<line x1="325" y1="58" x2="325" y2="98" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar1)"/>'
                . '<text x="333" y="82" font-size="11" fill="#5a6b7d">HTTPS</text>'
                . '<rect x="215" y="100" width="220" height="64" rx="8" fill="#dbeafe" stroke="#1e5aa8" stroke-width="1.5"/>'
                . '<text x="325" y="126" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Azure App Service (Linux)</text>'
                . '<text x="325" y="146" text-anchor="middle" font-size="11" fill="#4a6076">Node/Express + React build</text>'
                . '<text x="452" y="127" font-size="11" fill="#5a6b7d">1 instance,</text>'
                . '<text x="452" y="141" font-size="11" fill="#5a6b7d">can scale up</text>'
                . '<line x1="280" y1="164" x2="150" y2="248" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar1)"/>'
                . '<text x="110" y="212" font-size="11" fill="#5a6b7d">reads secrets</text>'
                . '<line x1="380" y1="164" x2="505" y2="248" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar1)"/>'
                . '<text x="432" y="212" font-size="11" fill="#5a6b7d">SQL over TLS</text>'
                . '<line x1="330" y1="164" x2="330" y2="376" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar1)"/>'
                . '<text x="338" y="330" font-size="11" fill="#5a6b7d">telemetry</text>'
                . '<rect x="40" y="250" width="210" height="66" rx="8" fill="#fdeceb" stroke="#c0392b" stroke-width="1.5"/>'
                . '<text x="145" y="280" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Key Vault</text>'
                . '<text x="145" y="300" text-anchor="middle" font-size="11" fill="#4a6076">DB password, JWT key</text>'
                . '<rect x="400" y="250" width="210" height="66" rx="8" fill="#e7f6ee" stroke="#2e8b57" stroke-width="1.5"/>'
                . '<text x="505" y="280" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Azure Database for MySQL</text>'
                . '<text x="505" y="300" text-anchor="middle" font-size="11" fill="#4a6076">Flexible Server (managed)</text>'
                . '<rect x="225" y="378" width="210" height="52" rx="8" fill="#eaf2fb" stroke="#2f6fb0" stroke-width="1.5"/>'
                . '<text x="330" y="409" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Application Insights</text>'
                . '</svg></div>'
            . '<p><strong>In plain English:</strong> a customer opens Swiftcart in the browser over HTTPS. One <strong>App Service</strong> instance runs the Node app, which also serves the React files (same origin, no CORS headaches). It fetches the database password and JWT signing key from <strong>Key Vault</strong> at startup (nothing secret is baked into the code), then talks to the managed <strong>MySQL</strong> database over an encrypted connection. <strong>Application Insights</strong> quietly collects timings and errors so you can see how it is doing.</p>'
            . '<ul>'
            . '<li><strong>Pick it when:</strong> you are launching, budget is tight, and traffic is modest.</li>'
            . '<li><strong>Effort:</strong> lowest — no containers, no Kubernetes, mostly point-and-click plus one deploy pipeline.</li>'
            . '<li><strong>Grows by:</strong> turning on App Service <em>autoscale</em> (more instances) and adding the pieces in Scenario 2 when traffic climbs.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Scenario 2 — Growing SaaS (fast, resilient, multi-tenant)',
        'body' => '<p><em>Goal:</em> dozens-to-hundreds of tenants, snappy performance worldwide, and no single point that falls over. This is the sweet spot for a healthy, growing product.</p>'
            . '<div class="arch-diagram" style="margin:1.1rem 0;overflow-x:auto">'
                . '<svg viewBox="0 0 720 420" role="img" aria-label="Scenario 2 growing architecture" style="width:100%;height:auto;max-width:720px;display:block;margin:0 auto;background:#fbfdff;border:1px solid #e2e8f0;border-radius:10px">'
                . '<defs><marker id="ar2" markerWidth="10" markerHeight="10" refX="7.5" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L7.5,3 L0,6 z" fill="#5b7086"/></marker></defs>'
                . '<rect x="260" y="14" width="200" height="40" rx="8" fill="#f2f2f2" stroke="#888" stroke-width="1.5"/>'
                . '<text x="360" y="39" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Users (many tenants)</text>'
                . '<line x1="360" y1="54" x2="360" y2="88" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar2)"/>'
                . '<text x="368" y="76" font-size="11" fill="#5a6b7d">HTTPS</text>'
                . '<rect x="200" y="90" width="320" height="56" rx="8" fill="#eaf2fb" stroke="#2f6fb0" stroke-width="1.5"/>'
                . '<text x="360" y="116" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Azure Front Door + WAF (CDN)</text>'
                . '<text x="360" y="134" text-anchor="middle" font-size="11" fill="#4a6076">caches static files at the edge, blocks common attacks</text>'
                . '<line x1="360" y1="146" x2="360" y2="178" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar2)"/>'
                . '<rect x="200" y="180" width="320" height="60" rx="8" fill="#dbeafe" stroke="#1e5aa8" stroke-width="1.5"/>'
                . '<text x="360" y="206" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">App Service / Container Apps</text>'
                . '<text x="360" y="225" text-anchor="middle" font-size="11" fill="#4a6076">2..N copies, autoscaling, behind a load balancer</text>'
                . '<line x1="250" y1="240" x2="105" y2="298" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar2)"/>'
                . '<text x="150" y="274" font-size="11" fill="#5a6b7d">cache</text>'
                . '<line x1="320" y1="240" x2="268" y2="298" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar2)"/>'
                . '<text x="286" y="274" font-size="11" fill="#5a6b7d">auth</text>'
                . '<line x1="405" y1="240" x2="450" y2="298" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar2)"/>'
                . '<text x="430" y="274" font-size="11" fill="#5a6b7d">SQL</text>'
                . '<line x1="470" y1="240" x2="625" y2="298" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar2)"/>'
                . '<text x="560" y="274" font-size="11" fill="#5a6b7d">jobs</text>'
                . '<rect x="30" y="300" width="150" height="64" rx="8" fill="#fff4e6" stroke="#d98324" stroke-width="1.5"/>'
                . '<text x="105" y="328" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Redis</text>'
                . '<text x="105" y="346" text-anchor="middle" font-size="11" fill="#4a6076">cache</text>'
                . '<rect x="192" y="300" width="152" height="64" rx="8" fill="#fdeceb" stroke="#c0392b" stroke-width="1.5"/>'
                . '<text x="268" y="328" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Entra ID / Auth0</text>'
                . '<text x="268" y="346" text-anchor="middle" font-size="11" fill="#4a6076">identity provider</text>'
                . '<rect x="360" y="300" width="180" height="64" rx="8" fill="#e7f6ee" stroke="#2e8b57" stroke-width="1.5"/>'
                . '<text x="450" y="326" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">MySQL primary</text>'
                . '<text x="450" y="344" text-anchor="middle" font-size="11" fill="#4a6076">+ read replica</text>'
                . '<rect x="560" y="300" width="130" height="64" rx="8" fill="#e6eefb" stroke="#3a6ea5" stroke-width="1.5"/>'
                . '<text x="625" y="326" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Worker(s)</text>'
                . '<text x="625" y="344" text-anchor="middle" font-size="11" fill="#4a6076">(BullMQ)</text>'
                . '<path d="M625,364 L625,398 L105,398 L105,366" fill="none" stroke="#8a97a8" stroke-width="1.4" stroke-dasharray="5 4" marker-end="url(#ar2)"/>'
                . '<text x="365" y="394" text-anchor="middle" font-size="11" fill="#7a8798">workers pull jobs via Redis</text>'
                . '</svg></div>'
            . '<p><strong>In plain English:</strong> all traffic first hits <strong>Azure Front Door</strong>, which caches the static React files close to each user (fast page loads globally) and runs a <strong>WAF</strong> that blocks common attacks before they reach your code. It forwards live requests to <strong>several copies</strong> of the app (App Service or Container Apps) that autoscale up and down with demand — if one copy dies, the others carry on. Each copy uses <strong>Redis</strong> to cache hot data (so the database is not hammered), verifies logins against a hosted identity provider (<strong>Entra ID</strong> or Auth0), and reads from a <strong>MySQL read replica</strong> for heavy list/search traffic while writes go to the primary. Slow tasks (bulk CSV imports, emails) are dropped onto a <strong>Redis-backed BullMQ queue</strong> and handled by separate <strong>worker</strong> processes, so the web copies stay responsive.</p>'
            . '<ul>'
            . '<li><strong>Pick it when:</strong> real customers depend on you, traffic is spiky, and slow imports/reports must not block the UI.</li>'
            . '<li><strong>Effort:</strong> medium — containerise (optional on App Service), add Redis, a replica, workers and Front Door.</li>'
            . '<li><strong>Why it scales:</strong> the app is stateless, so "more load" simply means "more copies"; Redis and the read replica keep the database from becoming the bottleneck.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Scenario 3 — Large-scale, multi-region SaaS (always on)',
        'body' => '<p><em>Goal:</em> thousands of tenants, strict uptime promises (SLAs), and users spread across continents. This is the enterprise-grade blueprint.</p>'
            . '<div class="arch-diagram" style="margin:1.1rem 0;overflow-x:auto">'
                . '<svg viewBox="0 0 760 500" role="img" aria-label="Scenario 3 multi-region architecture" style="width:100%;height:auto;max-width:760px;display:block;margin:0 auto;background:#fbfdff;border:1px solid #e2e8f0;border-radius:10px">'
                . '<defs><marker id="ar3" markerWidth="10" markerHeight="10" refX="7.5" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L7.5,3 L0,6 z" fill="#5b7086"/></marker>'
                . '<marker id="ar3s" markerWidth="10" markerHeight="10" refX="2" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M7.5,0 L0,3 L7.5,6 z" fill="#8a97a8"/></marker></defs>'
                . '<rect x="290" y="12" width="180" height="40" rx="8" fill="#f2f2f2" stroke="#888" stroke-width="1.5"/>'
                . '<text x="380" y="37" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Global users</text>'
                . '<line x1="380" y1="52" x2="380" y2="84" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<rect x="150" y="86" width="460" height="60" rx="8" fill="#eaf2fb" stroke="#2f6fb0" stroke-width="1.5"/>'
                . '<text x="380" y="110" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Azure Front Door (WAF + CDN + routing)</text>'
                . '<text x="380" y="130" text-anchor="middle" font-size="11" fill="#4a6076">sends users to the nearest healthy region; fails over automatically</text>'
                . '<text x="190" y="176" text-anchor="middle" font-size="12" font-weight="600" fill="#334">Region A (West EU)</text>'
                . '<text x="570" y="176" text-anchor="middle" font-size="12" font-weight="600" fill="#334">Region B (East US)</text>'
                . '<line x1="300" y1="146" x2="195" y2="194" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<line x1="460" y1="146" x2="565" y2="194" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<rect x="90" y="196" width="200" height="52" rx="8" fill="#f3e8fb" stroke="#7d3cad" stroke-width="1.5"/>'
                . '<text x="190" y="218" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">API Management</text>'
                . '<text x="190" y="236" text-anchor="middle" font-size="11" fill="#4a6076">keys, quotas, versioning</text>'
                . '<rect x="470" y="196" width="200" height="52" rx="8" fill="#f3e8fb" stroke="#7d3cad" stroke-width="1.5"/>'
                . '<text x="570" y="218" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">API Management</text>'
                . '<text x="570" y="236" text-anchor="middle" font-size="11" fill="#4a6076">keys, quotas, versioning</text>'
                . '<line x1="190" y1="248" x2="190" y2="284" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<line x1="570" y1="248" x2="570" y2="284" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<rect x="90" y="286" width="200" height="58" rx="8" fill="#dbeafe" stroke="#1e5aa8" stroke-width="1.5"/>'
                . '<text x="190" y="310" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">AKS or ACA</text>'
                . '<text x="190" y="328" text-anchor="middle" font-size="11" fill="#4a6076">app + BullMQ workers</text>'
                . '<rect x="470" y="286" width="200" height="58" rx="8" fill="#dbeafe" stroke="#1e5aa8" stroke-width="1.5"/>'
                . '<text x="570" y="310" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">AKS or ACA</text>'
                . '<text x="570" y="328" text-anchor="middle" font-size="11" fill="#4a6076">app + BullMQ workers</text>'
                . '<line x1="150" y1="344" x2="125" y2="400" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<line x1="235" y1="344" x2="290" y2="400" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<line x1="530" y1="344" x2="505" y2="400" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<line x1="615" y1="344" x2="670" y2="400" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar3)"/>'
                . '<rect x="70" y="402" width="110" height="52" rx="8" fill="#fff4e6" stroke="#d98324" stroke-width="1.5"/>'
                . '<text x="125" y="433" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Redis</text>'
                . '<rect x="200" y="402" width="185" height="64" rx="8" fill="#e7f6ee" stroke="#2e8b57" stroke-width="1.5"/>'
                . '<text x="292" y="428" text-anchor="middle" font-size="12" font-weight="600" fill="#12314e">MySQL primary + replicas</text>'
                . '<text x="292" y="446" text-anchor="middle" font-size="11" fill="#4a6076">(Private Endpoint)</text>'
                . '<rect x="450" y="402" width="110" height="52" rx="8" fill="#fff4e6" stroke="#d98324" stroke-width="1.5"/>'
                . '<text x="505" y="433" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Redis</text>'
                . '<rect x="580" y="402" width="170" height="64" rx="8" fill="#e7f6ee" stroke="#2e8b57" stroke-width="1.5"/>'
                . '<text x="665" y="428" text-anchor="middle" font-size="12" font-weight="600" fill="#12314e">MySQL geo-replica</text>'
                . '<text x="665" y="446" text-anchor="middle" font-size="11" fill="#4a6076">(Private Endpoint)</text>'
                . '<path d="M292,466 L292,486 L665,486 L665,466" fill="none" stroke="#8a97a8" stroke-width="1.4" stroke-dasharray="5 4" marker-end="url(#ar3)" marker-start="url(#ar3s)"/>'
                . '<text x="478" y="481" text-anchor="middle" font-size="11" fill="#7a8798">geo-replication</text>'
                . '</svg></div>'
            . '<p><strong>In plain English:</strong> the app runs in <strong>two (or more) Azure regions</strong> at once. <strong>Front Door</strong> sends each user to the closest healthy region and, if a whole region has trouble, routes everyone to another one automatically. In each region, requests pass through <strong>API Management</strong> — a gateway that handles API keys, per-customer rate limits and quotas, and API versioning — before reaching a fleet of app copies on <strong>AKS or Container Apps</strong> (with their <strong>BullMQ workers</strong> alongside). Each region has its own <strong>Redis</strong> cache and a <strong>MySQL</strong> database reached over a <strong>Private Endpoint</strong> (the database is never exposed to the public internet), with replicas absorbing read traffic and geo-replication keeping regions in sync.</p>'
            . '<ul>'
            . '<li><strong>Pick it when:</strong> you have signed uptime SLAs, global customers, or regulatory needs for isolation and data residency.</li>'
            . '<li><strong>Effort:</strong> highest — usually a dedicated platform team; genuine overkill until scale demands it.</li>'
            . '<li><strong>Trade-off:</strong> maximum resilience and reach, but far more moving parts to build, secure and pay for. Most products only reach here after Scenario 2 is bursting.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Scenario 4 — Event-driven add-ons (analytics, notifications, integrations)',
        'body' => '<p><em>Goal:</em> add features <em>around</em> Swiftcart — usage analytics, customer email/webhook notifications, or feeding data to other systems — without slowing the core app. This layers onto any of the scenarios above.</p>'
            . '<div class="arch-diagram" style="margin:1.1rem 0;overflow-x:auto">'
                . '<svg viewBox="0 0 720 350" role="img" aria-label="Scenario 4 event-driven architecture" style="width:100%;height:auto;max-width:720px;display:block;margin:0 auto;background:#fbfdff;border:1px solid #e2e8f0;border-radius:10px">'
                . '<defs><marker id="ar4" markerWidth="10" markerHeight="10" refX="7.5" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L7.5,3 L0,6 z" fill="#5b7086"/></marker></defs>'
                . '<rect x="250" y="14" width="220" height="48" rx="8" fill="#dbeafe" stroke="#1e5aa8" stroke-width="1.5"/>'
                . '<text x="360" y="38" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Swiftcart app</text>'
                . '<text x="360" y="54" text-anchor="middle" font-size="11" fill="#4a6076">(any scenario above)</text>'
                . '<line x1="360" y1="62" x2="360" y2="116" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar4)"/>'
                . '<text x="370" y="88" font-size="11" fill="#5a6b7d">publishes an event:</text>'
                . '<text x="370" y="103" font-size="11" fill="#5a6b7d">order.created, stock.low, ...</text>'
                . '<rect x="180" y="118" width="360" height="58" rx="8" fill="#eaf2fb" stroke="#2f6fb0" stroke-width="1.5"/>'
                . '<text x="360" y="142" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Azure Service Bus (queue) or Event Grid (pub/sub)</text>'
                . '<text x="360" y="161" text-anchor="middle" font-size="11" fill="#4a6076">a reliable mailbox that other programs subscribe to</text>'
                . '<line x1="300" y1="176" x2="140" y2="246" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar4)"/>'
                . '<line x1="360" y1="176" x2="360" y2="246" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar4)"/>'
                . '<line x1="420" y1="176" x2="580" y2="246" stroke="#5b7086" stroke-width="1.6" marker-end="url(#ar4)"/>'
                . '<rect x="50" y="248" width="180" height="78" rx="8" fill="#e6eefb" stroke="#3a6ea5" stroke-width="1.5"/>'
                . '<text x="140" y="282" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Azure Functions</text>'
                . '<text x="140" y="300" text-anchor="middle" font-size="11" fill="#4a6076">small on-demand jobs</text>'
                . '<rect x="270" y="248" width="180" height="78" rx="8" fill="#fdeceb" stroke="#c0392b" stroke-width="1.5"/>'
                . '<text x="360" y="278" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Notification sender</text>'
                . '<text x="360" y="296" text-anchor="middle" font-size="11" fill="#4a6076">email / SMS /</text>'
                . '<text x="360" y="311" text-anchor="middle" font-size="11" fill="#4a6076">webhook</text>'
                . '<rect x="490" y="248" width="180" height="78" rx="8" fill="#e7f6ee" stroke="#2e8b57" stroke-width="1.5"/>'
                . '<text x="580" y="278" text-anchor="middle" font-size="13" font-weight="600" fill="#12314e">Analytics sink</text>'
                . '<text x="580" y="296" text-anchor="middle" font-size="11" fill="#4a6076">store events</text>'
                . '<text x="580" y="311" text-anchor="middle" font-size="11" fill="#4a6076">for dashboards</text>'
                . '</svg></div>'
            . '<p><strong>In plain English:</strong> instead of doing extra work inside the web request, the app just <strong>announces that something happened</strong> — "an order was created", "stock ran low" — by dropping a small message onto <strong>Azure Service Bus</strong> (a reliable queue) or <strong>Event Grid</strong> (a publish/subscribe hub). Other little programs <em>subscribe</em> to those announcements and react independently: <strong>Azure Functions</strong> run tiny bits of code on demand, a notification service emails the customer or calls their webhook, and an analytics sink records the event for dashboards. The core app never waits for any of this, and you can add new reactions later without touching Swiftcart\'s code.</p>'
            . '<ul>'
            . '<li><strong>Pick it when:</strong> you need notifications, reporting, or to integrate with other apps, and want those features decoupled from the main request path.</li>'
            . '<li><strong>Why it helps:</strong> the checkout stays fast (it only posts a message), and each add-on can fail, retry or scale on its own without affecting the shop.</li>'
            . '<li><strong>Note:</strong> this is the same "offload slow work" idea as BullMQ, but for cross-service events; Azure Functions also mean you pay only when an event actually fires.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Which architecture should Swiftcart use?',
        'body' => '<table class="params">'
            . '<thead><tr><th>Scenario</th><th>Good for</th><th>Key Azure pieces</th><th>Effort / cost</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>1 · Starter</td><td>First tenants, MVP, tight budget</td><td>App Service, MySQL Flexible, Key Vault, App Insights</td><td>Lowest</td></tr>'
            . '<tr><td>2 · Growing</td><td>Dozens–hundreds of tenants, spiky traffic</td><td>Front Door + WAF, App Service/ACA (autoscale), Redis, read replica, BullMQ workers, Entra ID</td><td>Medium</td></tr>'
            . '<tr><td>3 · Large-scale</td><td>Thousands of tenants, SLAs, global, regulated</td><td>Multi-region Front Door, API Management, AKS/ACA, Redis, geo-replicated MySQL, Private Endpoints</td><td>Highest</td></tr>'
            . '<tr><td>4 · Event-driven</td><td>Analytics, notifications, integrations</td><td>Service Bus / Event Grid, Azure Functions (adds onto 1–3)</td><td>Incremental</td></tr>'
            . '</tbody></table>'
            . '<p><strong>Recommendation:</strong> start at <strong>Scenario 1</strong> to get live, move to <strong>Scenario 2</strong> as soon as you have real customers and performance matters (this is the best home for most SaaS products), and only adopt <strong>Scenario 3</strong> when uptime promises or global reach truly demand it. Add <strong>Scenario 4</strong> whenever you need notifications, analytics or integrations — it clips onto whichever tier you are on. Every step reuses the same unchanged Swiftcart app, so you are always adding capability, never rewriting.</p>',
    ],
];

$appServiceDeploy = [
    [
        'heading' => 'The big picture — what you will build',
        'body' => '<p>This is a complete, hands-on guide to putting Swiftcart on <strong>Azure App Service</strong> and keeping it up to date automatically with <strong>GitHub Actions</strong>. The shape of it:</p>'
            . '<ol>'
            . '<li><strong>One-off setup</strong> (done once): create the App Service, store configuration and secrets, and connect GitHub to Azure securely.</li>'
            . '<li><strong>Ongoing automation</strong> (every push): a GitHub Actions workflow builds the React app, bundles it with the Node backend, and deploys it.</li>'
            . '<li><strong>Operate it well</strong>: pick the right plan/SKU, then add monitoring, backups and best practices for security, performance, reliability and scalability.</li>'
            . '<li><strong>Grow it</strong>: optionally extend into an event-driven architecture for notifications, analytics and integrations.</li>'
            . '</ol>'
            . '<p>Throughout, remember Swiftcart is <em>one</em> Node app that also serves the React build and reads every setting from <code>process.env</code> — that is what makes this deployment simple.</p>',
    ],
    [
        'heading' => 'Three services on three ports — can it be one App Service?',
        'body' => '<p>Good question — and the answer has two halves, because App Service has two rules worth knowing.</p>'
            . '<p><strong>First, what the three "services" really are in production:</strong></p>'
            . '<ul>'
            . '<li><strong>The Vite dev server (port 5173) is development-only.</strong> It does not run in production. <code>npm run build</code> compiles React into plain static files, which the Express server then serves — so this "service" disappears entirely once built.</li>'
            . '<li><strong>The Express backend (port 4000) is the real server.</strong> It already serves the compiled React app <em>and</em> the <code>/api</code> routes from a <strong>single process on a single port</strong>.</li>'
            . '<li><strong>The PHP docs (port 8000) are a genuinely separate program</strong> — a PHP app, not Node.</li>'
            . '</ul>'
            . '<p><strong>Rule 1 — one app is exposed on 80/443 only.</strong> App Service puts its own front end on ports 80/443 and forwards traffic to whatever <em>single</em> internal port your app listens on. You tell it that port through the <code>PORT</code> environment variable — and Swiftcart already reads <code>process.env.PORT</code> (defaulting to 4000 locally). So your internal port 4000 is perfectly fine; you never expose 4000/5173/8000 publicly, and you do not need to. What you <em>cannot</em> do is publish several ports from one app.</p>'
            . '<p><strong>Rule 2 — one runtime stack per app.</strong> An App Service app runs <em>either</em> Node <em>or</em> PHP, not both; Node cannot execute PHP.</p>'
            . '<p><strong>So can the monolith be a single deployment?</strong> The Node half — <strong>Express + the React build — absolutely yes, as one App Service on one port.</strong> The many dev ports collapse into that one production process. The <em>only</em> piece that does not fit inside the Node app is the PHP documentation site — covered next.</p>',
    ],
    [
        'heading' => 'Handling the PHP documentation service',
        'body' => '<p>Because App Service runs one runtime per app, the PHP docs cannot execute inside the Node app. (In fact <code>api-docs.php</code> is copied into <code>backend/public</code>, so a Node App Service <em>would</em> serve it — but as <strong>raw PHP text</strong>, since Node cannot run PHP.) You have three clean options:</p>'
            . '<ul>'
            . '<li><strong>(a) A separate small PHP App Service — recommended and simplest.</strong> Deploy the docs as their own tiny web app (say <code>swiftcart-docs</code>) on the PHP stack. It is cheap, fully decoupled, and keeps the intentional "this documentation site is itself a PHP app" demonstration. You end up with two one-line deployments instead of one.</li>'
            . '<li><strong>(b) Fold the docs into the Node app.</strong> Drop PHP and serve the documentation from Express instead — either pre-rendered to static HTML at build time, or as an Express route. Then <em>everything</em> is a single Node deployment, at the cost of losing the PHP demo.</li>'
            . '<li><strong>(c) One custom container running all of it.</strong> Build a Docker image that runs Node <em>and</em> PHP behind a small reverse proxy (nginx) on one port, and deploy it to App Service for Containers or Azure Container Apps. This is a genuine single deployment that includes PHP, but it adds moving parts and is usually overkill for a docs page.</li>'
            . '</ul>'
            . '<p><strong>Make two apps feel like one.</strong> If you pick option (a), put both App Services behind <strong>Azure Front Door</strong> with path-based routing — for example <code>/</code> → the Node app and <code>/docs</code> → the PHP app — so users see a single domain. The app already reads its docs link from a configurable setting (<code>VITE_DOCS_URL</code>), so you just point it at wherever the docs live.</p>'
            . '<p><strong>Bottom line:</strong> deploy Swiftcart\'s Node monolith (Express + React) as one App Service — that part is genuinely a single, simple deployment. Host the PHP docs alongside it as a small second app (option a), and optionally unify them under one domain with Front Door.</p>',
    ],
    [
        'heading' => 'One-off setup vs ongoing automation',
        'body' => '<p>A common question: "is deployment a one-time thing or continuous?" Both — they are different jobs:</p>'
            . '<ul>'
            . '<li><strong>One-off (set up once):</strong> creating the resource group, App Service plan and web app; configuring App Settings and Key Vault; and wiring GitHub to Azure. You only redo these if the infrastructure changes.</li>'
            . '<li><strong>Ongoing (every code change):</strong> the GitHub Actions workflow. Each push to <code>main</code> automatically builds and redeploys the app — this is <strong>continuous deployment (CD)</strong>. You never touch Azure by hand for routine releases.</li>'
            . '</ul>'
            . '<p>Configuration sits in between: you set it once, but can update App Settings any time <em>without</em> redeploying code — App Service just restarts the app with the new values.</p>',
    ],
    [
        'heading' => 'Step 1 — Create the App Service (one-off)',
        'body' => '<p>Create the hosting resources once with the Azure CLI (or the portal). This makes a Linux App Service running Node 20.</p>'
            . '<pre class="code"># a resource group to hold everything\naz group create --name swiftcart-rg --location eastus\n\n# an App Service plan (the VM behind your app) — start on S1\naz appservice plan create \\\n  --name swiftcart-plan --resource-group swiftcart-rg \\\n  --sku S1 --is-linux\n\n# the web app itself, on Node 20\naz webapp create \\\n  --name swiftcart-api --resource-group swiftcart-rg \\\n  --plan swiftcart-plan --runtime "NODE:20-lts"</pre>'
            . '<p>The web app gets a public HTTPS URL like <code>https://swiftcart-api.azurewebsites.net</code>. Turn on two settings now:</p>'
            . '<pre class="code"># keep the app warm (no cold starts) — Basic tier or higher\naz webapp config set -g swiftcart-rg -n swiftcart-api --always-on true\n\n# how App Service starts the app\naz webapp config set -g swiftcart-rg -n swiftcart-api \\\n  --startup-file "node src/server.js"</pre>'
            . '<p>App Service tells the app which port to listen on via the <code>PORT</code> environment variable — Swiftcart already reads <code>process.env.PORT</code>, so no code change is needed.</p>',
    ],
    [
        'heading' => 'Step 2 — Deploying configuration without committing .env',
        'body' => '<p>Your <code>backend/.env</code> is deliberately <strong>git-ignored</strong> and never committed — so how does production get its settings? Through <strong>App Settings</strong>, App Service\'s equivalent of <code>.env</code>. App Service injects each App Setting into the app as an <strong>environment variable</strong> at runtime, exactly where <code>process.env</code> looks.</p>'
            . '<pre class="code">az webapp config appsettings set \\\n  --resource-group swiftcart-rg --name swiftcart-api \\\n  --settings \\\n    NODE_ENV=production \\\n    DB_HOST=vt-mysql-flex-01.mysql.database.azure.com \\\n    DB_USER=vtadmin \\\n    DB_NAME=sales_db \\\n    DB_SSL=true \\\n    FRONTEND_ORIGIN=https://swiftcart-api.azurewebsites.net</pre>'
            . '<ul>'
            . '<li>These values live in Azure, not in Git — nothing sensitive enters the repository.</li>'
            . '<li>The app reads them unchanged (<code>process.env.DB_HOST</code>, etc.), so local <code>.env</code> and production App Settings stay interchangeable.</li>'
            . '<li>Change a setting in the portal or CLI at any time; App Service restarts the app to pick it up — no code redeploy required.</li>'
            . '<li><strong>Connection strings</strong> (a sibling of App Settings) work the same way and are masked in logs.</li>'
            . '</ul>'
            . '<p>The database <em>password</em> is the one setting you should not paste in plain text — the next step moves it into Key Vault.</p>',
    ],
    [
        'heading' => 'Step 3 — Secrets in Key Vault + a Managed Identity',
        'body' => '<p>For anything sensitive (the DB password, a JWT signing key, API keys), store it in <strong>Azure Key Vault</strong> and let App Service fetch it using a <strong>Managed Identity</strong> — an automatic, password-less identity for your app.</p>'
            . '<pre class="code"># 1) create a vault and store the DB password\naz keyvault create -g swiftcart-rg -n swiftcart-kv\naz keyvault secret set --vault-name swiftcart-kv \\\n  --name db-password --value "YOUR_DB_PASSWORD"\n\n# 2) give the web app a managed identity\naz webapp identity assign -g swiftcart-rg -n swiftcart-api\n\n# 3) let that identity read secrets from the vault\naz keyvault set-policy -n swiftcart-kv \\\n  --object-id <app-identity-objectId> --secret-permissions get\n\n# 4) reference the secret from an App Setting (note the special syntax)\naz webapp config appsettings set -g swiftcart-rg -n swiftcart-api \\\n  --settings DB_PASSWORD="@Microsoft.KeyVault(SecretUri=https://swiftcart-kv.vault.azure.net/secrets/db-password/)"</pre>'
            . '<p>At runtime the app still just reads <code>process.env.DB_PASSWORD</code> — App Service resolves the Key Vault reference for it. Even better, Azure Database for MySQL supports <strong>Microsoft Entra (passwordless) authentication</strong>, letting the managed identity connect with <em>no</em> stored password at all.</p>',
    ],
    [
        'heading' => 'Step 4 — Connect GitHub to Azure securely (OIDC, one-off)',
        'body' => '<p>GitHub needs permission to deploy to your Azure app. The modern, most secure way is <strong>OpenID Connect (OIDC)</strong>: GitHub proves its identity to Azure and receives a short-lived token, so <em>no</em> password or publish profile is stored in the repo. Set this up once:</p>'
            . '<pre class="code"># create an Entra app registration for the pipeline\naz ad app create --display-name swiftcart-github\n# note its appId, then create a service principal for it\naz ad sp create --id <appId>\n\n# let it deploy into the resource group\naz role assignment create --assignee <appId> --role contributor \\\n  --scope /subscriptions/<subId>/resourceGroups/swiftcart-rg\n\n# trust GitHub Actions from your repo main branch (federated credential)\naz ad app federated-credential create --id <appId> --parameters \'{\n  "name": "swiftcart-main",\n  "issuer": "https://token.actions.githubusercontent.com",\n  "subject": "repo:my-org/swiftcart:ref:refs/heads/main",\n  "audiences": ["api://AzureADTokenExchange"]\n}\'</pre>'
            . '<p>Then add three <strong>repository secrets</strong> in GitHub (Settings → Secrets and variables → Actions): <code>AZURE_CLIENT_ID</code> (the appId), <code>AZURE_TENANT_ID</code> and <code>AZURE_SUBSCRIPTION_ID</code>. These are identifiers, not passwords. <em>Simpler alternative:</em> download the app\'s <strong>publish profile</strong> and store it as one secret — quicker, but a long-lived credential, so OIDC is preferred.</p>',
    ],
    [
        'heading' => 'Step 5 — The GitHub Actions workflow (ongoing)',
        'body' => '<p>Add this file at <code>.github/workflows/deploy.yml</code>. On every push to <code>main</code> it builds the React app, copies it into the backend, and deploys — the same steps you run locally with <code>npm run build:deploy</code>, but automated.</p>'
            . '<pre class="code">name: Deploy Swiftcart to App Service\n\non:\n  push:\n    branches: [ main ]\n\npermissions:\n  id-token: write      # required for OIDC login\n  contents: read\n\nenv:\n  AZURE_WEBAPP_NAME: swiftcart-api      # the App Service you provisioned in Step 1\n\njobs:\n  build-and-deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - uses: actions/checkout@v4\n      - uses: actions/setup-node@v4\n        with:\n          node-version: 20\n\n      # 1) build the React frontend\n      - run: npm ci\n        working-directory: frontend\n      - run: npm run build\n        working-directory: frontend\n\n      # 2) install backend deps and copy the build into backend/public\n      - run: npm ci\n        working-directory: backend\n      - run: node scripts/copy-frontend.js\n\n      # 3) log in to Azure with OIDC (picks the subscription the app lives in)\n      - uses: azure/login@v2\n        with:\n          client-id: ${{ secrets.AZURE_CLIENT_ID }}\n          tenant-id: ${{ secrets.AZURE_TENANT_ID }}\n          subscription-id: ${{ secrets.AZURE_SUBSCRIPTION_ID }}\n\n      # 4) deploy to the App Service provisioned in Step 1 (matched by name)\n      - uses: azure/webapps-deploy@v3\n        with:\n          app-name: ${{ env.AZURE_WEBAPP_NAME }}   # = swiftcart-api\n          package: backend\n          # slot-name: staging   # deploy to the staging slot, then swap (Step 6)</pre>'
            . '<p><strong>Where is the provisioned App Service referenced?</strong> Two lines connect the pipeline to the app you created in Step 1:</p>'
            . '<ul>'
            . '<li><code>azure/login</code> selects the <strong>subscription</strong> the app lives in (via <code>AZURE_SUBSCRIPTION_ID</code>).</li>'
            . '<li><code>azure/webapps-deploy</code>&rsquo;s <code>app-name</code> names the <strong>exact web app</strong> to deploy to — <code>swiftcart-api</code>, read from the <code>AZURE_WEBAPP_NAME</code> variable so it is defined once. It must match the name you passed to <code>az webapp create</code>.</li>'
            . '</ul>'
            . '<p>There is no separate resource-group field — <code>azure/webapps-deploy</code> locates the app by name within the logged-in subscription. To release through the staging slot from Step 6, uncomment <code>slot-name: staging</code> and follow the deploy with a slot swap. Configuration is deliberately <em>absent</em> from this file — it lives in App Settings/Key Vault on Azure, so the workflow only ships code.</p>',
    ],
    [
        'heading' => 'Step 6 — First deploy, verify, and zero-downtime slots',
        'body' => '<p>Commit and push to <code>main</code>. In GitHub\'s <strong>Actions</strong> tab you will see the run; when it finishes, open <code>https://swiftcart-api.azurewebsites.net</code> and check that <code>/api/health</code> returns <code>{ "status": "ok" }</code>.</p>'
            . '<p><strong>Zero-downtime releases with deployment slots (recommended for production).</strong> A <em>slot</em> is a live copy of the app with its own URL. Deploy to a <code>staging</code> slot, smoke-test it, then <strong>swap</strong> it into production instantly — and swap back if anything is wrong.</p>'
            . '<pre class="code"># one-off: add a staging slot (Standard tier or higher)\naz webapp deployment slot create -g swiftcart-rg -n swiftcart-api --slot staging\n\n# in the workflow: deploy to the slot, then swap\n#   azure/webapps-deploy@v3  with  slot-name: staging\n#   az webapp deployment slot swap -g swiftcart-rg -n swiftcart-api --slot staging</pre>'
            . '<p>Slots keep their own App Settings, so you can point staging at a test database.</p>',
    ],
    [
        'heading' => 'Deploy the PHP docs as their own App Service (option a, in full)',
        'body' => '<p>This expands <strong>option (a)</strong> from "Handling the PHP documentation service" into concrete steps. Good news: <code>api-docs.php</code> is fully self-contained (inline CSS, inline SVG, no external assets and no database), so deploying the docs is just shipping <em>one PHP file</em> to a PHP web app. Reuse the same resource group — and the same OIDC login from Step 4.</p>'
            . '<p><strong>1) Create a PHP web app</strong> (reuse the existing plan, or make a cheap B1 plan just for docs):</p>'
            . '<pre class="code">az webapp create \\\n  --name swiftcart-docs --resource-group swiftcart-rg \\\n  --plan swiftcart-plan --runtime "PHP:8.3"</pre>'
            . '<p><strong>2) Point the docs at your deployed app.</strong> The file hardcodes local URLs for the "Back to Swiftcart" link and the API base (<code>$portalUrl</code> and <code>$baseUrl</code> both = <code>http://localhost:4000</code>). Make them environment-driven so no per-environment code edit is needed:</p>'
            . '<pre class="code">// near the top of api-docs.php\n$baseUrl   = getenv("BASE_URL")   ?: "http://localhost:4000";\n$portalUrl = getenv("PORTAL_URL") ?: "http://localhost:4000";</pre>'
            . '<pre class="code">az webapp config appsettings set -g swiftcart-rg -n swiftcart-docs \\\n  --settings \\\n    PORTAL_URL=https://swiftcart-api.azurewebsites.net \\\n    BASE_URL=https://swiftcart-api.azurewebsites.net</pre>'
            . '<p><strong>3) Package the file as the site root.</strong> App Service serves <code>index.php</code> by default, so copy the docs to that name:</p>'
            . '<pre class="code">mkdir docs-dist\ncp frontend/public/api-docs.php docs-dist/index.php   # Windows: copy frontend\\public\\api-docs.php docs-dist\\index.php</pre>'
            . '<p><strong>4) Deploy it (one-off manual test):</strong></p>'
            . '<pre class="code"># zip the folder, then zip-deploy it\ncd docs-dist && zip -r ../docs.zip . && cd ..\naz webapp deploy -g swiftcart-rg -n swiftcart-docs --src-path docs.zip --type zip\n# Windows PowerShell: Compress-Archive -Path docs-dist\\* -DestinationPath docs.zip</pre>'
            . '<p><strong>5) Automate it with GitHub Actions</strong> (redeploys only when the docs change):</p>'
            . '<pre class="code">name: Deploy Swiftcart Docs (PHP)\n\non:\n  push:\n    branches: [ main ]\n    paths: [ "frontend/public/api-docs.php" ]   # only when the docs change\n\npermissions:\n  id-token: write\n  contents: read\n\njobs:\n  deploy-docs:\n    runs-on: ubuntu-latest\n    steps:\n      - uses: actions/checkout@v4\n      - run: |\n          mkdir docs-dist\n          cp frontend/public/api-docs.php docs-dist/index.php\n      - uses: azure/login@v2\n        with:\n          client-id: ${{ secrets.AZURE_CLIENT_ID }}\n          tenant-id: ${{ secrets.AZURE_TENANT_ID }}\n          subscription-id: ${{ secrets.AZURE_SUBSCRIPTION_ID }}\n      - uses: azure/webapps-deploy@v3\n        with:\n          app-name: swiftcart-docs   # the PHP web app created in step 1 above\n          package: docs-dist</pre>'
            . '<p><strong>6) Verify and wire the link.</strong> Browse to <code>https://swiftcart-docs.azurewebsites.net</code> — the docs load and the <code>?p=…</code> router works because it is the same file. Finally, point the main app\'s <strong>Documents</strong> link at the new URL by building the React app with <code>VITE_DOCS_URL=https://swiftcart-docs.azurewebsites.net</code> (or serve both under one domain with Front Door, e.g. <code>/docs</code> → the docs app).</p>'
            . '<p><em>Alternative:</em> skip the rename and deploy <code>api-docs.php</code> as-is — it simply lives at <code>https://swiftcart-docs.azurewebsites.net/api-docs.php</code> instead of the root. Everything else (App Settings, the workflow) stays the same.</p>',
    ],
    [
        'heading' => 'App Service plans & SKUs, explained',
        'body' => '<p>An <strong>App Service plan</strong> is the virtual machine(s) your app runs on; the <strong>SKU</strong> (pricing tier) decides its power and features. From smallest to largest:</p>'
            . '<table class="params">'
            . '<thead><tr><th>Tier</th><th>Example SKUs</th><th>Rough size</th><th>Key capabilities</th><th>Typical use</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Free / Shared</td><td>F1, D1</td><td>Shared CPU, ~1 GB</td><td>No Always On, no custom-domain TLS, daily quotas</td><td>Experiments only</td></tr>'
            . '<tr><td>Basic</td><td>B1–B3</td><td>1–4 vCPU, 1.75–7 GB</td><td>Custom domains + TLS, Always On, manual scale (max 3); no slots/autoscale</td><td>Dev/test, tiny prod</td></tr>'
            . '<tr><td>Standard</td><td>S1–S3</td><td>1–4 vCPU, 1.75–7 GB</td><td>Autoscale (to 10), 5 deployment slots, daily backups</td><td>Small production</td></tr>'
            . '<tr><td>Premium v3</td><td>P0v3–P3v3</td><td>1–8 vCPU, 4–32 GB, SSD</td><td>Faster CPUs, autoscale (to 30), 20 slots, zone redundancy, VNet integration</td><td>Production &amp; SaaS</td></tr>'
            . '<tr><td>Isolated v2</td><td>I1v2–I3v2</td><td>Dedicated (App Service Environment)</td><td>Full network isolation, highest scale, compliance</td><td>Enterprise / regulated</td></tr>'
            . '</tbody></table>'
            . '<p>You pay per plan (per hour), regardless of how many apps run on it. Scaling <em>up</em> = a bigger SKU; scaling <em>out</em> = more instances of the same SKU.</p>',
    ],
    [
        'heading' => 'Key configuration options',
        'body' => '<p>Beyond the SKU, these are the App Service settings you will actually tune:</p>'
            . '<ul>'
            . '<li><strong>Runtime stack &amp; startup command</strong> — Node 20; <code>node src/server.js</code> (or <code>npm start</code>).</li>'
            . '<li><strong>Always On</strong> — keeps the app loaded so the first request is not slow (Basic+).</li>'
            . '<li><strong>Health check</strong> — point it at <code>/api/health</code>; App Service removes unhealthy instances automatically.</li>'
            . '<li><strong>Scale up / out / autoscale</strong> — bigger box, more boxes, or rules that add/remove instances by CPU/memory or schedule (Standard+).</li>'
            . '<li><strong>Deployment slots</strong> — staging + swap for zero-downtime releases (Standard+).</li>'
            . '<li><strong>App Settings &amp; connection strings</strong> — environment configuration and secrets (with Key Vault references).</li>'
            . '<li><strong>Managed identity</strong> — password-less access to Key Vault, MySQL, Storage, etc.</li>'
            . '<li><strong>Networking</strong> — VNet integration and <strong>Private Endpoints</strong> so the app reaches MySQL privately; access restrictions to limit inbound traffic.</li>'
            . '<li><strong>TLS/HTTPS</strong> — HTTPS-only, minimum TLS 1.2, managed certificates for custom domains.</li>'
            . '<li><strong>Platform</strong> — 64-bit, HTTP/2, and (Premium) zone redundancy.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Scenario-based SKU recommendations (incl. SaaS)',
        'body' => '<p>The right plan depends on where Swiftcart is in its life. Concrete recommendations:</p>'
            . '<table class="params">'
            . '<thead><tr><th>Scenario</th><th>SKU</th><th>Instances</th><th>Key configuration</th><th>Why</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>Dev / test</td><td>B1</td><td>1</td><td>HTTPS-only; App Settings for a test DB</td><td>Cheapest; no autoscale/slots needed</td></tr>'
            . '<tr><td>Swiftcart today (single-tenant prod)</td><td>S1 or P0v3</td><td>1–2</td><td>Always On, health check on <code>/api/health</code>, one staging slot, App Insights, Key Vault + managed identity</td><td>Reliable, zero-downtime releases, room to autoscale</td></tr>'
            . '<tr><td>Growing SaaS</td><td>P1v3</td><td>Autoscale 2–10</td><td>Staging slot, VNet + Private Endpoint to MySQL, zone redundancy, Front Door + WAF, per-tenant config in App Settings</td><td>Secure, resilient and elastic for many tenants</td></tr>'
            . '<tr><td>Large / global SaaS</td><td>P2v3–P3v3, multi-region</td><td>Autoscale 3–30 per region</td><td>Two+ regions behind Front Door, zone redundant, slots, API Management, full monitoring/alerts</td><td>High availability and global low latency for SLAs</td></tr>'
            . '<tr><td>Regulated / isolated</td><td>Isolated v2 (ASE)</td><td>As needed</td><td>Dedicated network, private everything, strict access control</td><td>Compliance and network isolation</td></tr>'
            . '</tbody></table>'
            . '<p><strong>For a SaaS specifically</strong>, <strong>Premium v3 with autoscale, a staging slot, zone redundancy, VNet/Private Endpoint to the database, and Front Door + WAF in front</strong> is the sweet spot — it matches Scenario 2 in the Solution Architectures section.</p>',
    ],
    [
        'heading' => 'Best practices — Security',
        'body' => '<ul>'
            . '<li><strong>HTTPS only + TLS 1.2 minimum</strong> — enforce both with a toggle.</li>'
            . '<li><strong>No secrets in code or repo</strong> — App Settings + Key Vault references; managed identity for password-less DB/Key Vault access.</li>'
            . '<li><strong>Disable legacy auth</strong> — turn off FTP and SCM/basic auth; deploy only through the pipeline.</li>'
            . '<li><strong>Private networking</strong> — VNet integration + Private Endpoint so MySQL is off the public internet; use access restrictions to allow only Front Door.</li>'
            . '<li><strong>WAF</strong> — front the app with Azure Front Door or Application Gateway to block common attacks.</li>'
            . '<li><strong>Keep the app\'s own guards</strong> — Helmet, CORS allow-list, rate limiting and parameterized queries stay on.</li>'
            . '<li><strong>Microsoft Defender for App Service</strong> — enable threat detection; patch dependencies with <code>npm audit</code> + Dependabot.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Best practices — Performance',
        'body' => '<ul>'
            . '<li><strong>Premium v3</strong> — faster CPUs and SSD storage noticeably improve response times over Basic/Standard.</li>'
            . '<li><strong>Always On</strong> — avoids cold-start latency on the first request.</li>'
            . '<li><strong>Front Door / CDN</strong> — cache and serve the static React files from an edge near each user; enable gzip/brotli compression and HTTP/2.</li>'
            . '<li><strong>Co-locate</strong> — run the app in the same region as MySQL to cut database latency; size Knex pooling for the plan.</li>'
            . '<li><strong>Scale out under load</strong> — because the app is stateless, adding instances helps more than a single bigger box.</li>'
            . '<li><strong>Cache hot reads</strong> — add Azure Cache for Redis for frequently-read, rarely-changing data.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Best practices — Reliability',
        'body' => '<ul>'
            . '<li><strong>Deployment slots + swap</strong> — release with zero downtime and instant rollback.</li>'
            . '<li><strong>Health check</strong> on <code>/api/health</code> — unhealthy instances are pulled and replaced automatically.</li>'
            . '<li><strong>Run 2+ instances</strong> — never depend on a single instance; combine with <strong>zone redundancy</strong> (Premium v3) to survive a datacenter-zone failure.</li>'
            . '<li><strong>Auto-heal</strong> — configure rules to recycle the app on memory spikes or repeated 5xx errors.</li>'
            . '<li><strong>Managed database resilience</strong> — Azure Database for MySQL Flexible Server offers zone-redundant high-availability options and automated backups.</li>'
            . '<li><strong>Multi-region</strong> — for the strongest SLAs, run two regions behind Front Door with automatic failover.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Best practices — Scalability',
        'body' => '<ul>'
            . '<li><strong>Scale up vs out</strong> — up = bigger SKU (more CPU/RAM per instance); out = more instances behind the built-in load balancer.</li>'
            . '<li><strong>Autoscale rules</strong> — add instances when CPU or memory crosses a threshold, and scale on a <em>schedule</em> for known busy periods (Standard+ / Premium).</li>'
            . '<li><strong>Stateless by design</strong> — Swiftcart keeps no in-memory session, so any instance can serve any request — the key enabler of scale-out.</li>'
            . '<li><strong>Watch the database</strong> — as instances multiply, add MySQL <strong>read replicas</strong> and mind connection-pool limits so the DB does not become the bottleneck.</li>'
            . '<li><strong>Front Door</strong> — a global entry point that also absorbs spikes and distributes traffic.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Monitoring & diagnostics',
        'body' => '<p><strong>Application Insights</strong> is the heart of monitoring — enable it (App Service can auto-instrument Node, or add the <code>applicationinsights</code> SDK) to see request rates, response times, failures, dependency calls and live traces.</p>'
            . '<ul>'
            . '<li><strong>Metrics &amp; alerts</strong> — track CPU, memory, HTTP 5xx and response time in Azure Monitor; alert when, say, the 5xx rate exceeds 2% for 5 minutes.</li>'
            . '<li><strong>Availability tests</strong> — ping <code>/api/health</code> from multiple regions and alert if it goes down.</li>'
            . '<li><strong>Log streaming &amp; diagnostics</strong> — enable App Service application logging (stdout) and stream it live; route logs to <strong>Log Analytics</strong> for KQL queries and retention.</li>'
            . '<li><strong>Live metrics + failures view</strong> — watch traffic in real time and drill into failed requests with end-to-end traces.</li>'
            . '<li><strong>Structured logs</strong> — emit JSON logs (pino/winston) including the tenant and request id so issues are searchable.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Backup & disaster recovery',
        'body' => '<p>Think about two things separately: the <strong>app</strong> and the <strong>data</strong>.</p>'
            . '<ul>'
            . '<li><strong>The app is rebuildable.</strong> Because the code lives in Git and deploys via the pipeline, you can recreate the App Service any time — treat the infrastructure as code (Bicep/Terraform) for fast rebuilds.</li>'
            . '<li><strong>App Service Backup</strong> (Standard+) — scheduled backups of app content and configuration to a storage account, with restore; useful if you keep state on the app (Swiftcart does not, so this is optional).</li>'
            . '<li><strong>The database matters most.</strong> Azure Database for MySQL Flexible Server takes <strong>automated backups</strong> with <strong>point-in-time restore</strong> (retention 7–35 days) and an optional <strong>geo-redundant</strong> copy for regional disaster recovery.</li>'
            . '<li><strong>Deployment slots</strong> double as instant rollback for a bad release.</li>'
            . '<li><strong>Define your RPO/RTO</strong> — how much data loss and downtime is acceptable — and choose geo-redundant backups and/or a second region accordingly.</li>'
            . '</ul>',
    ],
    [
        'heading' => 'Extending to an event-driven architecture',
        'body' => '<p>Once Swiftcart is on App Service, you can grow it into an <strong>event-driven architecture</strong> without rewriting it: the app simply <em>announces</em> that something happened, and separate services react. This is the same idea as Scenario 4 in the Solution Architectures section.</p>'
            . '<p><strong>The pattern:</strong> add a message broker — <strong>Azure Service Bus</strong> (reliable queues) or <strong>Event Grid</strong> (pub/sub fan-out) — and have the app publish domain events like <code>order.created</code> or <code>stock.low</code>. Independent <strong>Azure Functions</strong> subscribe and do the follow-up work (emails, webhooks, analytics) outside the web request.</p>'
            . '<pre class="code">// In the App Service app: after saving a sales order, announce it.\nimport { ServiceBusClient } from "@azure/service-bus";\nconst sb = new ServiceBusClient(process.env.SERVICE_BUS_CONNECTION);\nconst sender = sb.createSender("order-events");\n\nasync function onOrderCreated(order) {\n  await sender.sendMessages({\n    body: { type: "order.created", transactionId: order.transactionId, tenantId: order.tenantId }\n  });\n  // the web request returns immediately; no waiting for email/analytics\n}</pre>'
            . '<pre class="code">// A separate Azure Function reacts to each message (runs on its own).\nmodule.exports = async function (context, message) {\n  switch (message.type) {\n    case "order.created":\n      await sendConfirmationEmail(message);   // email the customer\n      await recordAnalytics(message);         // feed a dashboard\n      break;\n    case "stock.low":\n      await notifyPurchasing(message);        // Teams / Slack / webhook\n      break;\n  }\n};</pre>'
            . '<p><strong>Concrete Swiftcart examples:</strong></p>'
            . '<ul>'
            . '<li><strong>Order confirmation emails</strong> — publish <code>order.created</code>; a Function sends the email so checkout stays instant.</li>'
            . '<li><strong>Low-stock alerts</strong> — when stock drops below a threshold, publish <code>stock.low</code>; a Function posts to Teams or a supplier webhook.</li>'
            . '<li><strong>Analytics pipeline</strong> — every event is also written to storage or a database for dashboards, without slowing the app.</li>'
            . '<li><strong>Real-time UI</strong> — use <strong>Azure Web PubSub</strong> or <strong>SignalR</strong> to push live updates (e.g. a stock counter) to the browser.</li>'
            . '</ul>'
            . '<p><strong>Why this is safe to add:</strong> the App Service app gains just one line — "publish an event" — while every reaction lives in a small, independently deployed and scaled Function. Nothing in the core request path slows down, and you can add new reactions later without touching Swiftcart\'s code.</p>',
    ],
];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function required_label($required)
{
    if ($required === 'conditional') {
        return '<span class="req req-cond">conditional</span>';
    }
    return $required
        ? '<span class="req req-yes">required</span>'
        : '<span class="req req-no">optional</span>';
}

// Section bodies use the two characters "\n" to mark line breaks inside <pre> code
// examples; convert them to real newlines so the code renders on multiple lines.
foreach (['startHere', 'aboutApp', 'frontendNotes', 'altFrameworks', 'knexNotes', 'azureHosting', 'saasEvolution', 'architectureScenarios', 'appServiceDeploy'] as $sectionName) {
    foreach ($$sectionName as &$normBlock) {
        $normBlock['body'] = str_replace('\n', "\n", $normBlock['body']);
    }
    unset($normBlock);
}

// ===========================================================================
// Documentation router — one page per section.
// The docs used to render every section into a single (very large) HTML page.
// Now a lightweight router renders ONE section at a time based on ?p=<slug>,
// and a small client-side script swaps sections without a full reload
// (seamless navigation). Requesting ?p=<slug>&partial=1 returns just the
// section markup, which the client fetches and drops into <div class="doc-main">.
// Links keep real hrefs, so navigation still works with JavaScript disabled.
// ===========================================================================
$docPages = [
    'start-here' => [
        'id' => 'start-here', 'type' => 'blocks', 'data' => 'startHere', 'title' => 'Start Here',
        'desc' => 'New to web apps or Node.js? Read this first — a plain-English welcome, a glossary of the words you will see, and a suggested reading order. No prior experience needed.',
    ],
    'about-application' => [
        'id' => 'about-application', 'type' => 'blocks', 'data' => 'aboutApp', 'title' => 'About Application',
        'desc' => 'Why Swiftcart is built the way it is — the technology choices, their benefits over alternatives, the design principles, and the concepts behind the Node.js API, Knex and Vite.',
    ],
    'frontend-state' => [
        'id' => 'frontend-state', 'type' => 'blocks', 'data' => 'frontendNotes', 'title' => 'Frontend State &amp; Routing',
        'desc' => 'A plain-English look at how the React app keeps track of data, moves between pages, and whether it stores anything in your browser.',
    ],
    'alt-frameworks' => [
        'id' => 'alt-frameworks', 'type' => 'blocks', 'data' => 'altFrameworks', 'title' => 'Alternative Frontend Frameworks',
        'desc' => 'What Swiftcart’s stack would look like with Angular, Vue 3 or Next.js instead of React — and whether each would help with performance, security, state management, routing and form validation.',
    ],
    'knex-database' => [
        'id' => 'knex-database', 'type' => 'blocks', 'data' => 'knexNotes', 'title' => 'Knex &amp; the Database',
        'desc' => 'How Knex engages with the MySQL database, and how it compares with raw SQL, ORMs and schema toolkits across performance, security, productivity and change management.',
    ],
    'azure-hosting' => [
        'id' => 'azure-hosting', 'type' => 'blocks', 'data' => 'azureHosting', 'title' => 'Hosting on Azure',
        'desc' => 'A beginner-friendly tour of how to run Swiftcart on Azure — monolith vs microservices, hosting options, what to consider, security &amp; performance best practices, monitoring, scaling, and CI/CD with Azure DevOps and GitHub Actions.',
    ],
    'saas-evolution' => [
        'id' => 'saas-evolution', 'type' => 'blocks', 'data' => 'saasEvolution', 'title' => 'Evolving into a SaaS',
        'desc' => 'A detailed, beginner-friendly guide to growing Swiftcart into a large-scale Software-as-a-Service app — the JavaScript patterns, frameworks and libraries for multi-tenancy, authentication, security, caching, background jobs, scale-out and observability, with a phased roadmap.',
    ],
    'solution-architectures' => [
        'id' => 'solution-architectures', 'type' => 'blocks', 'data' => 'architectureScenarios', 'title' => 'Solution Architectures on Azure',
        'desc' => 'Scenario-based reference architectures for running Swiftcart as a SaaS on Azure — from a budget-friendly launch to a global, always-on service — each drawn as a simple diagram with a plain-English walkthrough and guidance on when to choose it.',
    ],
    'appservice-deploy' => [
        'id' => 'appservice-deploy', 'type' => 'blocks', 'data' => 'appServiceDeploy', 'title' => 'Deploy to App Service',
        'desc' => 'A complete, step-by-step guide to deploying Swiftcart to Azure App Service with GitHub Actions — how configuration and secrets reach production without committing .env, one-off vs ongoing work, App Service SKUs and settings, scenario-based sizing (including SaaS), security / performance / reliability / scalability best practices, monitoring, backup, and extending to an event-driven architecture.',
    ],
    'api-reference' => [
        'id' => 'api-reference', 'type' => 'api', 'title' => 'API Reference',
        'desc' => 'The backend REST API, grouped by resource. Every endpoint returns JSON; each group below documents its endpoints with detailed parameters and request/response examples.',
    ],
    'how-to' => [
        'id' => 'how-to', 'type' => 'howto', 'title' => 'How to use',
        'desc' => '',
    ],
];

$activePage = (isset($_GET['p']) && isset($docPages[$_GET['p']])) ? $_GET['p'] : 'start-here';
$isPartial = isset($_GET['partial']);

// Render just the active section (the inner content of <div class="doc-main">).
function render_doc_main($active)
{
    global $docPages, $sections, $howto;
    $page = $docPages[$active];
    ob_start();
    if ($page['type'] === 'blocks'):
        $blocks = $GLOBALS[$page['data']];
        ?>
        <section class="section" id="<?php echo e($page['id']); ?>">
            <h2><?php echo $page['title']; ?></h2>
            <?php if ($page['desc'] !== ''): ?><p class="section-desc"><?php echo $page['desc']; ?></p><?php endif; ?>
            <?php foreach ($blocks as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <?php
    elseif ($page['type'] === 'api'):
        ?>
        <section class="section" id="api-reference">
            <h2>API Reference</h2>
            <p class="section-desc"><?php echo $page['desc']; ?></p>
        </section>
        <?php foreach ($sections as $section): ?>
            <section class="section" id="<?php echo e($section['id']); ?>">
                <h2><?php echo e($section['title']); ?></h2>
                <p class="section-desc"><?php echo e($section['description']); ?></p>

                <?php foreach ($section['endpoints'] as $ep): ?>
                    <article class="endpoint">
                        <div class="endpoint-head">
                            <span class="method-pill method-<?php echo strtolower(e($ep['method'])); ?>">
                                <?php echo e($ep['method']); ?>
                            </span>
                            <span class="endpoint-path"><?php echo e($ep['path']); ?></span>
                        </div>
                        <p class="endpoint-desc"><?php echo e($ep['description']); ?></p>

                        <h4>Parameters</h4>
                        <?php if (empty($ep['params'])): ?>
                            <p class="no-params">This endpoint takes no parameters.</p>
                        <?php else: ?>
                            <table class="params">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>In</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                        <th>Description</th>
                                        <th>Example</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ep['params'] as $p): ?>
                                        <tr>
                                            <td class="pname"><code><?php echo e($p['name']); ?></code></td>
                                            <td><span class="pin"><?php echo e($p['in']); ?></span></td>
                                            <td class="ptype"><code><?php echo e($p['type']); ?></code></td>
                                            <td><?php echo required_label($p['required']); ?></td>
                                            <td><?php echo e($p['description']); ?></td>
                                            <td class="pex"><code><?php echo e($p['example']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <h4>Example request</h4>
                        <pre class="code"><?php echo e($ep['request']); ?></pre>

                        <h4>Example response</h4>
                        <pre class="code"><?php echo e($ep['response']); ?></pre>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <?php
    elseif ($page['type'] === 'howto'):
        ?>
        <section class="section howto" id="how-to">
            <h2>How to use these APIs</h2>
            <ol>
                <?php foreach ($howto as $step): ?>
                    <li><?php echo $step; ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php
    endif;
    return ob_get_clean();
}

// Partial request: return only the section markup for the client-side swap.
if ($isPartial) {
    header('Content-Type: text/html; charset=UTF-8');
    echo render_doc_main($activePage);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Swiftcart — <?php echo e(html_entity_decode(strip_tags($docPages[$activePage]['title']))); ?> · Docs</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --surface-alt: #f7f9fc;
            --border: #e6eaf2;
            --text: #1d2739;
            --muted: #6b7688;
            --accent: #2f7d8f;
            --accent-hover: #256574;
            --accent-2: #6a8fd6;
            --radius: 16px;
            --radius-sm: 10px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            line-height: 1.55;
            background: var(--bg);
        }

        .doc-container { max-width: 1000px; margin: 0 auto; padding: 40px 24px 72px; }

        .doc-header { display: flex; align-items: center; gap: 14px; margin-bottom: 6px; }
        .doc-mark {
            display: grid; place-items: center; width: 44px; height: 44px; border-radius: 12px;
            color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 8px 18px rgba(47, 125, 143, 0.28); font-weight: 800;
        }
        h1 { margin: 0; font-size: 1.9rem; font-weight: 800; letter-spacing: -0.02em; }
        .subtitle { color: var(--muted); margin: 6px 0 24px; }

        .stack-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 22px; margin-bottom: 22px; box-shadow: 0 8px 24px rgba(29, 39, 57, 0.07);
        }
        .stack-badges {
            display: grid; gap: 12px; margin: 16px 0 20px;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        }
        .stack-badge {
            display: flex; flex-direction: column; gap: 2px; padding: 14px 16px; border-radius: var(--radius-sm);
            color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 8px 18px rgba(47, 125, 143, 0.22);
        }
        .stack-badge .val { font-size: 1.3rem; font-weight: 800; line-height: 1.1; word-break: break-word; }
        .stack-badge .lab {
            font-size: 0.72rem; font-weight: 700; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .fun-list { margin: 0; padding-left: 20px; display: grid; gap: 10px; }
        .fun-list li { line-height: 1.55; }

        .about-block {
            background: var(--surface); border: 1px solid var(--border); border-left: 4px solid var(--accent);
            border-radius: var(--radius); padding: 18px 22px; margin-bottom: 14px;
            box-shadow: 0 8px 24px rgba(29, 39, 57, 0.06);
        }
        .about-block h3 { margin: 0 0 8px; font-size: 1.12rem; }
        .about-block p { margin: 0 0 10px; }
        .about-block p:last-child { margin-bottom: 0; }
        .about-block ul { margin: 8px 0 10px; padding-left: 20px; display: grid; gap: 6px; }
        .about-block ul:last-child { margin-bottom: 0; }

        .back-link {
            display: inline-block; margin-bottom: 22px; color: var(--accent);
            text-decoration: none; font-weight: 600; font-size: 0.9rem;
        }
        .back-link:hover { text-decoration: underline; }

        .doc-layout { display: flex; align-items: flex-start; gap: 24px; }
        .doc-main { flex: 1 1 auto; min-width: 0; }

        .toc {
            flex: none; width: 232px; position: sticky; top: 20px; align-self: flex-start;
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 18px; box-shadow: 0 8px 24px rgba(29, 39, 57, 0.07);
        }
        .toc h2 { margin: 0 0 10px; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
        .toc ul { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 3px; }
        .toc a {
            display: block; padding: 8px 12px; border-radius: 8px; text-decoration: none;
            color: var(--accent); font-weight: 600; font-size: 0.88rem;
        }
        .toc a:hover { background: var(--accent-soft); }
        .toc a.active { background: var(--accent); color: #fff; }
        .toc a.active:hover { background: var(--accent); }
        .toc ul ul a.sub-active { color: var(--accent); background: var(--accent-soft); }

        .toc-group > a { font-weight: 700; }
        .toc ul ul { margin: 2px 0 6px 10px; padding-left: 10px; border-left: 1px solid var(--border); display: flex; flex-direction: column; gap: 2px; }
        .toc ul ul a { font-size: 0.82rem; padding: 5px 10px; color: var(--muted); font-weight: 600; }
        .toc ul ul a:hover { color: var(--accent); background: var(--accent-soft); }

        @media (max-width: 820px) {
            .doc-layout { flex-direction: column; }
            .toc { position: static; width: 100%; }
            .toc ul { flex-direction: row; flex-wrap: wrap; gap: 8px; }
            .toc a { background: var(--surface-alt); border: 1px solid var(--border); border-radius: 999px; padding: 6px 12px; }
        }

        .section { margin-bottom: 30px; scroll-margin-top: 16px; }
        .section > h2 {
            font-size: 1.4rem; margin: 0 0 4px; padding-bottom: 8px; border-bottom: 2px solid var(--border);
        }
        .section-desc { color: var(--muted); margin: 8px 0 18px; }

        .endpoint {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(29, 39, 57, 0.06);
        }
        .endpoint-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .method-pill {
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; padding: 4px 10px;
            border-radius: 999px; color: #fff;
        }
        .method-get { background: #1f8a5b; }
        .method-post { background: #b8641a; }
        .method-put { background: #2f7d8f; }
        .method-delete { background: #b8341a; }
        .endpoint-path {
            font-family: "Consolas", "SFMono-Regular", "Menlo", monospace; font-size: 1rem;
            font-weight: 700; color: var(--text);
        }
        .endpoint-desc { color: var(--text); margin: 12px 0 4px; }

        h4 {
            margin: 20px 0 8px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--muted);
        }

        table.params { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.params th, table.params td {
            text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border);
            font-size: 0.88rem; vertical-align: top;
        }
        table.params th {
            background: var(--surface-alt); font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: var(--muted);
        }
        table.params td.pname code, table.params td.ptype code, table.params td.pex code {
            font-family: "Consolas", "SFMono-Regular", "Menlo", monospace; font-size: 0.82rem;
            background: var(--surface-alt); border: 1px solid var(--border); border-radius: 6px;
            padding: 1px 6px; color: var(--accent-hover);
        }
        .pin {
            font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            padding: 2px 7px; border-radius: 999px; background: #eef2f9; color: var(--muted);
        }
        .req { font-size: 0.72rem; font-weight: 700; }
        .req-yes { color: #b8341a; }
        .req-no { color: var(--muted); }
        .req-cond { color: #9a6a12; }

        .no-params { color: var(--muted); font-style: italic; font-size: 0.9rem; }

        pre.code {
            margin: 6px 0 0; padding: 14px 16px; border-radius: 8px; background: #0f2135;
            color: #d7e6f5; font-family: "Consolas", "SFMono-Regular", "Menlo", monospace;
            font-size: 0.82rem; line-height: 1.55; overflow-x: auto; white-space: pre;
        }

        code {
            background: var(--surface-alt); border: 1px solid var(--border); border-radius: 6px;
            padding: 1px 6px; font-family: "Consolas", "SFMono-Regular", "Menlo", monospace;
            font-size: 0.85rem; color: var(--accent-hover);
        }

        .howto ol { margin: 0; padding-left: 20px; display: grid; gap: 8px; }

        .doc-footer { margin-top: 34px; color: var(--muted); font-size: 0.82rem; text-align: center; }

        /* Per-section print button (added by script into each section heading) */
        .section-print-btn {
            float: right;
            margin: 2px 0 0 12px;
            padding: 5px 12px;
            font-family: inherit;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            color: var(--accent);
            background: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: 999px;
            cursor: pointer;
        }
        .section-print-btn:hover { background: #eaf3f5; }

        @media print {
            /* never print the buttons themselves */
            .section-print-btn { display: none !important; }

            /* when printing a single section, hide everything except that section */
            body.printing-one .back-link,
            body.printing-one .doc-header,
            body.printing-one .subtitle,
            body.printing-one .stack-card,
            body.printing-one .toc,
            body.printing-one .doc-footer { display: none !important; }
            body.printing-one .doc-layout { display: block; }
            body.printing-one .doc-main > .section:not(.print-target) { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="doc-container">
        <a class="back-link" href="<?php echo e($portalUrl); ?>">&larr; Back to Swiftcart</a>

        <div class="doc-header">
            <span class="doc-mark">S</span>
            <div><h1>Swiftcart API Documentation</h1></div>
        </div>
        <p class="subtitle">
            Detailed reference for the Node.js / Express REST API. Every endpoint returns JSON.
            Base URL: <code><?php echo e($baseUrl); ?></code>
        </p>

        <section class="stack-card" id="doc-hero"<?php echo $activePage === 'start-here' ? '' : ' style="display:none"'; ?>>
            <h2 style="margin:0 0 4px; font-size:1.25rem;">About this documentation app</h2>
            <p class="muted" style="margin:0;">
                This documentation is a small PHP application — running on
                <strong>PHP <?php echo e(PHP_VERSION); ?></strong> — separate from the Node.js API it describes.
            </p>
            <div class="stack-badges">
                <?php foreach ($phpStack as $tech): ?>
                    <div class="stack-badge">
                        <span class="val"><?php echo e($tech['value']); ?></span>
                        <span class="lab"><?php echo e($tech['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <ul class="fun-list">
                <?php foreach ($phpFunFacts as $fact): ?>
                    <li><?php echo $fact; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <div class="doc-layout">
        <nav class="toc">
            <h2>Sections</h2>
            <ul>
                <?php foreach ($docPages as $slug => $navPage): ?>
                    <?php if ($slug === 'api-reference'): ?>
                        <li class="toc-group">
                            <a class="<?php echo $activePage === 'api-reference' ? 'active' : ''; ?>" href="?p=api-reference" data-doc="api-reference"><?php echo $navPage['title']; ?></a>
                            <ul>
                                <?php foreach ($sections as $section): ?>
                                    <li><a href="?p=api-reference#<?php echo e($section['id']); ?>" data-doc="api-reference" data-anchor="<?php echo e($section['id']); ?>"><?php echo e($section['title']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li><a class="<?php echo $activePage === $slug ? 'active' : ''; ?>" href="?p=<?php echo e($slug); ?>" data-doc="<?php echo e($slug); ?>"><?php echo $navPage['title']; ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="doc-main">
        <?php echo render_doc_main($activePage); ?>
        <?php if (false): // Legacy static markup below is disabled; the router renders one section at a time. ?>
        <section class="section" id="start-here">
            <h2>Start Here</h2>
            <p class="section-desc">
                New to web apps or Node.js? Read this first — a plain-English welcome, a glossary of the
                words you will see, and a suggested reading order. No prior experience needed.
            </p>
            <?php foreach ($startHere as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="about-application">
            <h2>About Application</h2>
            <p class="section-desc">
                Why Swiftcart is built the way it is — the technology choices, their benefits over
                alternatives, the design principles, and the concepts behind the Node.js API, Knex and Vite.
            </p>
            <?php foreach ($aboutApp as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="frontend-state">
            <h2>Frontend State &amp; Routing</h2>
            <p class="section-desc">
                A plain-English look at how the React app keeps track of data, moves between pages,
                and whether it stores anything in your browser.
            </p>
            <?php foreach ($frontendNotes as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="alt-frameworks">
            <h2>Alternative Frontend Frameworks</h2>
            <p class="section-desc">
                What Swiftcart’s stack would look like with Angular, Vue 3 or Next.js instead of React —
                and whether each would help with performance, security, state management, routing and form validation.
            </p>
            <?php foreach ($altFrameworks as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="knex-database">
            <h2>Knex &amp; the Database</h2>
            <p class="section-desc">
                How Knex engages with the MySQL database, and how it compares with raw SQL, ORMs and
                schema toolkits across performance, security, productivity and change management.
            </p>
            <?php foreach ($knexNotes as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="azure-hosting">
            <h2>Hosting on Azure</h2>
            <p class="section-desc">
                A beginner-friendly tour of how to run Swiftcart on Azure — monolith vs microservices,
                hosting options, what to consider, security &amp; performance best practices, monitoring,
                scaling, and CI/CD with Azure DevOps and GitHub Actions.
            </p>
            <?php foreach ($azureHosting as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="saas-evolution">
            <h2>Evolving into a SaaS</h2>
            <p class="section-desc">
                A detailed, beginner-friendly guide to growing Swiftcart into a large-scale
                Software-as-a-Service app — the JavaScript patterns, frameworks and libraries for
                multi-tenancy, authentication, security, caching, background jobs, scale-out and
                observability, with a phased roadmap.
            </p>
            <?php foreach ($saasEvolution as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="solution-architectures">
            <h2>Solution Architectures on Azure</h2>
            <p class="section-desc">
                Scenario-based reference architectures for running Swiftcart as a SaaS on Azure —
                from a budget-friendly launch to a global, always-on service — each drawn as a simple
                diagram with a plain-English walkthrough and guidance on when to choose it.
            </p>
            <?php foreach ($architectureScenarios as $block): ?>
                <article class="about-block">
                    <h3><?php echo e($block['heading']); ?></h3>
                    <?php echo $block['body']; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="section" id="api-reference">
            <h2>API Reference</h2>
            <p class="section-desc">
                The backend REST API, grouped by resource. Every endpoint returns JSON; each group
                below documents its endpoints with detailed parameters and request/response examples.
            </p>
        </section>

        <?php foreach ($sections as $section): ?>
            <section class="section" id="<?php echo e($section['id']); ?>">
                <h2><?php echo e($section['title']); ?></h2>
                <p class="section-desc"><?php echo e($section['description']); ?></p>

                <?php foreach ($section['endpoints'] as $ep): ?>
                    <article class="endpoint">
                        <div class="endpoint-head">
                            <span class="method-pill method-<?php echo strtolower(e($ep['method'])); ?>">
                                <?php echo e($ep['method']); ?>
                            </span>
                            <span class="endpoint-path"><?php echo e($ep['path']); ?></span>
                        </div>
                        <p class="endpoint-desc"><?php echo e($ep['description']); ?></p>

                        <h4>Parameters</h4>
                        <?php if (empty($ep['params'])): ?>
                            <p class="no-params">This endpoint takes no parameters.</p>
                        <?php else: ?>
                            <table class="params">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>In</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                        <th>Description</th>
                                        <th>Example</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ep['params'] as $p): ?>
                                        <tr>
                                            <td class="pname"><code><?php echo e($p['name']); ?></code></td>
                                            <td><span class="pin"><?php echo e($p['in']); ?></span></td>
                                            <td class="ptype"><code><?php echo e($p['type']); ?></code></td>
                                            <td><?php echo required_label($p['required']); ?></td>
                                            <td><?php echo e($p['description']); ?></td>
                                            <td class="pex"><code><?php echo e($p['example']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <h4>Example request</h4>
                        <pre class="code"><?php echo e($ep['request']); ?></pre>

                        <h4>Example response</h4>
                        <pre class="code"><?php echo e($ep['response']); ?></pre>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>

        <section class="section howto" id="how-to">
            <h2>How to use these APIs</h2>
            <ol>
                <?php foreach ($howto as $step): ?>
                    <li><?php echo $step; ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>
        </div>
        </div>

        <p class="doc-footer">
            Swiftcart &middot; Generated by PHP <?php echo e(PHP_VERSION); ?> &middot;
            <?php echo e(date('Y-m-d')); ?>
        </p>
    </div>
    <script>
        function printSection(id) {
            var section = document.getElementById(id);
            if (!section) return;
            section.classList.add('print-target');
            document.body.classList.add('printing-one');
            function cleanup() {
                document.body.classList.remove('printing-one');
                section.classList.remove('print-target');
                window.removeEventListener('afterprint', cleanup);
            }
            window.addEventListener('afterprint', cleanup);
            window.print();
        }

        // Attach a "Print" button to each section heading (idempotent, re-run after swaps).
        function enhanceSections() {
            document.querySelectorAll('section.section[id]').forEach(function (section) {
                var heading = section.querySelector('h2');
                if (!heading || heading.querySelector('.section-print-btn')) return;
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'section-print-btn no-print';
                button.textContent = '🖨 Print';
                button.setAttribute('aria-label', 'Print this section');
                button.addEventListener('click', function () { printSection(section.id); });
                heading.appendChild(button);
            });
        }

        // ---- Seamless client-side routing between section pages ----
        var currentPage = <?php echo json_encode($activePage); ?>;
        var pageTitles = <?php echo json_encode(array_map(function ($p) { return html_entity_decode(strip_tags($p['title'])); }, $docPages)); ?>;

        function setActiveLink(p) {
            document.querySelectorAll('.toc a[data-doc]').forEach(function (a) {
                var isTop = a.getAttribute('data-doc') === p && !a.hasAttribute('data-anchor');
                a.classList.toggle('active', isTop);
            });
        }

        function loadPage(p, anchor, push) {
            var main = document.querySelector('.doc-main');
            if (!main) return;
            main.setAttribute('aria-busy', 'true');
            fetch('?p=' + encodeURIComponent(p) + '&partial=1')
                .then(function (r) { if (!r.ok) throw new Error('bad'); return r.text(); })
                .then(function (html) {
                    main.innerHTML = html;
                    main.removeAttribute('aria-busy');
                    currentPage = p;
                    setActiveLink(p);
                    var hero = document.getElementById('doc-hero');
                    if (hero) hero.style.display = (p === 'start-here') ? '' : 'none';
                    enhanceSections();
                    if (pageTitles[p]) document.title = 'Swiftcart \u2014 ' + pageTitles[p] + ' \u00B7 Docs';
                    var url = '?p=' + encodeURIComponent(p) + (anchor ? ('#' + anchor) : '');
                    if (push) history.pushState({ p: p }, '', url);
                    if (anchor) { var el = document.getElementById(anchor); if (el) { el.scrollIntoView(); return; } }
                    window.scrollTo(0, 0);
                })
                .catch(function () {
                    window.location = '?p=' + encodeURIComponent(p) + (anchor ? ('#' + anchor) : '');
                });
        }

        document.addEventListener('click', function (ev) {
            var t = ev.target;
            var a = (t && t.closest) ? t.closest('a[data-doc]') : null;
            if (!a) return;
            if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
            ev.preventDefault();
            var p = a.getAttribute('data-doc');
            var anchor = a.getAttribute('data-anchor') || '';
            if (p === currentPage) {
                if (anchor) {
                    var el = document.getElementById(anchor);
                    if (el) el.scrollIntoView();
                    history.replaceState({ p: p }, '', '?p=' + p + '#' + anchor);
                } else {
                    window.scrollTo(0, 0);
                }
                return;
            }
            loadPage(p, anchor, true);
        });

        window.addEventListener('popstate', function (ev) {
            var params = new URLSearchParams(window.location.search);
            var p = (ev.state && ev.state.p) || params.get('p') || 'start-here';
            loadPage(p, '', false);
        });

        document.addEventListener('DOMContentLoaded', function () {
            enhanceSections();
            history.replaceState({ p: currentPage }, '', '?p=' + currentPage);
        });
    </script>
</body>
</html>
