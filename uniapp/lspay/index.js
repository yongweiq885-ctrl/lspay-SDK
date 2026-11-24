/**
 * 立收通 (LSPay) UniApp / JS SDK
 *
 * 最简用法（仅限 Node.js/可信服务端；前端不得保存 md5Key）：
 *   const { LsPay, LsPayClient, MerConfig, WayCode, UnifiedOrderRequest } = require('./lspay');
 *   LsPay.init('default', new MerConfig({ mchNo:'...', appId:'...', md5Key:'...' }));
 *   const req = new UnifiedOrderRequest()
 *       .setMchOrderNo('JT123').setWayCode(WayCode.ALI_QR).setAmount(1)
 *       .setSubject('test').setBody('test body').setChannelExtra({ pay_data_type: 'code_url' });
 *   const resp = await LsPayClient.execute(req);
 *   if (resp.isError()) console.log(resp.getErrorInfo()); else console.log(resp.getData());
 */

const MerConfig    = require('./config');
const exceptions   = require('./exceptions');
const WayCode      = require('./enums/WayCode');
const Endpoint     = require('./enums/Endpoint');

const LsPay        = require('./core/LsPay');
const LsPayClient  = require('./core/LsPayClient');
const LsPayResponse= require('./core/LsPayResponse');
const LsPayTools   = require('./core/LsPayTools');

const BaseRequest         = require('./request/BaseRequest');
const requests            = require('./request/Requests');
const RequestFactory      = require('./request/RequestFactory');
const channelExtras       = require('./request/ChannelExtra');
const responses           = require('./response/Responses');
const protocolEnums       = require('./enums/ProtocolEnums');
const notifications       = require('./notify/LsPayNotify');

module.exports = {
  // 配置
  MerConfig,
  // 异常
  ...exceptions,
  // 枚举
  WayCode,
  Endpoint,
  ...protocolEnums,
  // 核心
  LsPay,
  LsPayClient,
  LsPayResponse,
  LsPayTools,
  // Request
  BaseRequest,
  RequestFactory,
  ...requests,
  ...channelExtras,
  ...responses,
  ...notifications,

  version: '3.0.0',
};
