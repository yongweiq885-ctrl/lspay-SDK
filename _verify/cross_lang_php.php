<?php
/** 用 fixtures/cross_lang.json 校验 PHP 签名，stdout 输出各用例签名供其他语言比对。 */
require_once __DIR__ . '/../php/LsPaySdk/init.php';

use LsPaySdk\core\LsPayTools;

$fixturePath = __DIR__ . '/fixtures/cross_lang.json';
$fx          = json_decode(file_get_contents($fixturePath), true);
$secret      = $fx['secret'];

$out = ['language' => 'php', 'signs' => []];
$fail = 0;
foreach ($fx['tests'] as $t) {
    $sig = LsPayTools::sign($t['data'], $secret);
    $out['signs'][$t['name']] = $sig;
    $ok = ($sig === $t['expect_sign']);
    fprintf(STDERR, "  [%s] %-25s sig=%s expect=%s\n",
        $ok ? 'OK  ' : 'FAIL', $t['name'], $sig, $t['expect_sign']);
    if (!$ok) $fail++;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
exit($fail ? 1 : 0);
