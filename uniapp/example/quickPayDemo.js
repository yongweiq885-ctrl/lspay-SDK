const {
  QuickPayBindApplyRequest, QuickPayBindChannelExtra,
  QuickPayConfirmRequest, QuickPayUnbindRequest, QuickPayUnbindChannelExtra,
} = require('../lspay');

const bindApply = new QuickPayBindApplyRequest()
  .setOrderId(`BIND${Date.now()}`)
  .setChannelExtra(new QuickPayBindChannelExtra()
    .setMchUserId('merchant-user-1').setCardId('银行卡号').setCardName('持卡人')
    .setCertId('证件号').setCertValidityType('1').setCertBeginDate('20200101')
    .setCardMp('银行预留手机号').setDcType('D'));

const payConfirm = new QuickPayConfirmRequest()
  .setMchOrderNo('原支付订单号').setVerifyCode('123456');

const unbind = new QuickPayUnbindRequest()
  .setOrderId(`UNBIND${Date.now()}`)
  .setChannelExtra(new QuickPayUnbindChannelExtra()
    .setMchUserId('merchant-user-1').setChannelCardId('渠道绑卡 ID'));

module.exports = { bindApply, payConfirm, unbind };
