package tech.lspay.sdk.example;

import tech.lspay.sdk.core.LsPayClient;
import tech.lspay.sdk.request.*;

/**
 * 新增业务族构造示例。配置初始化方式见 Loader；生产代码取消注释 execute 即可发送。
 */
public final class ExtendedFeaturesDemo {
    private ExtendedFeaturesDemo() {}

    public static void main(String[] args) {
        BalancePayChannelExtra balanceExtra = new BalancePayChannelExtra().setTransOut("OUT_ACCOUNT");
        balanceExtra.addDivisionReceiver(new DivisionReceiver("IN_ACCOUNT").setDivAmt(100));
        BalancePayRequest balancePay = new BalancePayRequest();
        balancePay.setMchOrderNo("BAL" + System.currentTimeMillis())
                .setAmount(100).setSubject("余额支付").setBody("余额支付示例");
        balancePay.setChannelExtra(balanceExtra);

        QuickPayBindApplyRequest bind = new QuickPayBindApplyRequest()
                .setOrderId("BIND" + System.currentTimeMillis())
                .setClientIp("127.0.0.1")
                .setChannelExtra(new QuickPayBindChannelExtra()
                        .setMchUserId("USER1").setCardId("CARD").setCardName("张三")
                        .setCertId("CERT").setCertValidityType("1")
                        .setCertBeginDate("20260101").setCardMp("13800000000").setDcType("D"));

        ElectronicAccountUserOpenRequest open = new ElectronicAccountUserOpenRequest()
                .setOrderId("OPEN" + System.currentTimeMillis()).setAccountName("张三")
                .setAccountType(1).setRelationType("USER").setAccountAlias("demo")
                .setDivisionProfit("0.1")
                .setChannelExtra(new ElectronicAccountOpenChannelExtra()
                        .setUserInfo(new ElectronicAccountUserInfo()
                                .setCertType("00").setName("张三")
                                .setCertNo("CERT").setMobileNo("13800000000")));

        balancePay.validate();
        bind.validate();
        open.validate();
        System.out.println(balancePay.toMap());
        System.out.println(bind.toMap());
        System.out.println(open.toMap());
        // Loader.load(); LsPayClient.execute(balancePay);
    }
}
