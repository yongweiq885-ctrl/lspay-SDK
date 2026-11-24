package tech.lspay.sdk.notify;

import tech.lspay.sdk.config.MerConfig;
import tech.lspay.sdk.core.LsPayTools;
import tech.lspay.sdk.exceptions.LsPaySignException;

import java.util.LinkedHashMap;
import java.util.Map;

public abstract class BaseNotify {
    protected final Map<String, Object> data;

    protected BaseNotify(Map<String, ?> source, MerConfig config) {
        if (source == null || source.isEmpty()) throw new IllegalArgumentException("通知参数不能为空");
        data = new LinkedHashMap<>();
        for (Map.Entry<String, ?> e : source.entrySet()) {
            if (e.getKey() == null || e.getKey().isEmpty() || e.getValue() instanceof Map
                    || e.getValue() instanceof Iterable || (e.getValue() != null && e.getValue().getClass().isArray()))
                throw new IllegalArgumentException("通知参数必须是标量值");
            data.put(e.getKey(), e.getValue());
        }
        String sign = value("sign");
        if (sign == null || !sign.matches("(?i)[0-9a-f]{32}") || !LsPayTools.verifySign(data, config.md5Key))
            throw new LsPaySignException("通知签名验证失败");
        if (!constantEquals(config.mchNo, value("mch_no")) || !constantEquals(config.appId, value("app_id")))
            throw new IllegalArgumentException("通知商户身份与 SDK 配置不匹配");
    }

    public Map<String, Object> getRaw() { return new LinkedHashMap<>(data); }
    public String getNotifyType(){return value("notify_type");} public String getEventType(){return value("event_type");}
    public String getPayOrderId(){return value("pay_order_id");} public String getMchNo(){return value("mch_no");}
    public String getAppId(){return value("app_id");} public String getWayCode(){return value("way_code");}
    public String getCurrency(){return value("currency");} public String getExtParam(){return value("ext_param");}
    public Long getState(){return number("state");} public String getSign(){return value("sign");}
    public abstract String getIdempotencyKey();

    protected String value(String key){Object v=data.get(key);return v==null?null:String.valueOf(v);}
    protected Long number(String key){try{String v=value(key);return v==null?null:Long.valueOf(v);}catch(Exception e){return null;}}
    protected String require(String key,int max){String v=value(key);if(v==null||v.trim().isEmpty())throw new IllegalArgumentException("通知缺少 "+key);
        if(v.length()>max)throw new IllegalArgumentException("通知字段 "+key+" 过长");return v;}
    protected long positive(String key){Long v=number(key);if(v==null||v<=0)throw new IllegalArgumentException("通知字段 "+key+" 必须大于 0");return v;}
    protected void expected(String key,Object expected){if(!constantEquals(String.valueOf(expected),value(key)))throw new IllegalArgumentException("通知字段 "+key+" 与订单记录不一致");}
    private static boolean constantEquals(String a,String b){if(a==null||b==null)return false;int diff=a.length()^b.length();int n=Math.max(a.length(),b.length());
        for(int i=0;i<n;i++)diff|=(i<a.length()?a.charAt(i):0)^(i<b.length()?b.charAt(i):0);return diff==0;}
}
