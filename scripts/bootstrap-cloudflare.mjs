#!/usr/bin/env node
const accountId = process.env.CLOUDFLARE_ACCOUNT_ID;
const apiToken = process.env.CLOUDFLARE_API_TOKEN;
const workerName = "hendydichvumang-bot";
const pagesProject = "hendydichvumang";

if (!accountId || !apiToken) {
  console.error("Thiếu CLOUDFLARE_ACCOUNT_ID hoặc CLOUDFLARE_API_TOKEN.");
  process.exit(1);
}

const apiBase = "https://api.cloudflare.com/client/v4";
async function cloudflare(path, method = "GET", body) {
  const response = await fetch(apiBase + path, {
    method,
    headers: {
      "Authorization": "Bearer " + apiToken,
      "Content-Type": "application/json"
    },
    ...(body ? { body: JSON.stringify(body) } : {})
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || payload.success === false) {
    const messages = (payload.errors || []).map((item) => item.message).filter(Boolean);
    const error = new Error(messages.join("; ") || `Cloudflare API trả HTTP ${response.status}`);
    error.status = response.status;
    throw error;
  }
  return payload.result;
}

async function ensureWorkersSubdomain() {
  const path = `/accounts/${encodeURIComponent(accountId)}/workers/subdomain`;
  try {
    const result = await cloudflare(path);
    if (result?.subdomain) return result.subdomain;
  } catch (error) {
    if (error.status !== 404) throw error;
  }

  // Chỉ khởi tạo workers.dev subdomain khi tài khoản chưa có.
  const created = await cloudflare(path, "PUT", { subdomain: "hendydichvumang" });
  if (!created?.subdomain) throw new Error("Cloudflare không trả về workers.dev subdomain.");
  return created.subdomain;
}

async function ensurePagesProject() {
  const path = `/accounts/${encodeURIComponent(accountId)}/pages/projects/${pagesProject}`;
  try {
    const existing = await cloudflare(path);
    if (existing?.name === pagesProject) return;
  } catch (error) {
    if (error.status !== 404) throw error;
  }
  await cloudflare(`/accounts/${encodeURIComponent(accountId)}/pages/projects`, "POST", {
    name: pagesProject,
    production_branch: "main"
  });
  console.log("Đã tạo Cloudflare Pages project: " + pagesProject);
}

try {
  const [subdomain] = await Promise.all([
    ensureWorkersSubdomain(),
    ensurePagesProject()
  ]);
  const workerApiUrl = `https://${workerName}.${subdomain}.workers.dev`;
  const pagesUrl = `https://${pagesProject}.pages.dev`;

  const githubEnv = process.env.GITHUB_ENV;
  if (githubEnv) {
    const { appendFileSync } = await import("node:fs");
    appendFileSync(githubEnv, `WORKER_API_URL=${workerApiUrl}\nMINI_APP_URL=${pagesUrl}\n`);
  }
  console.log("Worker API URL đã được xác định tự động: " + workerApiUrl);
  console.log("Mini App URL: " + pagesUrl);
} catch (error) {
  console.error("Không thể tự động khởi tạo Cloudflare: " + error.message);
  console.error("Kiểm tra API Token có quyền Workers Scripts Read/Write và Pages Read/Write.");
  process.exit(1);
}
