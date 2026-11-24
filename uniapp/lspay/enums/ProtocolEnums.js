module.exports = Object.freeze({
  PayDataType: Object.freeze({
    CASHIER: 'cashier', PAY_URL: 'pay_url', WXAPP: 'wxapp', ALIAPP: 'aliapp',
    YSFAPP: 'ysfapp', CODE_URL: 'code_url', CODE_IMG_URL: 'code_img_url', NONE: 'none',
  }),
  OrderState: Object.freeze({ CREATED: 0, PAYING: 1, PAID: 2, FAILED: 3, REVOKED: 4, REFUNDED: 5, CLOSED: 6 }),
  RefundState: Object.freeze({ CREATED: 0, REFUNDING: 1, SUCCEEDED: 2, FAILED: 3, CLOSED: 4 }),
  QuickPayTransStatus: Object.freeze({ FAILED: 'F', PROCESSING: 'P', SUCCEEDED: 'S' }),
  ElectronicAccountTransStatus: Object.freeze({ FAILED: 'F', PROCESSING: 'P', SUCCEEDED: 'S' }),
  ElectronicAccountBalanceStatus: Object.freeze({ NORMAL: 'N', CLOSED: 'C', FROZEN: 'F', CANCELLED: 'D' }),
  ElectronicAccountWithdrawStatus: Object.freeze({ PENDING: 'pending', FAILED: 'failed', SUCCEEDED: 'succeeded' }),
  ElectronicAccountChannelStatus: Object.freeze({ PENDING: '0', SUCCEEDED: '1', FAILED: '2' }),
  ElectronicAccountType: Object.freeze({ PERSONAL: 1, ENTERPRISE: 2, INDIVIDUAL_BUSINESS: 3 }),
  ElectronicAccountRelationType: Object.freeze({
    SERVICE_PROVIDER: 'SERVICE_PROVIDER', STORE: 'STORE', STAFF: 'STAFF', STORE_OWNER: 'STORE_OWNER',
    PARTNER: 'PARTNER', HEADQUARTER: 'HEADQUARTER', BRAND: 'BRAND', DISTRIBUTOR: 'DISTRIBUTOR',
    USER: 'USER', SUPPLIER: 'SUPPLIER', CUSTOM: 'CUSTOM',
  }),
  DelayTransTaskStatus: Object.freeze({ PENDING: 'PENDING', PROCESSING: 'PROCESSING', COMPLETED: 'COMPLETED', FAILED: 'FAILED', PARTIAL: 'PARTIAL' }),
});
