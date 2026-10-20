package com.atoma.app;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        handleDeepLink(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleDeepLink(intent);
    }

    private void handleDeepLink(Intent intent) {
        if (intent == null) return;
        final Uri uri = intent.getData();
        if (uri != null) {
            final Bridge b = getBridge();
            if (b != null && b.getWebView() != null) {
                b.getWebView().post(new Runnable() {
                    @Override
                    public void run() {
                        try {
                            String scheme = uri.getScheme();
                            if ("atomaroute".equalsIgnoreCase(scheme)) {
                                String token = uri.getQueryParameter("token");
                                if (token != null && !token.isEmpty()) {
                                    b.getWebView().loadUrl("https://atomaroute.com/auth/mobile-exchange?token=" + Uri.encode(token));
                                    return;
                                }
                                String host = uri.getHost();
                                String path = uri.getPath();
                                String targetPath = (host != null ? "/" + host : "") + (path != null ? path : "");
                                b.getWebView().loadUrl("https://atomaroute.com" + targetPath);
                                return;
                            }
                            b.getWebView().loadUrl(uri.toString());
                        } catch (Exception ignored) {}
                    }
                });
            }
        }
    }
}
