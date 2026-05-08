const http = require("http");
const path = require("path");
const tls = require("tls");

const MAX_BODY_SIZE = 64 * 1024;
const DEFAULT_SUCCESS_URL = "/?contato=enviado#contato";
const DEFAULT_ERROR_URL = "/?contato=erro#contato";

function loadMailConfig() {
  return require(path.join(__dirname, "config", "mail.js"));
}

function cleanHeader(value) {
  return String(value || "").replace(/[\r\n]/g, "").trim();
}

function cleanBody(value) {
  return String(value || "").replace(/\r\n/g, "\n").trim();
}

function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
}

function encodeMimeWord(value) {
  return `=?UTF-8?B?${Buffer.from(cleanHeader(value), "utf8").toString("base64")}?=`;
}

function encodeAddress(name, email) {
  return `${encodeMimeWord(name)} <${cleanHeader(email)}>`;
}

function responseIsComplete(buffer) {
  const lines = buffer.split(/\r?\n/).filter(Boolean);
  const lastLine = lines[lines.length - 1] || "";
  return /^\d{3} /.test(lastLine);
}

class SmtpSession {
  constructor(socket) {
    this.socket = socket;
    this.buffer = "";
    this.waiting = null;
    this.error = null;

    socket.on("data", (chunk) => {
      this.buffer += chunk.toString("utf8");
      this.flush();
    });

    socket.on("error", (error) => this.fail(error));
    socket.on("end", () => this.fail(new Error("SMTP connection closed.")));
  }

  fail(error) {
    this.error = error;
    if (this.waiting) {
      const waiting = this.waiting;
      this.waiting = null;
      waiting.reject(error);
    }
  }

  flush() {
    if (!this.waiting || !responseIsComplete(this.buffer)) {
      return;
    }

    const response = this.buffer;
    this.buffer = "";
    const waiting = this.waiting;
    this.waiting = null;
    waiting.resolve(response);
  }

  read() {
    if (this.error) {
      return Promise.reject(this.error);
    }

    return new Promise((resolve, reject) => {
      this.waiting = { resolve, reject };
      this.flush();
    });
  }

  async expect(codes) {
    const response = await this.read();
    const code = Number.parseInt(response.slice(0, 3), 10);

    if (!codes.includes(code)) {
      throw new Error(`SMTP response error: ${response.trim()}`);
    }

    return response;
  }

  async command(command, codes) {
    this.socket.write(`${command}\r\n`);
    return this.expect(codes);
  }
}

