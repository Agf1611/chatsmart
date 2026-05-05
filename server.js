"use strict";

const wa = require("./server/whatsapp");
const fs = require("fs");
const path = require("path");
require("dotenv").config();
const lib = require("./server/lib");
global.log = lib.log;

/**
 * EXPRESS FOR ROUTING
 */
const express = require("express");
const app = express();
const http = require("http");
const server = http.createServer(app);
app.set("trust proxy", true);

/**
 * SOCKET.IO
 */
const { Server } = require("socket.io");
const allowedOrigins = Array.from(
  new Set(
    [
      process.env.CORS_ALLOWED_ORIGINS || "",
      process.env.APP_URL || "",
      process.env.WA_URL_SERVER || "",
    ]
      .join(",")
      .split(",")
      .map((origin) => origin.trim())
      .filter(Boolean)
      .map((origin) => {
        try {
          return new URL(origin).origin;
        } catch (error) {
          return origin;
        }
      })
  )
);
const isPrivateOrigin = (origin) => {
  try {
    const { hostname } = new URL(origin);
    return /^(localhost|127\.0\.0\.1)$/i.test(hostname)
      || /^192\.168\./.test(hostname)
      || /^10\./.test(hostname)
      || /^172\.(1[6-9]|2\d|3[0-1])\./.test(hostname);
  } catch (error) {
    return false;
  }
};
const io = new Server(server, {
  cors: {
    origin: (origin, callback) => {
      if (!origin) {
        return callback(null, true);
      }

      try {
        const normalizedOrigin = new URL(origin).origin;
        if (!allowedOrigins.length || allowedOrigins.includes(normalizedOrigin) || isPrivateOrigin(origin)) {
          return callback(null, true);
        }
      } catch (error) {
        if (isPrivateOrigin(origin)) {
          return callback(null, true);
        }
      }

      return callback(new Error("Origin not allowed by Socket.IO CORS"));
    },
    methods: ["GET", "POST"],
  },
});
const isHosting = String(process.env.TYPE_SERVER || "").toLowerCase() === "hosting";
const port = isHosting
  ? process.env.PORT || process.env.PORT_NODE || 3100
  : process.env.PORT_NODE || process.env.PORT || 3100;

function writeRuntimeLog(type, error) {
  try {
    const logDir = path.join(process.cwd(), "storage", "logs");
    fs.mkdirSync(logDir, { recursive: true });
    const logFile = path.join(logDir, "node-runtime.log");
    const detail = error && error.stack ? error.stack : String(error || "");
    fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${type}: ${detail}\n\n`);
  } catch (logError) {
    console.error("Failed writing node runtime log", logError);
  }
}

process.on("unhandledRejection", (reason) => {
  console.error("Unhandled promise rejection:", reason);
  writeRuntimeLog("unhandledRejection", reason);
});

process.on("uncaughtException", (error) => {
  console.error("Uncaught exception:", error);
  writeRuntimeLog("uncaughtException", error);
});

process.on("warning", (warning) => {
  console.warn("Node warning:", warning);
  writeRuntimeLog("warning", warning);
});

app.use((req, res, next) => {
  res.set("Cache-Control", "no-store");
  req.io = io;
  // res.set('Cache-Control', 'no-store')
  next();
});

const bodyParser = require("body-parser");

// parse application/x-www-form-urlencoded
app.use(
  bodyParser.urlencoded({
    extended: false,
    limit: "50mb",
    parameterLimit: 100000,
  })
);
// parse application/json
app.use(bodyParser.json());
app.use(express.static("src/public"));
app.use(require("./server/router"));

// console.log(process.argv)

io.on("connection", (socket) => {
  socket.on("StartConnection", (data) => {
    wa.connectToWhatsApp(data, io);
  });
  socket.on("ConnectViaCode", (data) => {
    wa.connectToWhatsApp(data, io, true);
  });
  socket.on("LogoutDevice", (device) => {
    wa.deleteCredentials(device, io);
  });
});
server.listen(port, console.log(`Server run and listening port: ${port}`));

wa.restoreSessions(io)
  .then((results) => {
    if (results.length > 0) {
      console.log("Restored sessions:", results);
    }
  })
  .catch((error) => {
    console.log("Failed restoring sessions", error.message);
  });
