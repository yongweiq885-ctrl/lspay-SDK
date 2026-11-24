const {
  BalancePayRequest, BalancePayChannelExtra, DivisionReceiver,
  DelayTransConfirmRequest, DelayTransCreateTaskRequest,
} = require('../lspay');

const balancePay = new BalancePayRequest()
  .setMchOrderNo(`BAL${Date.now()}`).setAmount(100).setSubject('余额支付').setBody('余额支付示例')
  .setChannelExtra(new BalancePayChannelExtra()
    .setTransOut('电子账户号')
    .addDivisionReceiver(new DivisionReceiver('接收方账户').setDivAmt(100)));

const confirm = new DelayTransConfirmRequest()
  .setMchOrderNo('原支付订单号').setOrderId(`CONFIRM${Date.now()}`)
  .setChannelExtra({ div_list: [{ trans_in: '接收方账户', div_amt: 100 }] });

const transfer = new DelayTransCreateTaskRequest()
  .setAccountNo('电子账户号').setAccountName('账户名称')
  .setOrderNo(`TRANSFER${Date.now()}`).setAmount(100);

module.exports = { balancePay, confirm, transfer };
