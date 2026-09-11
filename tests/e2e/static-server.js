const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");

const root = process.cwd();
const contentTypes = {
  ".css": "text/css; charset=utf-8",
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".woff2": "font/woff2"
};

http
  .createServer((request, response) => {
    response.setHeader("Access-Control-Allow-Origin", "*");

    if (request.method === "OPTIONS") {
      response.writeHead(204);
      response.end();
      return;
    }

    const url = new URL(request.url, "http://127.0.0.1");
    const pathname = url.pathname === "/" ? "/index.html" : url.pathname;
    const file = path.resolve(root, `.${decodeURIComponent(pathname)}`);

    if (!file.startsWith(`${root}${path.sep}`)) {
      response.writeHead(403);
      response.end();
      return;
    }

    fs.readFile(file, (error, content) => {
      if (error) {
        response.writeHead(error.code === "ENOENT" ? 404 : 500);
        response.end();
        return;
      }

      response.writeHead(200, {
        "Content-Type": contentTypes[path.extname(file)] || "application/octet-stream"
      });
      response.end(content);
    });
  })
  .listen(18184, "127.0.0.1");