function openSmtpSocket(config) {
  return new Promise((resolve, reject) => {
    const socket = tls.connect({
      host: config.host,
      port: Number(config.port),
      servername: config.host,
    });

    const timer = setTimeout(() => {
      socket.destroy();
      reject(new Error("SMTP connection timed out."));
    }, 15000);

    socket.once("secureConnect", () => {
      clearTimeout(timer);
      socket.setTimeout(15000, () => socket.destroy(new Error("SMTP timeout.")));
      resolve(socket);
    });

    socket.once("error", (error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

function dotStuff(body) {
  return body
    .replace(/\r?\n/g, "\r\n")
    .split("\r\n")
    .map((line) => (line.startsWith(".") ? `.${line}` : line))
    .join("\r\n");
}

async function smtpSend(config, replyTo, subject, body) {
  const socket = await openSmtpSocket(config);
  const smtp = new SmtpSession(socket);
  const fromEmail = cleanHeader(config.fromEmail);
  const toEmail = cleanHeader(config.toEmail);

  try {
    await smtp.expect([220]);
    await smtp.command("EHLO gauchatecpar.com.br", [250]);
    await smtp.command("AUTH LOGIN", [334]);
    await smtp.command(Buffer.from(config.username, "utf8").toString("base64"), [334]);
    await smtp.command(Buffer.from(config.password, "utf8").toString("base64"), [235]);
    await smtp.command(`MAIL FROM:<${fromEmail}>`, [250]);
    await smtp.command(`RCPT TO:<${toEmail}>`, [250, 251]);
    await smtp.command("DATA", [354]);

    const headers = [
      `From: ${encodeAddress(config.fromName, fromEmail)}`,
      `To: ${encodeAddress(config.toName, toEmail)}`,
      `Reply-To: ${cleanHeader(replyTo)}`,
      `Subject: ${encodeMimeWord(subject)}`,
      "MIME-Version: 1.0",
      "Content-Type: text/plain; charset=UTF-8",
      "Content-Transfer-Encoding: 8bit",
    ];

    socket.write(`${headers.join("\r\n")}\r\n\r\n${dotStuff(body)}\r\n.\r\n`);
    await smtp.expect([250]);
    await smtp.command("QUIT", [221]);
  } finally {
    socket.end();
  }
}

function readRequestBody(req) {
  return new Promise((resolve, reject) => {
    let body = "";

    req.setEncoding("utf8");
    req.on("data", (chunk) => {
      body += chunk;

      if (body.length > MAX_BODY_SIZE) {
        reject(new Error("Request body too large."));
        req.destroy();
      }
    });
    req.on("end", () => resolve(body));
    req.on("error", reject);
  });
}

function parseFields(rawBody, contentType) {
  if (contentType.includes("application/json")) {
    return JSON.parse(rawBody || "{}");
  }

  return Object.fromEntries(new URLSearchParams(rawBody));
}

function wantsJson(req) {
  return String(req.headers.accept || "").includes("application/json");
}

function sendJson(res, statusCode, payload) {
  res.writeHead(statusCode, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store",
  });
  res.end(JSON.stringify(payload));
}

function redirect(res, location) {
  res.writeHead(303, { Location: location });
  res.end();
}

function sendContactResponse(req, res, ok, statusCode = ok ? 200 : 400) {
  if (wantsJson(req)) {
    sendJson(res, statusCode, { ok });
    return;
  }

  redirect(res, ok ? DEFAULT_SUCCESS_URL : DEFAULT_ERROR_URL);
}

async function sendContactFromFields(fields) {
  if (String(fields.empresa || "").trim() !== "") {
    return { skipped: true };
  }

  const name = cleanBody(fields.nome);
  const email = cleanHeader(fields.email);
  const phone = cleanBody(fields.telefone);
  const message = cleanBody(fields.mensagem);

  if (!name || !message || !isValidEmail(email)) {
    const error = new Error("Invalid contact form data.");
    error.statusCode = 422;
    throw error;
  }

  const body = [
    "Novo contato pelo site Gaucha TecPar",
    "",
    `Nome: ${name}`,
    `Email: ${email}`,
    `Telefone: ${phone}`,
    "",
    "Mensagem:",
    message,
    "",
  ].join("\n");

  await smtpSend(loadMailConfig(), email, "Contato pelo site - Gaucha TecPar", body);
  return { sent: true };
}

async function handleContactRequest(req, res) {
  if (req.method !== "POST") {
    sendContactResponse(req, res, false, 405);
    return;
  }

  try {
    const rawBody = await readRequestBody(req);
    const fields = parseFields(rawBody, String(req.headers["content-type"] || ""));
    await sendContactFromFields(fields);
    sendContactResponse(req, res, true);
  } catch (error) {
    console.error(error);
    sendContactResponse(req, res, false, error.statusCode || 500);
  }
}

if (require.main === module) {
  const port = Number(process.env.PORT || 3005);

  http
    .createServer((req, res) => {
      const url = new URL(req.url, `http://127.0.0.1:${port}`);

      if (url.pathname === "/send-contact") {
        handleContactRequest(req, res);
        return;
      }

      res.writeHead(404, { "Content-Type": "text/plain; charset=utf-8" });
      res.end("Not found");
    })
    .listen(port, () => {
      console.log(`Contact endpoint listening on http://127.0.0.1:${port}/send-contact`);
    });
}

module.exports = {
  handleContactRequest,
  sendContactFromFields,
};
