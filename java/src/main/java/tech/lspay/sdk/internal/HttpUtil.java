package tech.lspay.sdk.internal;

import tech.lspay.sdk.exceptions.LsPayNetworkException;

import javax.net.ssl.*;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.cert.X509Certificate;

/**
 * 内部 HTTP 工具，仅给 SDK 自身用
 */
public final class HttpUtil {
    private HttpUtil() {}

    /**
     * 以 application/x-www-form-urlencoded 提交
     */
    public static String postForm(String url, String formBody, int connectMs, int readMs, boolean insecure) {
        HttpURLConnection conn = null;
        try {
            URL u = new URL(url);
            conn = (HttpURLConnection) u.openConnection();
            if (insecure && conn instanceof HttpsURLConnection) {
                applyInsecure((HttpsURLConnection) conn);
            }
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(connectMs);
            conn.setReadTimeout(readMs);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded;charset=utf-8");
            conn.setRequestProperty("Accept", "application/json");
            try (OutputStream os = conn.getOutputStream()) {
                os.write(formBody.getBytes(StandardCharsets.UTF_8));
            }
            int code = conn.getResponseCode();
            InputStream is = code >= 400 ? conn.getErrorStream() : conn.getInputStream();
            String body = readAll(is);
            if (code < 200 || code >= 300) {
                throw new LsPayNetworkException("HTTP " + code + " body=" + body, null);
            }
            return body;
        } catch (LsPayNetworkException e) {
            throw e;
        } catch (Exception e) {
            throw new LsPayNetworkException("HTTP 请求失败: " + e.getMessage(), e);
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    /** @deprecated 立收通改为 form-urlencoded 提交，新代码请用 {@link #postForm} */
    @Deprecated
    public static String postJson(String url, String json, int connectMs, int readMs, boolean insecure) {
        return postForm(url, json, connectMs, readMs, insecure);
    }

    private static String readAll(InputStream is) throws java.io.IOException {
        if (is == null) return "";
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        byte[] buf = new byte[4096];
        int n;
        while ((n = is.read(buf)) > 0) out.write(buf, 0, n);
        return out.toString(StandardCharsets.UTF_8.name());
    }

    private static void applyInsecure(HttpsURLConnection conn) throws Exception {
        TrustManager[] trustAll = new TrustManager[]{ new X509TrustManager() {
            public X509Certificate[] getAcceptedIssuers() { return new X509Certificate[0]; }
            public void checkClientTrusted(X509Certificate[] c, String a) {}
            public void checkServerTrusted(X509Certificate[] c, String a) {}
        }};
        SSLContext ctx = SSLContext.getInstance("TLS");
        ctx.init(null, trustAll, new java.security.SecureRandom());
        conn.setSSLSocketFactory(ctx.getSocketFactory());
        conn.setHostnameVerifier((host, session) -> true);
    }
}
