const LsPayResponse = require('../core/LsPayResponse');

function getters(Cls, map) {
  Object.keys(map).forEach(method => {
    const spec = map[method];
    Cls.prototype[method] = function () {
      return spec[1] === 'int' ? this.integerValue(spec[0]) : this.stringValue(spec[0]);
    };
  });
}
class UnifiedOrderResponse extends LsPayResponse {
  isAccepted() { return !this.isError() && (!this.getTransStatus() || this.getTransStatus() === 'S'); }
  isPaid() { return !this.isError() && this.getOrderState() === 2; }
}
getters(UnifiedOrderResponse, {
  getPayOrderId: ['pay_order_id'], getMchOrderNo: ['mch_order_no'], getAmount: ['amount', 'int'],
  getOrderState: ['order_state', 'int'], getPayInfo: ['pay_info'], getTransStatus: ['trans_status'],
  getErrCode: ['err_code'], getErrMsg: ['err_msg'],
});
class QueryOrderResponse extends UnifiedOrderResponse {}
getters(QueryOrderResponse, {
  getMchNo: ['mch_no'], getAppId: ['app_id'], getWayCode: ['way_code'], getCurrency: ['currency'],
  getClientIp: ['client_ip'], getSubject: ['subject'], getBody: ['body'], getChannelOrderNo: ['channel_order_no'],
  getExtParam: ['ext_param'], getAddTime: ['add_time', 'int'], getSuccessTime: ['success_time', 'int'], // 10 位秒级时间戳
  getDivisionMode: ['division_mode', 'int'], getDivisionState: ['division_state', 'int'],
  getUnconfirmAmount: ['unconfirm_amount', 'int'],
});
class RefundResponse extends LsPayResponse {
  getRefundOrderId() { return this.stringValue('refund_order_id') || this.stringValue('order_refund_id') || this.stringValue('order_refund_Id'); }
  getOrderRefundId() { return this.getRefundOrderId(); }
  getChannelOrderNo() { return this.stringValue('channel_order_no') || this.stringValue('channel_rrder_no'); }
  isAccepted() { return !this.isError(); }
  isRefunded() { return !this.isError() && this.getState() === 2; }
}
getters(RefundResponse, {
  getMchRefundNo: ['mch_refund_no'], getErrCode: ['err_code'],
  getErrMsg: ['err_msg'], getState: ['state', 'int'], getPayAmount: ['pay_amount', 'int'],
  getRefundAmount: ['refund_amount', 'int'],
});
class QueryRefundResponse extends RefundResponse {}
getters(QueryRefundResponse, {
  getPayOrderId: ['pay_order_id'], getMchNo: ['mch_no'], getAppId: ['app_id'], getCurrency: ['currency'],
  getExtParam: ['ext_param'], getAddTime: ['add_time', 'int'], getSuccessTime: ['success_time', 'int'], // 10 位秒级时间戳
});
class BalancePayResponse extends UnifiedOrderResponse {}
class QuickPayApplyResponse extends UnifiedOrderResponse {
  isProcessing() { return !this.isError() && this.getTransStatus() === 'P'; }
}
class QuickPayResponse extends LsPayResponse {
  getTransStatus() { return this.stringValue('trans_status'); }
  isError() {
    const raw = this.getMap();
    return ('status' in raw || 'code' in raw || 'retCode' in raw)
      ? super.isError() : (!this.getTransStatus() || this.getTransStatus() === 'F');
  }
  isAccepted() { return !this.isError(); }
  isSuccessful() { return !this.isError() && this.getTransStatus() === 'S'; }
  isProcessing() { return !this.isError() && this.getTransStatus() === 'P'; }
}
class QuickPayBindApplyResponse extends QuickPayResponse {}
getters(QuickPayBindApplyResponse, { getTransId: ['trans_id'], getOrderId: ['order_id'], getRespDesc: ['resp_desc'] });
class QuickPayBindConfirmResponse extends QuickPayResponse {}
getters(QuickPayBindConfirmResponse, { getChannelUser: ['channel_user'], getChannelCardId: ['channel_card_id'], getRespDesc: ['resp_desc'] });
class QuickPayConfirmResponse extends QuickPayResponse {}
getters(QuickPayConfirmResponse, { getErrMsg: ['err_msg'] });
class QuickPayUnbindResponse extends QuickPayResponse {}
getters(QuickPayUnbindResponse, { getOrderId: ['order_id'], getRespDesc: ['resp_desc'] });
class QuickPaySmsCheckResponse extends QuickPayConfirmResponse {}

