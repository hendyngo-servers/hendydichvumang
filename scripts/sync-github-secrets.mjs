#!/usr/bin/env node
import { readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { resolve } from "node:path";

const REPOSITORY = "hendyngo-servers/hendydichvumang";
const REQUIRED = ["CLOUDFLARE_API_TOKEN", "CLOUDFLARE_ACCOUNT_ID", "BOT_TOKEN"];
const OPTIONAL = [
  "PAYMENT_BANK_CODE",
  "PAYMENT_BANK_NAME",
  "PAYMENT_ACCOUNT_NUMBER",
  "PAYMENT_ACCOUNT_NAME",
  "PAYMENT_QR_URL",
  "PAYMENT_TRANSFER_NOTE_PREFIX",
  "SUPPORT_URL"
];

function parseEnv(source) {
  const result = {};
  for (const [index, rawLine] of source.split(/\r?\n/).entries()) {
    const line = rawLine.trim();
    if (!line || line.startsWith("#")) continue;
    const match = line.match(/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/);
    if (!match) throw new Error(`Dòng ${index + 1} trong .env không đúng định dạng KEY=VALUE.`);
    let value = match[2].trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    result[match[1]] = value;
  }
  return result;
}

function gh(args, input) {
  return spawnSync("gh", args, { input, encoding: "utf8", windowsHide: true });
}

let config;
try {
  config = parseEnv(readFileSync(resolve(process.cwd(), ".env"), "utf8"));
} catch {
  console.error("Không đọc được file .env. Hãy sao chép .env.example thành .env và điền 3 giá trị bắt buộc.");
  process.exit(1);
}

const missing = REQUIRED.filter((key) => !config[key]);
if (missing.length) {
  console.error("Thiếu giá trị trong .env: " + missing.join(", "));
  process.exit(1);
}

const auth = gh(["auth", "status"]);
if (auth.error || auth.status !== 0) {
  console.error("GitHub CLI chưa đăng nhập. Hãy chạy: gh auth login");
  process.exit(1);
}

for (const name of REQUIRED) {
  const result = gh(["secret", "set", name, "--repo", REPOSITORY], config[name] + "\n");
  if (result.error || result.status !== 0) {
    console.error(`Không thể cập nhật GitHub Secret ${name}. Kiểm tra quyền ghi Actions secrets.`);
    if (result.stderr) console.error(result.stderr.trim());
    process.exit(1);
  }
  console.log(`Đã đồng bộ GitHub Secret: ${name}`);
}

for (const name of OPTIONAL) {
  const value = String(config[name] || "").trim();
  if (value) {
    const result = gh(["secret", "set", name, "--repo", REPOSITORY], value + "\n");
    if (result.error || result.status !== 0) {
      console.error(`Không thể cập nhật GitHub Secret tùy chọn ${name}.`);
      if (result.stderr) console.error(result.stderr.trim());
      process.exit(1);
    }
    console.log(`Đã đồng bộ cấu hình thanh toán: ${name}`);
  } else {
    // Remove an old optional secret when the corresponding .env value is blank.
    const result = gh(["secret", "delete", name, "--repo", REPOSITORY]);
    if (!result.error && result.status === 0) console.log(`Đã xóa cấu hình tùy chọn: ${name}`);
    else console.log(`Bỏ qua cấu hình tùy chọn chưa đặt: ${name}`);
  }
}

console.log("Hoàn tất. Workflow sẽ tự cấu hình Worker, webhook và các thông tin nhận thanh toán tùy chọn.");
