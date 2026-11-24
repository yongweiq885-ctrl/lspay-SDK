/**
 * Java SDK 签名检查。期望值与其他语言共用同一组数据。
 *
 * 先在 java/ 下执行 mvn -q package，再在 _verify/ 下：
 *   javac -encoding UTF-8 -cp ../java/target/classes VerifySign.java
 *   java  -cp ../java/target/classes:. VerifySign
 * fastjson2 需要按 java/pom.xml 中的版本加入 classpath。
 */
import java.util.LinkedHashMap;
import java.util.Map;
import tech.lspay.sdk.core.LsPayTools;

public class VerifySign {
    public static void main(String[] args) {
        Map<String, Object> data = new LinkedHashMap<>();
        data.put("mch_no", "M001");
        data.put("app_id", "A001");
        data.put("mch_order_no", "E20250101");
        data.put("way_code", "ALI_QR");
        data.put("amount", 1);
        data.put("currency", "cny");
        data.put("subject", "测试");
        data.put("body", "biz");
        data.put("req_time", "1700000000000");
        data.put("version", "1.0");
        data.put("sign_type", "MD5");
        data.put("channel_extra", "{\"pay_data_type\":\"cashier\"}");

        String expected = "18BC2B412C82CDE0B7A58923E6EFC06D";
        String got = LsPayTools.sign(data, "KEY");
        System.out.println("expected = " + expected);
        System.out.println("got      = " + got);

        boolean ok = expected.equals(got);
        System.out.println(ok ? "PASS" : "FAIL");
        if (!ok) System.exit(1);
    }
}
