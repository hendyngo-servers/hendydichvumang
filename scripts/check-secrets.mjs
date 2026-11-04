#!/usr/bin/env node
import { readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";

const listing = spawnSync("git", ["ls-files", "-z"], { encoding: "utf8" });
if (listing.error || listing.status !== 0) {
  console.error("Không thể liệt kê file Git. Hãy chạy security:check bên trong repository.");
  process.exit(2);
}

const knownPublicTestFixtures = new Set([
  "123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi",
  "5768337691:AAH5YkoiEuPk8-FZa32hStHTqXiLPtAEhx8"
]);
const findings = [];
const files = listing.stdout.split("\0").filter(Boolean);
const binaryExtensions = /\.(?:png|jpe?g|gif|webp|ico|pdf|zip|woff2?|ttf|eot)$/i;

for (const path of files) {
  if (binaryExtensions.test(path)) continue;
  let source;
  try { source = readFileSync(path, "utf8"); } catch { continue; }

  const botTokens = source.match(/\b\d{6,12}:[A-Za-z0-9_-]{30,}\b/g) || [];
  for (const token of botTokens) {
    if (!knownPublicTestFixtures.has(token)) findings.push({ path, type: "Telegram Bot Token" });
  }

  if (/-----BEGIN (?:[A-Z0-9 ]+ )?PRIVATE KEY-----/.test(source)) {
    findings.push({ path, type: "Private key" });
  }
  if (/\bCLOUDFLARE_API_TOKEN\s*=\s*["']?[^\s"'#]{24,}/.test(source)) {
    findings.push({ path, type: "Cloudflare API Token assignment" });
  }
  if (/\b(?:ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})\b/.test(source)) {
    findings.push({ path, type: "GitHub Personal Access Token" });
  }
  if (/\bAKIA[0-9A-Z]{16}\b/.test(source)) {
    findings.push({ path, type: "AWS access key" });
  }
}

if (findings.length) {
  for (const item of findings) {
    console.error("Có thể chứa bí mật: " + item.path + " (" + item.type + ").");
  }
  console.error("Hãy chuyển credential sang GitHub Secrets / Cloudflare Worker Secrets rồi chạy lại.");
  process.exit(1);
}
console.log("Security check passed: không phát hiện credential phổ biến trong các file đang được Git theo dõi.");
