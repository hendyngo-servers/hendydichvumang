import crypto from 'node:crypto';
import { isUtf8 } from 'node:buffer';

const MAX_ARTIFACT_BYTES = 1024 * 1024;
const MODULE_KEY = /^[a-z][a-z0-9-]{1,62}$/;
const TYPES = Object.freeze({
  '.json': 'application/json',
  '.md': 'text/markdown',
  '.txt': 'text/plain',
  '.css': 'text/css'
});

export function validateArtifact({ moduleKey, fileName, mimeType, content }) {
  if (typeof moduleKey !== 'string' || !MODULE_KEY.test(moduleKey)) {
    throw new TypeError('Mã module chỉ gồm chữ thường, số và dấu gạch ngang.');
  }
  if (typeof fileName !== 'string' || fileName.length < 1 || fileName.length > 120 ||
      fileName !== fileName.split(/[\\/]/).pop() || /[\\x00-\\x1f\\x7f]/.test(fileName)) {
    throw new TypeError('Tên tệp không hợp lệ.');
  }
  const extension = fileName.slice(fileName.lastIndexOf('.')).toLowerCase();
  const expectedMime = TYPES[extension];
  if (!expectedMime || String(mimeType || '').split(';')[0].trim().toLowerCase() !== expectedMime) {
    throw new TypeError('Chỉ nhận JSON, Markdown, TXT và CSS với MIME type tương ứng.');
  }
  if (!Buffer.isBuffer(content) || content.length < 1 || content.length > MAX_ARTIFACT_BYTES) {
    throw new TypeError('Tệp phải có dung lượng từ 1 byte đến 1 MB.');
  }
  if (!isUtf8(content) || content.includes(0)) {
    throw new TypeError('Tệp phải là văn bản UTF-8 hợp lệ.');
  }
  const text = content.toString('utf8');
  if (extension === '.json') {
    try { JSON.parse(text); } catch { throw new TypeError('Tệp JSON không hợp lệ.'); }
  }
  return {
    moduleKey,
    fileName,
    mimeType: expectedMime,
    sizeBytes: content.length,
    sha256: crypto.createHash('sha256').update(content).digest('hex'),
    content
  };
}

export const MODULE_ARTIFACT_MAX_BYTES = MAX_ARTIFACT_BYTES;
