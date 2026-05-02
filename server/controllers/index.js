const wa = require("../whatsapp");

function handleResponse(result, res, messageWhenFailed = "Check your whatsapp connection") {
  if (result) {
    return res.send({ status: true, data: result });
  }

  return res.send({ status: false, message: messageWhenFailed });
}

const createInstance = async (req, res) => {
  const { token } = req.body;
  if (!token) {
    return res.status(403).send("Token needed");
  }

  try {
    const response = await wa.connectToWhatsApp(token, req.io);
    return res.send({
      status: response?.status ?? "processing",
      qrcode: response?.qrcode,
      code: response?.code,
      message: response?.message || "Processing",
    });
  } catch (error) {
    console.log(error);
    return res.send({ status: false, error: error.message || error });
  }
};

const sendText = async (req, res) => {
  const { token, number, text } = req.body;
  if (!token || !number || !text) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.sendText(token, number, text);
  return handleResponse(result, res);
};

const sendMedia = async (req, res) => {
  const { token, number, type, url, caption, ptt, filename } = req.body;
  if (!token || !number || !type || !url) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.sendMedia(token, number, type, url, caption || "", ptt, filename);
  return handleResponse(result, res);
};

const sendButtonMessage = async (req, res) => {
  const { token, number, button, message, footer, image } = req.body;
  if (!token || !number || !button || !message) {
    return res.send({ status: false, message: "Check your parameterr" });
  }

  const result = await wa.sendButtonMessage(token, number, JSON.parse(button), message, footer || "", image);
  return handleResponse(result, res);
};

const sendTemplateMessage = async (req, res) => {
  const { token, number, button, text, footer, image } = req.body;
  if (!token || !number || !button || !text || !footer) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.sendTemplateMessage(token, number, JSON.parse(button), text, footer, image);
  return handleResponse(result, res);
};

const sendListMessage = async (req, res) => {
  const { token, number, list, text, footer, title, buttonText } = req.body;
  if (!token || !number || !list || !text || !title || !buttonText) {
    return res.send({ status: false, message: "Check your parameterrss" });
  }

  const result = await wa.sendListMessage(
    token,
    number,
    JSON.parse(list),
    text,
    footer || "",
    title,
    buttonText
  );
  return handleResponse(result, res);
};

const sendPoll = async (req, res) => {
  const { token, number, name, options, countable } = req.body;
  if (!token || !number || !name || !options || typeof countable === "undefined") {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.sendPollMessage(token, number, name, JSON.parse(options), countable);
  return handleResponse(result, res);
};

const fetchGroups = async (req, res) => {
  const { token } = req.body;
  if (!token) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.fetchGroups(token);
  return handleResponse(result, res);
};

const deleteCredentials = async (req, res) => {
  const { token } = req.body;
  if (!token) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.deleteCredentials(token);
  return handleResponse(result, res, "Nothing deleted");
};

const checkNumber = async (req, res) => {
  const { token, number } = req.body;
  if (!token || !number) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const active = await wa.isExist(token, number);
  return res.send({
    status: true,
    active,
    message: active,
  });
};

const logoutDevice = async (req, res) => {
  const { token } = req.body;
  if (!token) {
    return res.send({ status: false, message: "Check your parameter" });
  }

  const result = await wa.deleteCredentials(token);
  return res.send(result);
};

module.exports = {
  createInstance,
  sendText,
  sendMedia,
  sendButtonMessage,
  sendTemplateMessage,
  sendListMessage,
  deleteCredentials,
  fetchGroups,
  sendPoll,
  logoutDevice,
  checkNumber,
};
