<?php
/**
 * 立收通工具集：签名 / 验签 / 时间戳 / 金额转换
 */
namespace LsPaySdk\core;

class LsPayTools
{
    /**
     * 立收通官方签名算法（doc.lspay.tech）
     *   1. 移除 sign 字段
     *   2. 过滤空值（'' / null）
     *   3. 按字段名 ASCII 升序排序（ksort）
     *   4. 按 k=v&k=v 顺序拼接，数组/对象需先 json_encode（不转中文/斜杠）
     *   5. 末尾追加 &key=应用密钥（md5_key）
     *   6. MD5 -> 转大写
     *
     * 注意：禁止用 http_build_query 拼接（其会对 value 做 URL 编码，与平台不一致）。
     *
     * @param array  $data   待签参数（含或不含 sign 均可，sign 会被忽略）
     * @param string $secret 商户应用密钥 md5_key（必填）
     * @return string 32 位大写 MD5
     */
    public static function sign(array $data, $secret = '')
    {
        unset($data['sign']);

        $filtered = [];
        foreach ($data as $k => $v) {
            if ($v === '' || $v === null) continue;
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $filtered[$k] = (string)$v;
        }
        ksort($filtered);

        $buf = '';
        foreach ($filtered as $k => $v) {
            $buf .= $k . '=' . $v . '&';
        }
        $buf .= 'key=' . (string)$secret;

        return strtoupper(md5($buf));
    }

    /**
     * 校验异步通知 / 同步回跳的签名
     *
     * @param array  $data
     * @param string $secret 商户应用密钥 md5_key
     */
    public static function verifySign(array $data, $secret = '')
    {
        if (empty($data['sign'])) return false;
        $expect = self::sign($data, $secret);
        return hash_equals(
            strtoupper($expect),
            strtoupper((string)$data['sign'])
        );
    }

    /** 毫秒级时间戳 */
    public static function msecTime()
    {
        list($msec, $sec) = explode(' ', microtime());
        return (string)(int)((floatval($msec) + floatval($sec)) * 1000);
    }

    /**
     * 元 → 分
     *   LsPayTools::toFen('0.01')   // 1
     *   LsPayTools::toFen('12.34')  // 1234
     */
    public static function toFen($yuan)
    {
        $s = trim((string)$yuan);
        if ($s === '' || !is_numeric($s)) {
            throw new \InvalidArgumentException("非法金额: $yuan");
        }
        $neg = false;
        if ($s[0] === '-') { $neg = true; $s = substr($s, 1); }

        if (strpos($s, '.') === false) {
            $intPart = $s; $decPart = '';
        } else {
            list($intPart, $decPart) = explode('.', $s, 2);
        }
        $decPart = substr($decPart . '00', 0, 2);
        $intPart = ltrim($intPart, '0');
        if ($intPart === '') $intPart = '0';
        $fen = (int)($intPart . str_pad($decPart, 2, '0'));
        return $neg ? -$fen : $fen;
    }

    /** 分 → 元（字符串，保留 2 位小数） */
    public static function toYuan($fen)
    {
        $n = (int)$fen;
        $neg = $n < 0;
        $n   = abs($n);
        $yuan = intdiv($n, 100);
        $cent = $n % 100;
        $s = $yuan . '.' . str_pad((string)$cent, 2, '0', STR_PAD_LEFT);
        return $neg ? '-' . $s : $s;
    }

    /** 判断字符串是否以指定字符串结尾 */
    public static function endWith($str, $needle)
    {
        return substr($str, -strlen($needle)) === $needle;
    }
}
