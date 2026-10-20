package com.atoma.app;

import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.SystemClock;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setupWebView();
        handleDeepLink(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleDeepLink(intent);
    }

    private void setupWebView() {
        Bridge b = getBridge();
        if (b == null || b.getWebView() == null) return;

        WebView webView = b.getWebView();

        // 1. Configure Persistent Cookies
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true);
        }

        // 2. Add Native Javascript Interface Bridge
        webView.addJavascriptInterface(new AtomaBridge(this), "AtomaBridge");

        // 3. Intercept Google OAuth to open Chrome with platform=apk & auto-sync widgets
        webView.setWebViewClient(new BridgeWebViewClient(b) {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                if (request != null && request.getUrl() != null) {
                    Uri url = request.getUrl();
                    String urlStr = url.toString();

                    // Intercept Google OAuth URL and delegate to external Chrome browser
                    if (urlStr.contains("/auth/google") && !urlStr.contains("/auth/google/callback")) {
                        Uri.Builder builder = url.buildUpon();
                        if (url.getQueryParameter("platform") == null) {
                            builder.appendQueryParameter("platform", "apk");
                        }
                        if (url.getQueryParameter("source") == null) {
                            builder.appendQueryParameter("source", "apk");
                        }
                        Uri apkAuthUri = builder.build();

                        Intent browserIntent = new Intent(Intent.ACTION_VIEW, apkAuthUri);
                        browserIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                        startActivity(browserIntent);
                        return true;
                    }
                }
                return super.shouldOverrideUrlLoading(view, request);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                try {
                    CookieManager.getInstance().flush();
                    view.evaluateJavascript("if(window.syncAtomaWidget) { window.syncAtomaWidget(); }", null);
                } catch (Throwable ignored) {}
            }
        });
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
                                    CookieManager.getInstance().flush();
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

    public class AtomaBridge {
        private final Context context;

        public AtomaBridge(Context context) {
            this.context = context;
        }

        @JavascriptInterface
        public void syncTasksToWidget(String tasksJson, int remainingCount) {
            try {
                SharedPreferences prefs = context.getSharedPreferences("AtomaWidgetPrefs", Context.MODE_PRIVATE);
                prefs.edit()
                    .putString("today_tasks_json", tasksJson != null ? tasksJson : "[]")
                    .putInt("remaining_count", remainingCount)
                    .putLong("last_updated", System.currentTimeMillis())
                    .apply();

                TodoWidgetProvider.updateAllWidgets(context);
            } catch (Throwable ignored) {}
        }

        @JavascriptInterface
        public void syncPomodoro(boolean isRunning, long remainingMs) {
            try {
                SharedPreferences prefs = context.getSharedPreferences("AtomaPomodoroPrefs", Context.MODE_PRIVATE);
                long now = SystemClock.elapsedRealtime();
                prefs.edit()
                    .putBoolean("is_running", isRunning)
                    .putLong("remaining_ms", remainingMs)
                    .putLong("target_end_elapsed", now + remainingMs)
                    .apply();

                PomodoroWidgetProvider.updateAllWidgets(context);
            } catch (Throwable ignored) {}
        }

        @JavascriptInterface
        public void openBrowser(String url) {
            try {
                if (url != null && !url.isEmpty()) {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                    intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                    context.startActivity(intent);
                }
            } catch (Throwable ignored) {}
        }
    }
}
