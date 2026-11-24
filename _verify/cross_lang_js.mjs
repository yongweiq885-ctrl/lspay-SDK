/** 用 fixtures/cross_lang.json 校验 JS 签名，stdout 输出各用例签名供其他语言比对。 */
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const require = createRequire(import.meta.url);
const here = path.dirname(fileURLToPath(import.meta.url));

const { LsPayTools } = require(path.join(here, '..', 'uniapp', 'lspay'));
const fx = JSON.parse(readFileSync(path.join(here, 'fixtures', 'cross_lang.json'), 'utf8'));

const secret = fx.secret;
const out = { language: 'js', signs: {} };
let fail = 0;
for (const t of fx.tests) {
  const sig = LsPayTools.sign(t.data, secret);
  out.signs[t.name] = sig;
  const ok = sig === t.expect_sign;
  process.stderr.write(
    `  [${ok ? 'OK  ' : 'FAIL'}] ${t.name.padEnd(25)} sig=${sig} expect=${t.expect_sign}\n`
  );
  if (!ok) fail++;
}
console.log(JSON.stringify(out, null, 2));
process.exit(fail ? 1 : 0);
