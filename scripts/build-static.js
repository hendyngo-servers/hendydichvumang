import fs from 'node:fs/promises';
import path from 'node:path';

const apiBase = String(process.env.STATIC_API_BASE_URL || '').trim().replace(/\/+$/, '');
if (!apiBase) {
  throw new Error('Thiếu STATIC_API_BASE_URL. Hãy liên kết Static Site đến URL HTTPS của API Web Service.');
}
let parsed;
try { parsed = new URL(apiBase); } catch { throw new Error('STATIC_API_BASE_URL không hợp lệ.'); }
if (parsed.protocol !== 'https:') throw new Error('STATIC_API_BASE_URL phải sử dụng HTTPS.');

const source = path.resolve('public');
const output = path.resolve('dist-site');
await fs.rm(output, { recursive: true, force: true });
await fs.cp(source, output, { recursive: true });
const websiteIndex = path.resolve('apps/website/index.html');
await fs.access(websiteIndex);
await fs.copyFile(websiteIndex, path.join(output, 'index.html'));
const config = 'window.HENDY_CONFIG = Object.freeze(' +
  JSON.stringify({ apiBaseUrl: parsed.origin }) + ');\n';
await fs.writeFile(path.join(output, 'config.js'), config, 'utf8');
console.log('Static Site đã build. API endpoint:', parsed.origin);
