import { createHash, createHmac, timingSafeEqual } from 'node:crypto';

const BUCKET = 'uploads';
const MAX_EXPIRES_SECONDS = 900;
const ALLOWED_CONTENT_TYPES = new Set([
  'application/json', 'text/plain', 'text/css', 'text/html',
  'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp',
]);
const json = (body, status = 200) => new Response(JSON.stringify(body), {
  status,
  headers: { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' },
});
const hmac = (key, value, encoding) => createHmac('sha256', key).update(value).digest(encoding);
const sha256Hex = (value) => createHash('sha256').update(value).digest('hex');
const rfc3986 = (value) => encodeURIComponent(String(value)).replace(/[!'()*]/g, (char) => '%' + char.charCodeAt(0).toString(16).toUpperCase());

function canonicalQuery(entries) {
  return entries.map(([key, value]) => [rfc3986(key), rfc3986(value)])
    .sort(([aKey, aValue], [bKey, bValue]) => aKey.localeCompare(bKey) || aValue.localeCompare(bValue))
    .map(([key, value]) => key + '=' + value).join('&');
}

export function buildPresignedUrl({ method, key, contentType = '', expiresIn = 300, env = process.env, now = new Date() }) {
  if (!['GET', 'PUT'].includes(method)) throw new Error('Unsupported operation');
  if (typeof key !== 'string' || !/^modules\/[a-z][a-z0-9-]{1,62}\/[A-Za-z0-9_-]{16,64}\.(json|md|txt|css|html|svg|png|jpg|jpeg|webp)$/.test(key)) throw new Error('Invalid object key');
  if (method === 'PUT' && !ALLOWED_CONTENT_TYPES.has(contentType)) throw new Error('Unsupported content type');

  const endpoint = env.AWS_ENDPOINT_URL_S3;
  const accessKey = env.AWS_ACCESS_KEY_ID;
  const secretKey = env.AWS_SECRET_ACCESS_KEY;
  const region = env.AWS_REGION;
  if (![endpoint, accessKey, secretKey, region].every((value) => typeof value === 'string' && value.length > 0)) throw new Error('Object storage is not configured');

  const expires = Math.max(1, Math.min(MAX_EXPIRES_SECONDS, Number(expiresIn) || 300));
  const url = new URL(endpoint);
  if (url.protocol !== 'https:' || !url.hostname.includes('.storage.') || !url.hostname.endsWith('.neon.tech')) throw new Error('Invalid Neon storage endpoint');
  const objectPath = BUCKET + '/' + key.split('/').map(rfc3986).join('/');
  url.pathname = url.pathname.replace(/\/$/, '') + '/' + objectPath;

  const amzDate = now.toISOString().replace(/[:-]|\.\d{3}/g, '');
  const dateStamp = amzDate.slice(0, 8);
  const scope = dateStamp + '/' + region + '/s3/aws4_request';
  const signedHeaders = method === 'PUT' ? 'content-type;host' : 'host';
  const params = [
    ['X-Amz-Algorithm', 'AWS4-HMAC-SHA256'],
    ['X-Amz-Credential', accessKey + '/' + scope],
    ['X-Amz-Date', amzDate],
    ['X-Amz-Expires', String(expires)],
    ['X-Amz-SignedHeaders', signedHeaders],
  ];
  const query = canonicalQuery(params);
  const canonicalHeaders = method === 'PUT'
    ? 'content-type:' + contentType.trim() + '\nhost:' + url.host + '\n'
    : 'host:' + url.host + '\n';
  const canonicalRequest = [method, url.pathname, query, canonicalHeaders, signedHeaders, 'UNSIGNED-PAYLOAD'].join('\n');
  const stringToSign = ['AWS4-HMAC-SHA256', amzDate, scope, sha256Hex(canonicalRequest)].join('\n');
  const dateKey = hmac('AWS4' + secretKey, dateStamp);
  const regionKey = hmac(dateKey, region);
  const serviceKey = hmac(regionKey, 's3');
  const signingKey = hmac(serviceKey, 'aws4_request');
  const signature = hmac(signingKey, stringToSign, 'hex');
  return {
    url: url.origin + url.pathname + '?' + query + '&X-Amz-Signature=' + signature,
    method,
    headers: method === 'PUT' ? { 'Content-Type': contentType } : {},
    expiresIn: expires,
  };
}

function authorized(request, secret) {
  const supplied = request.headers.get('authorization') || '';
  const expected = 'Bearer ' + secret;
  const left = Buffer.from(supplied);
  const right = Buffer.from(expected);
  return left.length === right.length && timingSafeEqual(left, right);
}

export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (request.method === 'GET' && url.pathname.endsWith('/healthz')) {
      try {
        buildPresignedUrl({
          method: 'GET',
          key: 'modules/health/0123456789abcdef0123456789abcdef.txt',
          expiresIn: 60,
        });
        return json({ status: 'ok', service: 'hendy-neon-storage', storage: 'ready' });
      } catch {
        return json({ status: 'degraded', service: 'hendy-neon-storage', storage: 'unavailable' }, 503);
      }
    }
    if (request.method !== 'POST' || !url.pathname.endsWith('/presign')) return json({ error: 'Not found' }, 404);
    const apiKey = process.env.HENDY_FUNCTION_API_KEY || '';
    if (!apiKey || !authorized(request, apiKey)) return json({ error: 'Unauthorized' }, 401);
    if (Number(request.headers.get('content-length') || 0) > 4096) return json({ error: 'Payload too large' }, 413);

    try {
      const input = await request.json();
      const action = input && input.action;
      const method = action === 'upload' ? 'PUT' : action === 'download' ? 'GET' : '';
      if (!method) return json({ error: 'Unsupported action' }, 400);
      return json(buildPresignedUrl({
        method, key: input.key,
        contentType: method === 'PUT' ? String(input.contentType || '') : '',
        expiresIn: input.expiresIn,
      }));
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Invalid request';
      const clientError = /invalid|unsupported/i.test(message);
      return json({ error: clientError ? message : 'Storage signing is unavailable' }, clientError ? 400 : 503);
    }
  },
};
