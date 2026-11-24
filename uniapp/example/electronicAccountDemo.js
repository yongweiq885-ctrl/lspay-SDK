const {
  ElectronicAccountUserOpenRequest, ElectronicAccountOpenChannelExtra,
  ElectronicAccountUserInfo, ElectronicAccountSettlementQueryRequest,
  ElectronicAccountBalanceWithdrawRequest,
} = require('../lspay');

const userOpen = new ElectronicAccountUserOpenRequest()
  .setOrderId(`OPEN${Date.now()}`).setAccountName('张三').setAccountType(1)
  .setRelationType('USER').setAccountAlias('示例账户').setDivisionProfit('0.10')
  .setChannelExtra(new ElectronicAccountOpenChannelExtra()
    .setUserInfo(new ElectronicAccountUserInfo()
      .setCertType('00').setName('张三').setCertNo('证件号').setCertValidityType('1')
      .setCertBeginDate('20200101').setMobileNo('手机号')));

const settlementQuery = new ElectronicAccountSettlementQueryRequest()
  .setOrderId(`SETTLE${Date.now()}`).setAccountNo('电子账户号')
  .setBeginDate('20261001').setEndDate('20261015').setPageNum(1).setPageSize(20);

const withdraw = new ElectronicAccountBalanceWithdrawRequest()
  .setOrderId(`WITHDRAW${Date.now()}`).setAccountNo('电子账户号').setWithdrawAmount('100.00');

module.exports = { userOpen, settlementQuery, withdraw };
