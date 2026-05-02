const mysql2 = require("mysql2");

require("dotenv").config();

const db = mysql2.createPool({
  host: process.env.DB_HOST,
  user: process.env.DB_USERNAME,
  database: process.env.DB_DATABASE,
  password: process.env.DB_PASSWORD,
  port: process.env.DB_PORT || undefined,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

async function setStatus(device, status) {
  try {
    await db.promise().query("UPDATE devices SET status = ? WHERE body = ?", [status, String(device)]);
    return true;
  } catch (error) {
    return false;
  }
}

async function dbQuery(query, params = []) {
  const [rows] = await db.promise().query(query, params);
  return rows;
}

module.exports = { setStatus, dbQuery, db };
