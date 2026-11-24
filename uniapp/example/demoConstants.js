/**
 * UniApp Demo 演示用常量
 */
module.exports = {
  ORDER_PREFIX:  'JT',
  REFUND_PREFIX: 'JR',
  DEFAULT_AMOUNT_FEN: 1,
  DEFAULT_SUBJECT: '立收通 SDK Demo 商品',
  DEFAULT_BODY:    '立收通 SDK Demo 商品描述',

  genOrderNo()  { return 'JT' + Date.now(); },
  genRefundNo() { return 'JR' + Date.now(); },
};
