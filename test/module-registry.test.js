import test from 'node:test';
import assert from 'node:assert/strict';
import { validateArtifact, MODULE_ARTIFACT_MAX_BYTES } from '../src/module-registry.js';

test('accepts valid UTF-8 JSON artifact and calculates checksum', () => {
  const content = Buffer.from(JSON.stringify({ version: 1, enabled: true }));
  const artifact = validateArtifact({
    moduleKey: 'customer-center',
    fileName: 'settings.json',
    mimeType: 'application/json',
    content
  });
  assert.equal(artifact.sizeBytes, content.length);
  assert.match(artifact.sha256, /^[a-f0-9]{64}$/);
  assert.equal(artifact.content, content);
});

test('rejects executable and unsupported file types', () => {
  for (const fileName of ['payload.php', 'payload.js', 'page.html', 'archive.zip', 'image.svg']) {
    assert.throws(() => validateArtifact({
      moduleKey: 'safe-module',
      fileName,
      mimeType: 'text/plain',
      content: Buffer.from('not allowed')
    }));
  }
});

test('rejects path traversal, invalid module names and mismatched MIME', () => {
  const base = { moduleKey: 'safe-module', fileName: 'notes.txt', mimeType: 'text/plain', content: Buffer.from('hello') };
  assert.throws(() => validateArtifact({ ...base, fileName: '../notes.txt' }));
  assert.throws(() => validateArtifact({ ...base, moduleKey: '../admin' }));
  assert.throws(() => validateArtifact({ ...base, mimeType: 'text/html' }));
});

test('rejects invalid JSON and non UTF-8 payloads', () => {
  assert.throws(() => validateArtifact({
    moduleKey: 'safe-module', fileName: 'broken.json', mimeType: 'application/json', content: Buffer.from('{')
  }));
  assert.throws(() => validateArtifact({
    moduleKey: 'safe-module', fileName: 'bad.txt', mimeType: 'text/plain', content: Buffer.from([0xff, 0xfe])
  }));
});

test('enforces a one-megabyte payload cap', () => {
  assert.equal(MODULE_ARTIFACT_MAX_BYTES, 1024 * 1024);
  assert.throws(() => validateArtifact({
    moduleKey: 'safe-module', fileName: 'large.txt', mimeType: 'text/plain',
    content: Buffer.alloc(MODULE_ARTIFACT_MAX_BYTES + 1, 97)
  }));
});
