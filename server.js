"use strict";

const wa = require("./server/whatsapp");
const fs = require("fs");
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

/**
 * SOCKET.IO
 */
const { Server } = require("socket.io");
const allowedOrigins = (process.env.CORS_ALLOWED_ORIGINS || process.env.APP_URL || "")
  .split(",")
  .map((origin) => origin.trim())
  .filter(Boolean);
const io = new Server(server, {
  cors: {
    origin: allowedOrigins.length ? allowedOrigins : true,
    methods: ["GET", "POST"],
  },
});
const isHosting = String(process.env.TYPE_SERVER || "").toLowerCase() === "hosting";
const port = isHosting
  ? process.env.PORT || process.env.PORT_NODE || 3100
  : process.env.PORT_NODE || process.env.PORT || 3100;
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
