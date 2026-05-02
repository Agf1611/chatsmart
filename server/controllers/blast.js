const { dbQuery } = require("../database");
const { formatReceipt } = require("../lib/helper");
const wa = require("../whatsapp");

const inProgress = {};

async function updateStatus(campaignId, receiver, status) {
  await dbQuery("UPDATE blasts SET status = ? WHERE receiver = ? AND campaign_id = ?", [
    status,
    receiver,
    campaignId,
  ]);
}

async function checkBlast(campaignId, receiver) {
  const rows = await dbQuery("SELECT status FROM blasts WHERE receiver = ? AND campaign_id = ? LIMIT 1", [
    receiver,
    campaignId,
  ]);

  return rows.length > 0 && rows[0].status === "pending";
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function normalizeBlastMessage(type, rawMessage) {
  if (typeof rawMessage === "string") {
    try {
      return JSON.parse(rawMessage);
    } catch (error) {
      return rawMessage;
    }
  }

  if (type === "image") {
    return rawMessage;
  }

  return rawMessage;
}

const sendBlastMessage = async (req, res) => {
  const data = JSON.parse(req.body.data);
  const details = data.data || [];
  const campaignId = data.campaign_id;

  if (inProgress[campaignId]) {
    console.log(`still any progress in campaign id ${campaignId}, request canceled.`);
    return res.send({ status: "in_progress" });
  }

  inProgress[campaignId] = true;
  console.log(`progress campaign ID : ${campaignId} started`);
  res.send({ status: "in_progress" });

  (async () => {
    for (let index = 0; index < details.length; index += 1) {
      const item = details[index];
      const delay = Number(data.delay || 0);
      await wait(delay * 1000);

      if (!data.sender || !item?.receiver || !item?.message) {
        console.log("wrong data, progress canceled!");
        continue;
      }

      const stillPending = await checkBlast(campaignId, item.receiver);
      if (!stillPending) {
        console.log("no pending, not send!");
        continue;
      }

      try {
        const exists = await wa.isExist(data.sender, formatReceipt(item.receiver));
        if (!exists) {
          await updateStatus(campaignId, item.receiver, "failed");
          continue;
        }
      } catch (error) {
        console.log("Error in wa.isExist:", error);
        await updateStatus(campaignId, item.receiver, "failed");
        continue;
      }

      try {
        let result = false;
        const normalizedMessage = normalizeBlastMessage(data.type, item.message);

        if (data.type === "media" || data.type === "image") {
          result = await wa.sendMedia(
            data.sender,
            item.receiver,
            normalizedMessage.type || "image",
            normalizedMessage.url,
            normalizedMessage.caption || "",
            normalizedMessage.ptt || false,
            normalizedMessage.filename
          );
        } else if (data.type === "button") {
          result = await wa.sendMessage(data.sender, item.receiver, normalizedMessage);
        } else if (data.type === "template") {
          result = await wa.sendMessage(data.sender, item.receiver, normalizedMessage);
        } else if (data.type === "list") {
          result = await wa.sendMessage(data.sender, item.receiver, normalizedMessage);
        } else {
          result = await wa.sendText(data.sender, item.receiver, item.message);
        }

        await updateStatus(campaignId, item.receiver, result ? "success" : "failed");
      } catch (error) {
        console.log("Error in send operation:", error.message || error);
        if (String(error?.message || "").includes("503")) {
          console.log("Server is busy, waiting for 5 seconds before retrying...");
          await wait(5000);
          index -= 1;
          continue;
        }

        await updateStatus(campaignId, item.receiver, "failed");
      }
    }

    delete inProgress[campaignId];
  })().catch((error) => {
    console.log(`Error in send operation: ${error}`);
    delete inProgress[campaignId];
  });
};

module.exports = { sendBlastMessage };
