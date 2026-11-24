package tech.lspay.sdk.request;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.time.format.DateTimeParseException;

abstract class AbstractPagedAccountRequest extends BaseRequest {
    protected static void date(String field, String value) {
        required(field, value, 8);
        try { LocalDate.parse(value, DateTimeFormatter.BASIC_ISO_DATE); }
        catch (DateTimeParseException e) { throw new IllegalArgumentException(field + " 必须是 yyyyMMdd 格式日期"); }
    }
    protected static void page(String field, Integer value) {
        if (value == null || value <= 0) throw new IllegalArgumentException(field + " 必须是正整数");
    }
}
