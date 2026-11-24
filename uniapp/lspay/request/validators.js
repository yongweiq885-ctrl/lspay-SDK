const { LsPayValidationException } = require('../exceptions');

function fail(message) { throw new LsPayValidationException(message); }
function text(value) { return value == null ? '' : String(value).trim(); }
function required(field, value, max) {
  const v = text(value);
  if (!v) fail(`${field} 必填`);
  if (max && Array.from(v).length > max) fail(`${field} 长度不能超过 ${max}`);
  return v;
}
function optional(field, value, max) {
  if (value == null || value === '') return;
  if (typeof value !== 'string' && typeof value !== 'number') fail(`${field} 必须是字符串`);
  if (max && Array.from(String(value)).length > max) fail(`${field} 长度不能超过 ${max}`);
}
function integer(field, value, positive = false) {
  if (!Number.isInteger(Number(value)) || String(value).trim() === '') fail(`${field} 必须是整数`);
  const n = Number(value);
  if (positive && n <= 0) fail(`${field} 必须是正整数`);
  return n;
}
function ipv4(field, value) {
  if (value == null || value === '') return;
  const parts = String(value).split('.');
  if (parts.length !== 4 || parts.some(p => !/^\d{1,3}$/.test(p) || Number(p) > 255)) {
    fail(`${field} 必须是合法 IPv4 地址`);
  }
}
function httpUrl(field, value) {
  if (value == null || value === '') return;
  if (!/^https?:\/\/[^/\s?#]+(?:[/?#]|$)/i.test(String(value))) fail(`${field} 必须是合法 HTTP(S) URL`);
  if (typeof URL === 'undefined') return;
  try {
    const u = new URL(String(value));
    if (u.protocol !== 'http:' && u.protocol !== 'https:') fail(`${field} 必须是合法 HTTP(S) URL`);
  } catch (_) { fail(`${field} 必须是合法 HTTP(S) URL`); }
}
function date8(field, value) {
  const v = String(value == null ? '' : value);
  if (!/^\d{8}$/.test(v)) fail(`${field} 必须是合法的 yyyyMMdd 格式日期`);
  const y = Number(v.slice(0, 4)); const m = Number(v.slice(4, 6)); const d = Number(v.slice(6, 8));
  const dt = new Date(Date.UTC(y, m - 1, d));
  if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== m - 1 || dt.getUTCDate() !== d) {
    fail(`${field} 必须是合法的 yyyyMMdd 格式日期`);
  }
  return dt;
}
function jsonObject(value, field = 'channel_extra') {
  if (value == null || value === '') return null;
  let data = value;
  if (value && typeof value.toArray === 'function') data = value.toArray();
  else if (value && typeof value.toJson === 'function') data = JSON.parse(value.toJson());
  else if (typeof value === 'string') {
    try { data = JSON.parse(value); } catch (_) { fail(`${field} 必须是合法 JSON 对象字符串`); }
  }
  if (!data || typeof data !== 'object' || Array.isArray(data)) fail(`${field} 必须是 JSON 对象`);
  return JSON.stringify(data);
}
function oneOf(field, value, allowed) {
  if (!allowed.includes(value)) fail(`${field} 非法`);
}

module.exports = { fail, text, required, optional, integer, ipv4, httpUrl, date8, jsonObject, oneOf };
