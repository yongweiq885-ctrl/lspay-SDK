package tech.lspay.sdk.enums;
import java.util.Arrays;
public final class PayDataTypeEnum { private PayDataTypeEnum(){} public static final String CASHIER="cashier",PAY_URL="pay_url",WXAPP="wxapp",ALIAPP="aliapp",YSFAPP="ysfapp",CODE_URL="code_url",CODE_IMG_URL="code_img_url",NONE="none";
 public static boolean isValid(String v){return Arrays.asList(CASHIER,PAY_URL,WXAPP,ALIAPP,YSFAPP,CODE_URL,CODE_IMG_URL,NONE).contains(v);}}