class DelayTransConfirmResponse extends LsPayResponse {
  isAccepted() { return !this.isError(); }
  isSuccessful() { return !this.isError() && this.getTransStatus() === 'S'; }
}
getters(DelayTransConfirmResponse, {
  getUnconfirmAmount: ['unconfirm_amount', 'int'], getConfirmedAmount: ['confirmed_amount', 'int'],
  getConfirmOrderId: ['confirm_order_id'], getBatchOrderId: ['batch_order_id'],
  getTransStatus: ['trans_status'], getErrCode: ['err_code'], getErrMsg: ['err_msg'],
});
class DelayTransConfirmRefundResponse extends DelayTransConfirmResponse {}
getters(DelayTransConfirmRefundResponse, { getConfirmRefundNo: ['confirm_refund_no'] });
class DelayTransUnconfirmQueryResponse extends LsPayResponse {
  getUnconfirmAmount() { return this.integerValue('unconfirm_amount'); }
  isSuccessful() { return !this.isError(); }
}
class DelayTransCreateTaskResponse extends LsPayResponse {
  isCreated() { return !this.isError() && this.getTaskStatus() === 'PENDING'; }
}
getters(DelayTransCreateTaskResponse, {
  getTaskId: ['task_id'], getOrderNo: ['order_no'], getAccountNo: ['account_no'], getAccountName: ['account_name'],
  getAmount: ['amount', 'int'], getTaskStatus: ['status'], getSelectedOrders: ['selected_orders', 'int'],
  getFirstOrderTime: ['first_order_time'], getLastOrderTime: ['last_order_time'],
});

class ElectronicAccountResponse extends LsPayResponse {
  getTransStatus() { return this.stringValue('trans_status'); }
  isError() {
    const raw = this.getMap();
    return ('status' in raw || 'code' in raw || 'retCode' in raw)
      ? super.isError() : (!this.getTransStatus() || this.getTransStatus() === 'F');
  }
  isSuccessful() { return !this.isError() && this.getTransStatus() === 'S'; }
  isProcessing() { return !this.isError() && this.getTransStatus() === 'P'; }
}
class ElectronicAccountUserOpenResponse extends ElectronicAccountResponse {}
class ElectronicAccountBusinessOpenResponse extends ElectronicAccountResponse {}
class ElectronicAccountBusinessChangeResponse extends ElectronicAccountResponse {}
[ElectronicAccountUserOpenResponse, ElectronicAccountBusinessOpenResponse, ElectronicAccountBusinessChangeResponse]
  .forEach(C => getters(C, { getAccountNo: ['account_no'], getRespDesc: ['resp_desc'] }));
