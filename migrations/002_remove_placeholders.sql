-- Remove only the four exact placeholder entries from the initial starter release.
-- This migration runs once; later admin-created services with the same codes are preserved.
DELETE FROM service_catalog
WHERE (code = 'tool-live' AND name = 'Thuê Tool Live')
   OR (code = 'proxy' AND name = 'Proxy')
   OR (code = 'vip' AND name = 'Kho VIP')
   OR (code = 'source-code' AND name = 'Mua Code');
