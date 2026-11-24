const assert = require('assert');
const sdk = require('../lspay');

function expectThrow(fn, contains) {
  assert.throws(fn, e => e && (!contains || e.message.includes(contains)));
}

async function main() {
  assert.strictEqual(Object.keys(sdk.Endpoint).length, 22);
  assert.strictEqual(Object.keys(sdk.WayCode.paymentMethods()).length, 20);
  assert.strictEqual(
    sdk.LsPayTools.sign({ a: '1', b: '2' }, 'JT2025_DEMO_SECRET_KEY'),
    'CD9D772610FCC1396431811A0189F50E',
  );

  const receiver = new sdk.DivisionReceiver('receiver-1').setDivAmt(100);
  const balanceExtra = new sdk.BalancePayChannelExtra()
    .setTransOut('account-1').addDivisionReceiver(receiver);
  const balance = new sdk.BalancePayRequest()
    .setMchOrderNo('B20260001').setAmount(100).setSubject('余额支付').setBody('协议测试')
    .setChannelExtra(balanceExtra);
  balance.validate();
  assert.strictEqual(balance.toMap().way_code, sdk.WayCode.BALANCE_PAY);
  expectThrow(() => balance.setWayCode(sdk.WayCode.ALI_QR), '固定');
  const invalidUnifiedWay = new sdk.UnifiedOrderRequest()
    .setMchOrderNo('P-BAD').setWayCode(sdk.WayCode.BALANCE_QUERY)
    .setAmount(1).setSubject('商品').setBody('描述');
  expectThrow(() => invalidUnifiedWay.validate(), '统一下单支付方式');
  const missingExtra = new sdk.UnifiedOrderRequest()
    .setMchOrderNo('P-NO-EXTRA').setWayCode(sdk.WayCode.ALI_QR)
    .setAmount(1).setSubject('商品').setBody('描述');
  assert.throws(() => missingExtra.validate(), e => e.message === 'channel_extra 必填');

  const requestCases = [
    [sdk.Endpoint.QUERY_ORDER, { mch_order_no: 'P1' }, sdk.QueryOrderRequest],
    [sdk.Endpoint.REFUND, { mch_refund_no: 'R1', mch_order_no: 'P1', refund_amount: 1, refund_reason: 'test' }, sdk.RefundRequest],
    [sdk.Endpoint.QUERY_REFUND, { mch_refund_no: 'R1' }, sdk.QueryRefundRequest],
    [sdk.Endpoint.DELAY_TRANS_CONFIRM, { mch_order_no: 'P1', order_id: 'C1' }, sdk.DelayTransConfirmRequest],
    [sdk.Endpoint.DELAY_TRANS_CONFIRM_REFUND, { confirm_order_id: 'C1', confirm_refund_no: 'CR1' }, sdk.DelayTransConfirmRefundRequest],
    [sdk.Endpoint.DELAY_TRANS_UNCONFIRM_QUERY, {}, sdk.DelayTransUnconfirmQueryRequest],
    [sdk.Endpoint.DELAY_TRANS_CREATE_TASK, { account_no: 'A1', account_name: '张三', order_no: 'T1', amount: 1 }, sdk.DelayTransCreateTaskRequest],
    [sdk.Endpoint.QUICK_PAY_CONFIRM, { mch_order_no: 'P1', verify_code: '123456' }, sdk.QuickPayConfirmRequest],
    [sdk.Endpoint.QUICK_PAY_SMS_CHECK, { mch_order_no: 'P1', verify_code: '123456' }, sdk.QuickPaySmsCheckRequest],
    [sdk.Endpoint.BALANCE_QUERY, { order_id: 'Q1', account_no: 'A1' }, sdk.BalanceQueryRequest],
    [sdk.Endpoint.ELECTRONIC_ACCOUNT_USER_QUERY, { account_no: 'A1' }, sdk.ElectronicAccountUserQueryRequest],
    [sdk.Endpoint.ELECTRONIC_ACCOUNT_SETTLEMENT_QUERY, { order_id: 'S1', account_no: 'A1', begin_date: '20261001', end_date: '20261015' }, sdk.ElectronicAccountSettlementQueryRequest],
    [sdk.Endpoint.ELECTRONIC_ACCOUNT_BALANCE_WITHDRAW, { order_id: 'W1', account_no: 'A1', withdraw_amount: '1.00' }, sdk.ElectronicAccountBalanceWithdrawRequest],
    [sdk.Endpoint.ELECTRONIC_ACCOUNT_SINGLE_WITHDRAW_QUERY, { account_no: 'A1', withdraw_date: '20261015', order_id: 'W1' }, sdk.ElectronicAccountSingleWithdrawQueryRequest],
    [sdk.Endpoint.ELECTRONIC_ACCOUNT_BATCH_WITHDRAW_QUERY, { account_no: 'A1', begin_date: '20261001', end_date: '20261015' }, sdk.ElectronicAccountBatchWithdrawQueryRequest],
  ];
  requestCases.forEach(([endpoint, params, Type]) => {
    const request = sdk.RequestFactory.fromArray(endpoint, params);
    assert(request instanceof Type);
    request.validate();
  });

  const bindExtra = new sdk.QuickPayBindChannelExtra()
    .setMchUserId('U1').setCardId('6222020000000000').setCardName('张三')
    .setCertId('110101199001010000').setCertValidityType('1')
    .setCertBeginDate('20200101').setCardMp('13800000000').setDcType('D');
  const bind = new sdk.QuickPayBindApplyRequest().setOrderId('BIND1').setChannelExtra(bindExtra);
  bind.validate();
  expectThrow(() => new sdk.QuickPayConfirmRequest().setMchOrderNo('P1').setVerifyCode('12').validate(), '6 位');

  sdk.LsPay.clear();
  sdk.LsPay.init({ mchNo: 'M1', appId: 'A1', md5Key: 'SECRET', notifyUrl: 'https://merchant.test/notify' });
  const captured = [];
  const oldHttp = sdk.LsPay._http;
  sdk.LsPay._http = async (url, body) => {
    captured.push({ url, body });
    return JSON.stringify({ status: 200, data: { pay_order_id: 'PAY1', order_state: 2 } });
  };
  const response = await sdk.LsPayClient.execute({
    funcCode: sdk.Endpoint.QUERY_ORDER,
    params: { mch_order_no: 'P1' },
  });
  assert(response instanceof sdk.QueryOrderResponse);
  assert.strictEqual(captured[0].url, 'https://api.lspay.tech/gateway/query');
  assert(captured[0].body.includes('sign='));

  const cashier = new sdk.UnifiedOrderRequest()
    .setMchOrderNo('C1').setWayCode(sdk.WayCode.ALI_QR).setAmount(1).setSubject('商品').setBody('描述')
    .setChannelExtra({ pay_data_type: 'cashier' });
  await assert.rejects(sdk.LsPayClient.execute(cashier), e => e.message.includes('buildCashierForm'));
  const form = sdk.LsPayClient.buildCashierForm(cashier);
  assert.strictEqual(captured.length, 1);
  assert.strictEqual(form.method, 'POST');
  assert.strictEqual(form.action, 'https://api.lspay.tech/gateway/unifiedorder');
  const { sign, ...unsigned } = form.fields;
  assert.strictEqual(sdk.LsPayTools.sign(unsigned, 'SECRET'), sign);
  assert.strictEqual(form.fields.channel_extra, '{"pay_data_type":"cashier"}');
  assert(form.toHtml().includes('name="sign"') && form.toHtml().includes('.submit()'));
  expectThrow(() => sdk.LsPayClient.buildCashierForm(cashier.setChannelExtra({ pay_data_type: 'pay_url' })), 'cashier');
  sdk.LsPay._http = oldHttp;

  const notifyData = {
    notify_type: 'payment', event_type: 'payment.succeeded', pay_order_id: 'PAY1',
    mch_no: 'M1', app_id: 'A1', mch_order_no: 'P1', way_code: 'ALI_QR',
    amount: '100', currency: 'cny', state: '2', subject: '商品', body: '描述',
    add_time: '1660000000', success_time: '1660000001', req_time: '1660000000000',
  };
  notifyData.sign = sdk.LsPayTools.sign(notifyData, 'SECRET');
  const notify = sdk.LsPayNotify.parse(notifyData);
  assert(notify instanceof sdk.PaymentNotify);
  assert(notify.isSucceeded());
  notify.assertOrder('P1', 100);
  const tampered = Object.assign({}, notifyData, { amount: '101' });
  expectThrow(() => sdk.LsPayNotify.parse(tampered), '签名');

  ['success', 'succeess'].forEach(status => {
    const raw = JSON.stringify({ status: 200, data: { status } });
    assert(new sdk.ElectronicAccountBalanceWithdrawResponse(raw).isAccepted());
    assert(new sdk.ElectronicAccountSingleWithdrawQueryResponse(raw).isChannelSuccessful());
    assert(new sdk.ElectronicAccountBatchWithdrawQueryResponse(raw).isChannelSuccessful());
  });
  const flatQuickPay = new sdk.QuickPayBindApplyResponse(JSON.stringify({
    trans_status: 'S', trans_id: 'T1', order_id: 'O1',
  }));
  assert(flatQuickPay.isSuccessful());
  assert.strictEqual(flatQuickPay.getTransId(), 'T1');
  const flatBalance = new sdk.BalanceQueryResponse(JSON.stringify({
    acct_type: '01', acct_stat: 'N', balance_amt: '100',
  }));
  assert(!flatBalance.isError());
  assert.strictEqual(flatBalance.getBalanceAmount(), '100');
  const settlement = new sdk.ElectronicAccountSettlementQueryResponse(JSON.stringify({
    status: 200, data: [{ trans_id: 'T2' }],
  }));
  assert.strictEqual(settlement.getTransId(), 'T2');
  const batchResponse = new sdk.ElectronicAccountBatchWithdrawQueryResponse(JSON.stringify({
    status: 200, data: { status: 'succeess', data: [{ order_id: 'W1' }] },
  }));
  assert.strictEqual(batchResponse.getOrderId(), 'W1');
  const refundTypo = new sdk.QueryRefundResponse(JSON.stringify({
    status: 200, data: { order_refund_Id: 'R1', channel_rrder_no: 'C1' },
  }));
  assert.strictEqual(refundTypo.getRefundOrderId(), 'R1');
  assert.strictEqual(refundTypo.getChannelOrderNo(), 'C1');
  const taskResponse = new sdk.DelayTransCreateTaskResponse(JSON.stringify({
    status: 200, data: { status: 'PENDING', selected_orders: 2 },
  }));
  assert(taskResponse.isCreated());
  assert.strictEqual(taskResponse.getSelectedOrders(), 2);

  const redacted = sdk.LsPay._redact({ sign: 'abc', channel_extra: JSON.stringify({ card_no: '6222', buyer_id: 'ok' }) });
  assert(!redacted.includes('6222'));
  assert(!redacted.includes('"abc"'));
  assert(redacted.includes('buyer_id'));

  console.log(`protocol tests passed (${requestCases.length + 14} assertions/groups)`);
}

main().catch(err => { console.error(err); process.exitCode = 1; });
