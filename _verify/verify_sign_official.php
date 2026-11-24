<?php
/**
 * 用官方文档的签名示例代码与 LsPayTools::sign 对同一组数据签名，结果必须相同。
 */
require_once __DIR__ . '/../php/LsPaySdk/init.php';

use LsPaySdk\core\LsPayTools;

/** 官方文档中的签名示例代码，保持原样作为对照。 */
function officialSign(array $data, string $secret): string {
    unset($data['sign']);
    $filteredData = array_filter($data, function ($value) {
        return $value !== '' && $value !== null;
    });
    ksort($filteredData);
    $string = '';
    foreach ($filteredData as $k => $v) {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $string .= $k . '=' . (string)$v . '&';
    }
    $string .= 'key=' . $secret;
    return strtoupper(md5($string));
}

$SECRET = 'JT2025_DEMO_SECRET_KEY';

$cases = [
    'minimal' => [
        'a' => '1',
        'b' => '2',
    ],
    'with_empty_and_null' => [
        'a' => '1',
        'b' => '',
        'c' => '2',
        'd' => null,
        'e' => '3',
        'sign' => 'WILL_BE_IGNORED',
    ],
    'unified_order_full' => [
        'mch_no'        => '839305812000286',
        'app_id'        => 'A0001',
        'way_code'      => 'WX_PUB',
        'amount'        => '100',
        'currency'      => 'cny',
        'subject'       => '测试商品',
        'body'          => '支付测试',
        'notify_url'    => 'https://www.lspay.tech/lspay/notify',
        'return_url'    => 'https://www.lspay.tech/success',
        'channel_extra' => '{"pay_data_type": "code_img_url"}',
        'req_time'      => '1779958002449',
        'version'       => '1.0',
        'sign_type'     => 'MD5',
        'mch_order_no'  => '0019770760423138',
    ],
    'with_nested_object' => [
        'mch_no'        => 'M001',
        'app_id'        => 'A001',
        'way_code'      => 'WX_PUB',
        'amount'        => 1,
        'subject'       => '商品',
        'channel_extra' => ['pay_data_type' => 'cashier', 'open_id' => 'oABC_中文'],
        'req_time'      => '1700000000000',
        'version'       => '1.0',
        'sign_type'     => 'MD5',
        'mch_order_no'  => 'E2025',
    ],
];

$fail = 0;
foreach ($cases as $name => $data) {
    $expect = officialSign($data, $SECRET);
    $actual = LsPayTools::sign($data, $SECRET);
    $ok     = ($expect === $actual);
    echo sprintf("  [%s] %-25s  expect=%s  actual=%s\n",
        $ok ? 'OK  ' : 'FAIL',
        $name,
        $expect,
        $actual
    );
    if (!$ok) $fail++;
}

echo "\n";
if ($fail) {
    echo "FAIL — $fail case(s) mismatch with official algorithm\n";
    exit(1);
}
echo "PASS\n";
