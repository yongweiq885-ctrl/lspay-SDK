<template>
  <view class="container">
    <view class="title">立收通 SDK · UniApp 服务端代理 Demo</view>

    <view class="form-item">
      <text>金额（元）</text>
      <input v-model="amountYuan" type="number" placeholder="如 1.00" />
    </view>

    <view class="form-item">
      <text>商品名称</text>
      <input v-model="subject" placeholder="商品名称" />
    </view>

    <view class="form-item">
      <text>商户 Profile</text>
      <input v-model="profile" placeholder="default / merch_b" />
    </view>

    <view class="form-item">
      <text>微信 openid</text>
      <input v-model="openid" placeholder="小程序内授权得到的 openid" />
    </view>

    <button class="btn" @click="onPay">支付宝 当面付下单</button>
    <button class="btn" @click="onWxLite">微信小程序支付</button>
    <button class="btn" @click="onWxNative">微信 扫码/JSAPI 下单</button>
    <button class="btn" @click="onQuery">查询订单</button>
    <button class="btn" @click="onRefund">申请退款</button>
    <button class="btn" @click="onQueryRefund">退款查询</button>

    <view v-if="result" class="result">
      <text>{{ result }}</text>
    </view>
  </view>
</template>

<script>
import { LsPayTools } from '../../../lspay';

// 这里必须是业务方自己的服务端，服务端再使用 SDK 保存密钥、签名并调用立收通。
const BACKEND_BASE = 'https://merchant.example/api/lspay';
export default {
  data() {
    return {
      profile: 'default',
      amountYuan: '0.01',
      subject: '立收通 SDK Demo',
      openid: '',
      lastOrderNo: '',
      lastRefundNo: '',
      result: '',
    };
  },
  methods: {
    _newOrderNo() {
      const no = 'JT' + Date.now();
      this.lastOrderNo = no;
      return no;
    },
    _newRefundNo() {
      const no = 'JR' + Date.now();
      this.lastRefundNo = no;
      return no;
    },
    async _run(label, action, params) {
      try {
        const data = await new Promise((resolve, reject) => uni.request({
          url: `${BACKEND_BASE}/${action}`,
          method: 'POST',
          data: { profile: this.profile, ...params },
          success: res => res.statusCode >= 200 && res.statusCode < 300
            ? resolve(res.data) : reject(new Error(`HTTP ${res.statusCode}`)),
          fail: reject,
        }));
        this.result = `${label}: ${JSON.stringify(data)}`;
      } catch (e) {
        this.result = `${label} 异常: ${e.message || e}`;
      }
    },
    onPay() {
      this._run('支付宝下单', 'unified-order', {
        mch_order_no: this._newOrderNo(), way_code: 'ALI_QR',
        amount: LsPayTools.toFen(this.amountYuan), subject: this.subject, body: this.subject,
      });
    },
    async onWxLite() {
      if (!this.openid) {
        this.result = '请填写 openid';
        return;
      }
      try {
        const data = await new Promise((resolve, reject) => uni.request({
          url: `${BACKEND_BASE}/unified-order`,
          method: 'POST',
          data: {
            profile: this.profile,
            mch_order_no: this._newOrderNo(),
            way_code: 'WX_LITE',
            amount: LsPayTools.toFen(this.amountYuan),
            subject: this.subject,
            body: this.subject,
            channel_extra: { openid: this.openid },
          },
          success: res => res.statusCode >= 200 && res.statusCode < 300
            ? resolve(res.data) : reject(new Error(`HTTP ${res.statusCode}`)),
          fail: reject,
        }));
        const payload = data.data || data;
        let payInfo = payload.pay_info;
        if (typeof payInfo === 'string') payInfo = JSON.parse(payInfo);
        await new Promise((resolve, reject) => uni.requestPayment({
          provider: 'wxpay',
          timeStamp: payInfo.timeStamp,
          nonceStr: payInfo.nonceStr,
          package: payInfo.package,
          signType: payInfo.signType,
          paySign: payInfo.paySign,
          success: resolve,
          fail: reject,
        }));
        this.result = '微信支付已调起';
      } catch (e) {
        this.result = `微信小程序支付异常: ${e.message || e.errMsg || e}`;
      }
    },
    onWxNative() {
      this._run('微信 WX_NATIVE 扫码下单', 'unified-order', {
        mch_order_no: this._newOrderNo(), way_code: 'WX_NATIVE',
        amount: LsPayTools.toFen(this.amountYuan), subject: this.subject, body: this.subject,
      });
    },
    onQuery() {
      if (!this.lastOrderNo) {
        this.result = '请先下单生成订单号';
        return;
      }
      this._run('查询订单', 'query-order', { mch_order_no: this.lastOrderNo });
    },
    onRefund() {
      if (!this.lastOrderNo) {
        this.result = '请先下单生成订单号';
        return;
      }
      this._run('申请退款', 'refund', {
        mch_refund_no: this._newRefundNo(), mch_order_no: this.lastOrderNo,
        refund_amount: LsPayTools.toFen(this.amountYuan), refund_reason: '用户申请退款',
      });
    },
    onQueryRefund() {
      if (!this.lastRefundNo) {
        this.result = '请先发起退款';
        return;
      }
      this._run('退款查询', 'query-refund', { mch_refund_no: this.lastRefundNo });
    },
  },
};
</script>

<style>
.container { padding: 24px; }
.title { font-size: 18px; font-weight: bold; margin-bottom: 16px; }
.form-item { display: flex; flex-direction: column; margin-bottom: 12px; }
.form-item text { font-size: 12px; color: #666; }
.form-item input { border: 1px solid #ddd; padding: 8px; border-radius: 4px; }
.btn { margin-top: 12px; background: #0066ff; color: #fff; }
.result {
  margin-top: 16px; padding: 12px; border-radius: 6px;
  background: #f6f8fa; word-break: break-all; font-size: 12px;
}
</style>
