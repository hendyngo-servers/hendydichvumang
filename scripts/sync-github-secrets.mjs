#!/usr/bin/env node
import { readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { resolve } from "node:path";

const REPOSITORY = "hendyngo-servers/hendydichvumang";
const REQUIRED = ["CLOUDFLARE_API_TOKEN", "CLOUDFLARE_ACCOUNT_ID", "BOT_TOKEN"];
const envPath = resolve(process.cwd(), ".env");

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

let config;
try {
  config = parseEnv(readFileSync(envPath, "utf8"));
} catch (error) {
  console.error("Không đọc được file .env. Hãy sao chép .env.example thành .env và điền 3 giá trị bí mật.");
  process.exit(1);
}

const missing = REQUIRED.filter((key) => !config[key]);
if (missing.length) {
  console.error("Thiếu giá trị trong .env: " + missing.join(", "));
  process.exit(1);
}

const ghCheck = spawnSync("gh", ["auth", "status"], { encoding: "utf8" });
if (ghCheck.error || ghCheck.status !== 0) {
  console.error("GitHub CLI chưa đăng nhập. Hãy chạy: gh auth login");
  process.exit(1);
}

for (const name of REQUIRED) {
  // Truyền secret qua stdin, tuyệt đối không đưa giá trị vào command line hoặc log.
  const result = spawnSync("gh", ["secret", "set", name, "--repo", REPOSITORY], {
    input: config[name] + "\n",
    encoding: "utf8",
    windowsHide: true
  });
  if (result.error || result.status !== 0) {
    console.error(`Không thể cập nhật GitHub Secret ${name}. Kiểm tra quyền ghi Actions secrets của GitHub CLI.`);
    if (result.stderr) console.error(result.stderr.trim());
    process.exit(1);
  }
  console.log(`Đã đồng bộ GitHub Secret: ${name}`);
}
console.log("Hoàn tất. Workflow sẽ tự tạo WEBHOOK_SECRET và tự khai báo URL Cloudflare khi triển khai.");
