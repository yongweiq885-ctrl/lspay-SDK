package tech.lspay.sdk.response;
import tech.lspay.sdk.core.LsPayResponse;
public class DelayTransCreateTaskResponse extends LsPayResponse {
    public DelayTransCreateTaskResponse(String raw){super(raw);}
    public String getTaskId(){return stringValue("task_id");}
    public String getOrderNo(){return stringValue("order_no");}
    public String getAccountNo(){return stringValue("account_no");}
    public String getAccountName(){return stringValue("account_name");}
    public Long getAmount(){return longValue("amount");}
    public String getTaskStatus(){return stringValue("status");}
    public Long getSelectedOrders(){return longValue("selected_orders");}
    public String getFirstOrderTime(){return stringValue("first_order_time");}
    public String getLastOrderTime(){return stringValue("last_order_time");}
    public boolean isCreated(){return !isError()&&"PENDING".equals(getTaskStatus());}
}
