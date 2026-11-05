export const COMMERCE_MODULES = Object.freeze([
  { code: 'payments', route: 'payments', label: 'Thanh toán', implementationReady: false, requiresReconciliation: true },
  { code: 'orders', route: 'orders', label: 'Đơn hàng', implementationReady: false, requiresReconciliation: true },
  { code: 'vip', route: 'vip', label: 'Thành viên VIP', implementationReady: false, requiresReconciliation: true },
  { code: 'vouchers', route: 'vouchers', label: 'Voucher', implementationReady: false, requiresReconciliation: true },
  { code: 'giftcodes', route: 'giftcodes', label: 'Giftcode', implementationReady: false, requiresReconciliation: true },
  { code: 'referrals', route: 'referrals', label: 'Giới thiệu bạn bè', implementationReady: false, requiresReconciliation: true },
  { code: 'video_jobs', route: 'video-jobs', label: 'Video Jobs', implementationReady: false, requiresReconciliation: true }
]);

export function summarizeCommerceFeature(feature, row = null) {
  const configured = row?.enabled === true;
  const backendReady = row?.backend_ready === true;
  const reconciliationVerified = row?.reconciliation_verified === true;
  const enabled = feature.implementationReady === true &&
    configured && backendReady &&
    (!feature.requiresReconciliation || reconciliationVerified);

  let reason = 'Mã nghiệp vụ Node.js chưa được port đầy đủ và kiểm thử production.';
  if (feature.implementationReady === true && !backendReady) {
    reason = 'Backend chưa qua kiểm thử production.';
  } else if (feature.implementationReady === true && feature.requiresReconciliation && !reconciliationVerified) {
    reason = 'Quy trình đối soát chưa được xác nhận.';
  } else if (feature.implementationReady === true && !configured) {
    reason = 'Module chưa được bật trong PostgreSQL.';
  } else if (enabled) {
    reason = 'Module đã sẵn sàng.';
  }

  return {
    code: feature.code,
    route: '/api/' + feature.route,
    label: feature.label,
    enabled,
    readiness: enabled ? 'READY' : 'LOCKED',
    backendReady,
    reconciliationVerified,
    reason
  };
}

export async function loadCommerceStatus(db) {
  const { rows } = await db.query(
    'SELECT code, enabled, backend_ready, reconciliation_verified FROM commerce_feature_flags'
  );
  const byCode = new Map(rows.map((row) => [row.code, row]));
  return COMMERCE_MODULES.map((feature) =>
    summarizeCommerceFeature(feature, byCode.get(feature.code) || null)
  );
}

export function createCommerceFeatureGate(db, code) {
  const feature = COMMERCE_MODULES.find((item) => item.code === code);
  if (!feature) throw new Error('Commerce feature không được đăng ký: ' + code);

  return async (_req, res, next) => {
    try {
      const { rows } = await db.query(
        'SELECT enabled, backend_ready, reconciliation_verified FROM commerce_feature_flags WHERE code = $1 LIMIT 1',
        [code]
      );
      const status = summarizeCommerceFeature(feature, rows[0] || null);
      if (!status.enabled) {
        return res.status(503).json({
          error: 'Chức năng đang được khóa để bảo đảm an toàn giao dịch.',
          feature: code,
          readiness: status.readiness,
          reason: status.reason
        });
      }
      return next();
    } catch (error) {
      return next(error);
    }
  };
}
