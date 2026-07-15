const fs = require("fs");
const path = require("path");

const distDir = path.join(__dirname, "..", "frontend", "dist");
const publicDir = path.join(__dirname, "..", "backend", "public");

if (!fs.existsSync(distDir)) {
  console.error("frontend/dist not found. Build the frontend first (npm run build:frontend).");
  process.exit(1);
}

fs.rmSync(publicDir, { recursive: true, force: true });
fs.mkdirSync(publicDir, { recursive: true });
fs.cpSync(distDir, publicDir, { recursive: true });

console.log("Copied frontend/dist -> backend/public");
