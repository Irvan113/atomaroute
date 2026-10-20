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
                            b.getWebView().loadUrl(uri.toString());
                        } catch (Exception ignored) {}
                    }
                });
            }
        }
    }
}
