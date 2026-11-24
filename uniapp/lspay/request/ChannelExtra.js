const V = require('./validators');

class JsonBuilder {
  constructor() { this.values = {}; }
  set(key, value) {
    key = String(key || '').trim();
    if (!key) V.fail('channel_extra 参数名不能为空');
    if (value == null || value === '') delete this.values[key];
    else this.values[key] = value && typeof value.toArray === 'function' ? value.toArray() : value;
    return this;
  }
  toArray() { return Object.assign({}, this.values); }
  toJson() { return JSON.stringify(this.toArray()); }
}

class DivisionReceiver extends JsonBuilder {
  constructor(transIn) { super(); if (transIn != null) this.setTransIn(transIn); }
  setTransIn(v) { return this.set('trans_in', V.required('div_list.trans_in', v)); }
  setDivAmt(v) { if (v == null || v === '') return this.set('div_amt', null); return this.set('div_amt', V.integer('div_list.div_amt', v, true)); }
  setDescription(v) { return this.set('description', v); }
  toArray() { V.required('div_list.trans_in', this.values.trans_in); return super.toArray(); }
}

class DivisionChannelExtra extends JsonBuilder {
  setDivisionReceivers(items) {
    if (!Array.isArray(items)) V.fail('channel_extra.div_list 必须是数组');
    return this.set('div_list', items.map(v => v && typeof v.toArray === 'function' ? v.toArray() : v));
  }
  addDivisionReceiver(item) {
    const list = this.values.div_list || [];
    list.push(item && typeof item.toArray === 'function' ? item.toArray() : item);
    return this.set('div_list', list);
  }
}

class UnifiedOrderChannelExtra extends DivisionChannelExtra {
  setBuyerId(v) { return this.set('buyer_id', v); }
  setOpenid(v) { return this.set('openid', v); }
  setAuthCode(v) { return this.set('auth_code', v); }
  setChannelUser(v) { return this.set('channel_user', v); }
  setChannelCardId(v) { return this.set('channel_card_id', v); }
  setPayDataType(v) {
    V.oneOf('channel_extra.pay_data_type', v, ['cashier', 'pay_url', 'wxapp', 'aliapp', 'ysfapp', 'code_url', 'code_img_url', 'none']);
    return this.set('pay_data_type', v);
  }
}
class BalancePayChannelExtra extends DivisionChannelExtra {
  setTransOut(v) { return this.set('trans_out', V.required('channel_extra.trans_out', v)); }
}

function setters(Cls, map) {
  Object.keys(map).forEach(method => {
    Cls.prototype[method] = function (value) { return this.set(map[method], value); };
  });
}
function requiredBuilder(requiredFields, validate) {
  return class extends JsonBuilder {
    toArray() {
      requiredFields.forEach(f => V.required(`channel_extra.${f}`, this.values[f]));
      if (validate) validate(this.values);
      return super.toArray();
    }
  };
}

class QuickPayBindChannelExtra extends requiredBuilder(
  ['mch_user_id', 'card_id', 'card_name', 'cert_id', 'cert_validity_type', 'cert_begin_date', 'card_mp', 'dc_type'],
  v => {
    V.oneOf('channel_extra.cert_validity_type', v.cert_validity_type, ['0', '1']);
    V.oneOf('channel_extra.dc_type', String(v.dc_type).toUpperCase(), ['C', 'D', 'S', 'U']);
    V.date8('channel_extra.cert_begin_date', v.cert_begin_date);
    if (v.cert_validity_type === '0') V.date8('channel_extra.cert_end_date', v.cert_end_date);
    if (v.expiration && !/^(0[1-9]|1[0-2])\d{2}$/.test(String(v.expiration))) V.fail('channel_extra.expiration 必须是 MMYY 格式');
  }
) { constructor() { super(); this.values.cert_type = '00'; } }
setters(QuickPayBindChannelExtra, {
  setMchUserId: 'mch_user_id', setCertType: 'cert_type', setCardId: 'card_id',
  setCardName: 'card_name', setCertId: 'cert_id', setCertValidityType: 'cert_validity_type',
  setCertBeginDate: 'cert_begin_date', setCertEndDate: 'cert_end_date', setCardMp: 'card_mp',
  setVipCode: 'vip_code', setExpiration: 'expiration', setDcType: 'dc_type',
});

class QuickPayBindConfirmChannelExtra extends requiredBuilder(
  ['mch_user_id', 'card_id', 'card_name', 'cert_id', 'card_mp', 'dc_type'],
  v => V.oneOf('channel_extra.dc_type', String(v.dc_type).toUpperCase(), ['C', 'D', 'S', 'U'])
) { constructor() { super(); this.values.cert_type = '00'; } }
setters(QuickPayBindConfirmChannelExtra, {
  setMchUserId: 'mch_user_id', setCertType: 'cert_type', setCardId: 'card_id',
  setCardName: 'card_name', setCertId: 'cert_id', setCardMp: 'card_mp', setDcType: 'dc_type',
});

class QuickPayUnbindChannelExtra extends requiredBuilder(['mch_user_id', 'channel_card_id']) {}
setters(QuickPayUnbindChannelExtra, { setMchUserId: 'mch_user_id', setChannelCardId: 'channel_card_id' });

