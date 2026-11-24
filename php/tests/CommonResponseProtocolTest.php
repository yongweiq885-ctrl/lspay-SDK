<?php
/**
 * 所有接口共享的响应状态协议测试。
 *
 * 运行：php php/tests/CommonResponseProtocolTest.php
 */
require_once dirname(__DIR__) . '/LsPaySdk/init.php';

use LsPaySdk\core\LsPayResponse;

function responseAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function responseAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected=' . var_export($expected, true)
            . ', actual=' . var_export($actual, true)
        );
    }
}

$tests = [];

$tests['status 200 response'] = function () {
    $response = new LsPayResponse([
        'status' => 200,
        'msg' => '处理成功',
        'sign' => 'CCD9083A6DAD9A2DA9F668C3D4517A84',
        'data' => ['order_id' => 'ORDER1'],
    ]);

    responseAssertTrue(!$response->isError(), 'status=200 应为处理成功');
    responseAssertSame(200, $response->getStatus(), '应返回原始 status');
    responseAssertSame('处理成功', $response->getMsg(), '应返回 msg');
    responseAssertSame(
        'CCD9083A6DAD9A2DA9F668C3D4517A84',
        $response->getSign(),
        '应透传响应 sign'
    );
    responseAssertSame(['order_id' => 'ORDER1'], $response->getData(), '应返回 data');
};

$tests['non-200 response'] = function () {
    $response = new LsPayResponse([
        'status' => 400,
        'msg' => '签名失败',
    ]);

    responseAssertTrue($response->isError(), '非 200 状态应为处理失败');
    responseAssertSame(
        ['status' => 400, 'msg' => '签名失败'],
        $response->getErrorInfo(),
        '错误信息应包含 status 和 msg'
    );
};

$tests['json string data'] = function () {
    $response = new LsPayResponse([
        'status' => 200,
        'data' => '{"amount":100,"trans_status":"S"}',
    ]);

    responseAssertSame(
        ['amount' => 100, 'trans_status' => 'S'],
        $response->getData(),
        'JSON 字符串 data 应解析为数组'
    );
};

$tests['legacy status compatibility'] = function () {
    foreach ([
        ['code' => '0', 'msg' => 'ok'],
        ['retCode' => 'SUCCESS', 'retMsg' => 'ok'],
    ] as $raw) {
        $response = new LsPayResponse($raw);
        responseAssertTrue(!$response->isError(), '应兼容旧响应成功状态');
        responseAssertSame($response->getStatus(), $response->getCode(), 'getCode 为兼容别名');
    }
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo "[PASS] $name\n";
}
echo "Common response protocol: $passed/" . count($tests) . " passed\n";