class ElectronicAccountUserQueryResponse extends ElectronicAccountResponse {
  getChannelExtra() { const v = this.dataValue('channel_extra'); return v == null ? null : (typeof v === 'string' ? v : JSON.stringify(v)); }
  getChannelExtraArray() {
    const v = this.dataValue('channel_extra');
    if (v && typeof v === 'object') return v;
    try { return v ? JSON.parse(v) : {}; } catch (_) { return {}; }
  }
  isOnboarded() { return this.isSuccessful() && this.getChannelStatus() === '1'; } // 资料中的入驻状态，开户成功看开户接口 trans_status
}
getters(ElectronicAccountUserQueryResponse, {
  getAccountNo: ['account_no'], getAccountName: ['account_name'], getAccountType: ['account_type'],
  getRelationType: ['relation_type'], getRelationTypeName: ['relation_type_name'], getDivisionProfit: ['division_profit'],
  getChannelStatus: ['channel_status'], getRespDesc: ['resp_desc'],
});
class ElectronicAccountSettlementQueryResponse extends LsPayResponse {
  getRecords() {
    const d = this.getData();
    if (Array.isArray(d)) return d;
    for (const key of ['records', 'list', 'rows']) if (Array.isArray(d[key])) return d[key];
    return d && d.trans_id ? [d] : [];
  }
  getFirstRecord() { return this.getRecords()[0] || {}; }
  recordValue(key) { const v = this.getFirstRecord()[key]; return v == null ? null : String(v); }
}
[
  ['getTransId', 'trans_id'], ['getTransDate', 'trans_date'], ['getSettleCycle', 'settle_cycle'],
  ['getCardNo', 'card_no'], ['getCardName', 'card_name'], ['getBankCode', 'bank_code'],
  ['getTransAmount', 'trans_amt'], ['getFeeAmount', 'fee_amt'], ['getTransStatus', 'trans_stat'],
  ['getSettleType', 'settle_type'], ['getSettleAbstract', 'settle_abstract'],
  ['getSettleBatchNo', 'settle_batch_no'], ['getSettleDescription', 'settle_desc'],
].forEach(([method, field]) => { ElectronicAccountSettlementQueryResponse.prototype[method] = function () { return this.recordValue(field); }; });
class ElectronicAccountBalanceWithdrawResponse extends LsPayResponse {
  getAcceptanceMessage() { return this.stringValue('resp_desc') || this.stringValue('err_msg'); }
  getAcceptanceStatus() { return this.stringValue('trans_status') || this.stringValue('status'); }
  isAccepted() {
    return !this.isError() && ['success', 'succeess'].includes(
      String(this.getAcceptanceStatus() || '').toLowerCase(),
    );
  }
}
class ElectronicAccountSingleWithdrawQueryResponse extends LsPayResponse {
  isPending() { return this.getTransStatus() === 'pending'; }
  isFailed() { return this.getTransStatus() === 'failed'; }
  isSucceeded() { return this.getTransStatus() === 'succeeded'; }
  isChannelSuccessful() { return ['success', 'succeess'].includes(String(this.getChannelStatus() || '').toLowerCase()); }
}
getters(ElectronicAccountSingleWithdrawQueryResponse, {
  getWithdrawDate: ['withdraw_date'], getOrderId: ['order_id'], getCashAmount: ['cash_amt'],
  getFeeAmount: ['fee_amt'], getTransStatus: ['trans_status'], getTransDescription: ['trans_desc'],
  getChannelStatus: ['status'], getChannelMessage: ['err_msg'],
});
class ElectronicAccountBatchWithdrawQueryResponse extends LsPayResponse {
  getRecords() { const d = this.getData(); return d && Array.isArray(d.data) ? d.data : []; }
  getFirstRecord() { return this.getRecords()[0] || {}; }
  recordValue(key) { const v = this.getFirstRecord()[key]; return v == null ? null : String(v); }
  isFirstRecordPending() { return !this.isError() && this.getTransStatus() === 'pending'; }
  isFirstRecordFailed() { return !this.isError() && this.getTransStatus() === 'failed'; }
  isFirstRecordSucceeded() { return !this.isError() && this.getTransStatus() === 'succeeded'; }
  isChannelSuccessful() { return ['success', 'succeess'].includes(String(this.getChannelStatus() || '').toLowerCase()); }
}
getters(ElectronicAccountBatchWithdrawQueryResponse, {
  getCount: ['count', 'int'], getPageSize: ['page_size', 'int'], getPageNum: ['page_num', 'int'], getChannelStatus: ['status'],
});
[
  ['getSettleDate', 'settle_date'], ['getCardNo', 'card_no'], ['getCardName', 'card_name'],
  ['getBankName', 'bank_name'], ['getBatchTransType', 'batch_trans_type'], ['getCashAmount', 'cash_amt'],
  ['getFeeAmount', 'fee_amt'], ['getFeeFlag', 'fee_flag'], ['getOriginalRequestDate', 'org_req_date'],
  ['getOrderId', 'order_id'], ['getTransStatus', 'trans_status'], ['getRemark', 'remark'],
  ['getTransDescription', 'trans_desc'],
].forEach(([method, field]) => { ElectronicAccountBatchWithdrawQueryResponse.prototype[method] = function () { return this.recordValue(field); }; });
class BalanceQueryResponse extends LsPayResponse {
  getData() {
    const data = super.getData();
    return Object.keys(data).length ? data : (this.getMap().acct_type != null ? this.getMap() : data);
  }
  isError() { return this.getMap().status == null && this.getMap().acct_type != null ? false : super.isError(); }
  isNormal() { return !this.isError() && this.getAccountStatus() === 'N'; }
}
getters(BalanceQueryResponse, {
  getAccountType: ['acct_type'], getAccountStatus: ['acct_stat'], getBalanceAmount: ['balance_amt'],
  getAvailableBalance: ['avl_balance'], getFrozenBalance: ['frz_bal'], getHistoricalBalance: ['dm_amt'],
});

module.exports = {
  LsPayResponse, UnifiedOrderResponse, QueryOrderResponse, RefundResponse, QueryRefundResponse, BalancePayResponse,
  QuickPayApplyResponse, QuickPayResponse, QuickPayBindApplyResponse, QuickPayBindConfirmResponse,
  QuickPayConfirmResponse, QuickPayUnbindResponse, QuickPaySmsCheckResponse,
  DelayTransConfirmResponse, DelayTransConfirmRefundResponse, DelayTransUnconfirmQueryResponse, DelayTransCreateTaskResponse,
  ElectronicAccountResponse, ElectronicAccountUserOpenResponse, ElectronicAccountBusinessOpenResponse,
  ElectronicAccountBusinessChangeResponse, ElectronicAccountUserQueryResponse, ElectronicAccountSettlementQueryResponse,
  ElectronicAccountBalanceWithdrawResponse, ElectronicAccountSingleWithdrawQueryResponse,
  ElectronicAccountBatchWithdrawQueryResponse, BalanceQueryResponse,
};