class ElectronicAccountUserInfo extends requiredBuilder(
  ['cert_type', 'name', 'cert_no', 'cert_validity_type', 'cert_begin_date', 'mobile_no'],
  v => { V.date8('user_info.cert_begin_date', v.cert_begin_date); if (String(v.cert_validity_type) === '0') V.date8('user_info.cert_end_date', v.cert_end_date); }
) {}
setters(ElectronicAccountUserInfo, {
  setCertType: 'cert_type', setName: 'name', setCertNo: 'cert_no', setCertValidityType: 'cert_validity_type',
  setCertBeginDate: 'cert_begin_date', setCertEndDate: 'cert_end_date', setMobileNo: 'mobile_no', setEmail: 'email',
});

class ElectronicAccountMerchantInfo extends requiredBuilder([
  'reg_name', 'short_name', 'license_code', 'license_validity_type', 'license_begin_date',
  'reg_prov_id', 'reg_area_id', 'reg_district_id', 'reg_detail', 'legal_name',
  'legal_cert_type', 'legal_cert_no', 'legal_cert_validity_type', 'legal_cert_begin_date',
  'contact_name', 'contact_mobile',
]) {}
setters(ElectronicAccountMerchantInfo, {
  setRegName: 'reg_name', setShortName: 'short_name', setLicenseCode: 'license_code',
  setLicenseValidityType: 'license_validity_type', setLicenseBeginDate: 'license_begin_date',
  setLicenseEndDate: 'license_end_date', setRegProvId: 'reg_prov_id', setRegAreaId: 'reg_area_id',
  setRegDistrictId: 'reg_district_id', setRegDetail: 'reg_detail', setLegalName: 'legal_name',
  setLegalCertType: 'legal_cert_type', setLegalCertNo: 'legal_cert_no',
  setLegalCertValidityType: 'legal_cert_validity_type', setLegalCertBeginDate: 'legal_cert_begin_date',
  setLegalCertEndDate: 'legal_cert_end_date', setContactName: 'contact_name', setContactMobile: 'contact_mobile',
});

class ElectronicAccountOpenChannelExtra extends JsonBuilder {
  setUserInfo(v) { this.values = { user_info: v.toArray() }; return this; }
  setMerchantInfo(v) { this.values = { mer_info: v.toArray() }; return this; }
  toArray() { if (!this.values.user_info && !this.values.mer_info) V.fail('channel_extra 必须设置 user_info 或 mer_info'); return super.toArray(); }
}
class ElectronicAccountSettlementCard extends requiredBuilder(['card_type', 'card_name', 'card_no']) {}
setters(ElectronicAccountSettlementCard, {
  setCardType: 'card_type', setCardName: 'card_name', setCardNo: 'card_no', setProvId: 'prov_id',
  setAreaId: 'area_id', setBankCode: 'bank_code', setBranchCode: 'branch_code', setBranchName: 'branch_name',
  setCertType: 'cert_type', setCertNo: 'cert_no', setCertValidityType: 'cert_validity_type',
  setCertBeginDate: 'cert_begin_date', setCertEndDate: 'cert_end_date', setMobile: 'mp',
});
class ElectronicAccountModifiedCard extends requiredBuilder(['card_type', 'card_name', 'card_no']) {}
setters(ElectronicAccountModifiedCard, { setCardType: 'card_type', setCardName: 'card_name', setCardNo: 'card_no' });
class ElectronicAccountSettleConfig extends requiredBuilder(['settle_cycle', 'settle_status'], v => V.oneOf('settle_cycle', v.settle_cycle, ['T1', 'D1'])) {}
setters(ElectronicAccountSettleConfig, { setSettleCycle: 'settle_cycle', setSettleStatus: 'settle_status' });
class ElectronicAccountCashConfig extends requiredBuilder(['cash_type', 'switch_state', 'out_fee_flag'], v => {
  V.oneOf('cash_type', v.cash_type, ['D0', 'D1', 'DM']);
  if (![1, 2].includes(Number(v.switch_state)) || ![1, 2].includes(Number(v.out_fee_flag))) V.fail('cash_config 状态只允许 1 或 2');
}) {}
setters(ElectronicAccountCashConfig, { setCashType: 'cash_type', setSwitchState: 'switch_state', setOutFeeFlag: 'out_fee_flag' });

class ElectronicAccountBusinessChannelExtra extends JsonBuilder {
  setCardInfo(v) { return this.set('card_info', v.toArray()); }
  setSettleConfig(v) { return this.set('settle_config', v.toArray()); }
  addCashConfig(v) { const a = this.values.cash_config || []; a.push(v.toArray()); return this.set('cash_config', a); }
  toArray() {
    if (!this.values.card_info || !this.values.settle_config || !Array.isArray(this.values.cash_config) || !this.values.cash_config.length) {
      V.fail('channel_extra card_info、settle_config、cash_config 必填');
    }
    return super.toArray();
  }
}
class ElectronicAccountBusinessChangeChannelExtra extends ElectronicAccountBusinessChannelExtra {}

module.exports = {
  JsonBuilder, DivisionReceiver, DivisionChannelExtra, UnifiedOrderChannelExtra, BalancePayChannelExtra,
  QuickPayBindChannelExtra, QuickPayBindConfirmChannelExtra, QuickPayUnbindChannelExtra,
  ElectronicAccountUserInfo, ElectronicAccountMerchantInfo, ElectronicAccountOpenChannelExtra,
  ElectronicAccountSettlementCard, ElectronicAccountModifiedCard, ElectronicAccountSettleConfig,
  ElectronicAccountCashConfig, ElectronicAccountBusinessChannelExtra, ElectronicAccountBusinessChangeChannelExtra,
};
