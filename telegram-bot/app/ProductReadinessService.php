<?php
declare(strict_types=1);

namespace Hendy;

use RuntimeException;

/** Fail-closed product policy. Provider credentials never belong in PostgreSQL. */
final class ProductReadinessService
{
    public static function sqlPredicate(string $alias = ''): string
    {
        if ($alias !== '' && !preg_match('/\\A[a-z][a-z0-9_]*\\z/i', $alias)) {
            throw new RuntimeException('SQL alias sản phẩm không hợp lệ.');
        }
        $p = $alias === '' ? '' : $alias . '.';
        return $p . "source_permission_status = 'authorized' AND "
            . $p . "supplier_access_status = 'verified' AND "
            . $p . "fulfillment_mode = 'manual' AND "
            . "NULLIF(BTRIM(" . $p . "supplier_name), '') IS NOT NULL AND "
            . "NULLIF(BTRIM(" . $p . "supplier_url), '') IS NOT NULL AND "
            . "NULLIF(BTRIM(" . $p . "source_url), '') IS NOT NULL";
    }

    public static function isSellable(array $product): bool
    {
        return self::truthy($product['is_active'] ?? false)
            && (string) ($product['source_permission_status'] ?? 'pending') === 'authorized'
            && (string) ($product['supplier_access_status'] ?? 'pending') === 'verified'
            && (string) ($product['fulfillment_mode'] ?? 'manual') === 'manual'
            && trim((string) ($product['supplier_name'] ?? '')) !== ''
            && trim((string) ($product['supplier_url'] ?? '')) !== ''
            && trim((string) ($product['source_url'] ?? '')) !== ''
            && (($product['stock'] ?? null) === null || (int) $product['stock'] > 0);
    }

    public static function adminFields(array $product): array
    {
        return [
            'supplierName' => (string) ($product['supplier_name'] ?? ''),
            'supplierUrl' => (string) ($product['supplier_url'] ?? ''),
            'supplierProductRef' => (string) ($product['supplier_product_ref'] ?? ''),
            'sourceUrl' => (string) ($product['source_url'] ?? ''),
            'sourcePermissionStatus' => (string) ($product['source_permission_status'] ?? 'pending'),
            'supplierAccessStatus' => (string) ($product['supplier_access_status'] ?? 'pending'),
            'fulfillmentMode' => (string) ($product['fulfillment_mode'] ?? 'manual'),
            'credentialEnvKey' => (string) ($product['credential_env_key'] ?? ''),
            'verifiedBy' => (string) ($product['verified_by'] ?? ''),
            'verifiedAt' => $product['verified_at'] ?? null,
            'isSellable' => self::isSellable($product),
            'readinessReasons' => self::reasons($product),
        ];
    }

    public static function reasons(array $product): array
    {
        $reasons = [];
        if (!self::truthy($product['is_active'] ?? false)) $reasons[] = 'Sản phẩm đang bị ẩn.';
        if ((string) ($product['source_permission_status'] ?? 'pending') !== 'authorized') $reasons[] = 'Chưa xác minh quyền phân phối nguồn.';
        if ((string) ($product['supplier_access_status'] ?? 'pending') !== 'verified') $reasons[] = 'Chưa xác minh quyền truy cập nhà cung cấp.';
        if (trim((string) ($product['supplier_name'] ?? '')) === '') $reasons[] = 'Thiếu tên nhà cung cấp.';
        if (trim((string) ($product['supplier_url'] ?? '')) === '') $reasons[] = 'Thiếu địa chỉ nhà cung cấp.';
        if (trim((string) ($product['source_url'] ?? '')) === '') $reasons[] = 'Thiếu URL nguồn / hồ sơ quyền sử dụng.';
        if ((string) ($product['fulfillment_mode'] ?? 'manual') !== 'manual') $reasons[] = 'Chưa có adapter API nhà cung cấp được kiểm thử; chế độ API vẫn khóa.';
        if (($product['stock'] ?? null) !== null && (int) $product['stock'] < 1) $reasons[] = 'Sản phẩm đã hết hàng.';
        return $reasons;
    }

    public static function validateEnvKey(string $key): string
    {
        $key = trim($key);
        if ($key !== '' && !preg_match('/\\A[A-Z][A-Z0-9_]{2,63}\\z/', $key)) {
            throw new RuntimeException('Tên biến Environment chỉ gồm chữ in hoa, số hoặc dấu gạch dưới; không nhập giá trị secret.');
        }
        return $key;
    }

    public static function validateHttpsUrl(string $url, string $fieldLabel): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException($fieldLabel . ' không phải URL hợp lệ.');
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || preg_match('/[\\x00-\\x20]/', $url)) {
            throw new RuntimeException($fieldLabel . ' phải là URL HTTPS và không chứa user/password.');
        }
        return $url;
    }

    private static function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
