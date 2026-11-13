import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const files = [
  'website/telegram/admin-dashboard/index.html',
  'website/telegram/admin-dashboard/legacy-login.html',
  'website/telegram/customer-center/index.html',
  'website/public/index.html',
];

let checked = 0;
const failures = [];
for (const file of files) {
  const html = readFileSync(file, 'utf8');
  const scripts = [...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi)];
  for (let index = 0; index < scripts.length; index += 1) {
    const attributes = scripts[index][1] || '';
    const source = scripts[index][2] || '';
    if (/\bsrc\s*=|type\s*=\s*["']application\/(?:json|ld\+json)["']/i.test(attributes)) continue;
    if (/type\s*=\s*["']module["']/i.test(attributes)) {
      failures.push(file + ': inline module script requires a dedicated module parser');
      continue;
    }
    if (!source.trim()) continue;
    checked += 1;
    try {
      new vm.Script(source, { filename: file + '#inline-script-' + (index + 1) });
    } catch (error) {
      failures.push(file + '#inline-script-' + (index + 1) + ': ' + (error.stack || error.message));
    }
  }
}
if (checked === 0) failures.push('No inline browser scripts were checked.');
if (failures.length) {
  for (const failure of failures) process.stderr.write('FAIL ' + failure + '\n');
  process.exit(1);
}
process.stdout.write('Đã kiểm tra cú pháp ' + checked + ' inline browser script(s) trên ' + files.length + ' trang.\n');
