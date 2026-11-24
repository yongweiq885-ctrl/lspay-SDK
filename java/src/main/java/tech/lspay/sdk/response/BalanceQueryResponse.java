package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
import java.util.Map;
public class BalanceQueryResponse extends LsPayResponse {
    public BalanceQueryResponse(String raw){super(raw);}
    @SuppressWarnings("unchecked") private Object balance(String key){
        Object b=getData().get("balance");
        if(b instanceof Map)return ((Map<String,Object>)b).get(key);
        if(getData().containsKey(key))return getData().get(key);
        return getMap().get(key);
    }
    @Override public boolean isError(){
        return !getMap().containsKey("status")&&getMap().containsKey("acct_type")?false:super.isError();
    }
    public String getAccountType(){return string(balance("acct_type"));}
    public String getAccountStatus(){return string(balance("acct_stat"));}
    public String getBalanceAmount(){return string(balance("balance_amt"));}
    public String getAvailableBalance(){return string(balance("avl_balance"));}
    public String getFrozenBalance(){return string(balance("frz_bal"));}
    public String getHistoricalBalance(){return string(balance("dm_amt"));}
    public boolean isNormal(){return !isError()&&"N".equals(getAccountStatus());}
    private String string(Object v){return v==null?null:String.valueOf(v);}
}
