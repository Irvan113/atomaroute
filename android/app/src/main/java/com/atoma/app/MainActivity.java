package com.atoma.app;

import android.Manifest;
import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.SystemClock;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        requestNotificationPermission();
        setupWebView();
        handleDeepLink(getIntent());
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                    != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(
                    this,
                    new String[]{Manifest.permission.POST_NOTIFICATIONS},
                    1001
                );
            }
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleDeepLink(intent);
    }

    @Override
    public void onPause() {
        super.onPause();
        try {
            CookieManager.getInstance().flush();
        } catch (Throwable ignored) {}
    }

    @Override
    public void onStop() {
        super.onStop();
        try {
            CookieManager.getInstance().flush();
        } catch (Throwable ignored) {}
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        try {
            CookieManager.getInstance().flush();
        } catch (Throwable ignored) {}
    }

    private void setupWebView() {
        Bridge b = getBridge();
        if (b == null || b.getWebView() == null) return;

        WebView webView = b.getWebView();

        // 1. Hardware acceleration & smooth native rendering
        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setVerticalScrollBarEnabled(false);
        webView.setHorizontalScrollBarEnabled(false);

        // 2. Configure Persistent Cookies and Storage
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true);
        }

        WebSettings settings = webView.getSettings();
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setTextZoom(100);
        settings.setLoadsImagesAutomatically(true);

        // 3. Add Native Javascript Interface Bridge
        webView.addJavascriptInterface(new AtomaBridge(this), "AtomaBridge");

        // 4. Intercept Google OAuth to open Chrome with platform=apk & auto-sync widgets
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

                    // Intercept root landing page URL to keep user on /tasks
                    if (urlStr.equals("https://atomaroute.com/") || urlStr.equals("https://atomaroute.com")
                            || urlStr.equals("http://atomaroute.com/") || urlStr.equals("http://atomaroute.com")) {
                        view.loadUrl("https://atomaroute.com/tasks");
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
        public void syncNotesToWidget(String title, String snippet, String date, long noteId) {
            try {
                SharedPreferences prefs = context.getSharedPreferences("AtomaNotesPrefs", Context.MODE_PRIVATE);
                prefs.edit()
                    .putLong("latest_note_id", noteId)
                    .putString("latest_note_title", title != null ? title : "")
                    .putString("latest_note_snippet", snippet != null ? snippet : "")
                    .putString("latest_note_date", date != null ? date : "")
                    .putLong("last_updated", System.currentTimeMillis())
                    .apply();

                NotesWidgetProvider.updateAllWidgets(context);
            } catch (Throwable ignored) {}
        }

        @JavascriptInterface
        public void scheduleNativeReminder(long notifId, String title, String body, long epochMs) {
            try {
                AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
                if (alarmManager == null) return;

                Intent intent = new Intent(context, NotificationAlarmReceiver.class);
                intent.setAction(NotificationAlarmReceiver.ACTION_FIRE_REMINDER);
                intent.putExtra("notif_id", (int) (notifId % 1000000));
                intent.putExtra("notif_title", title);
                intent.putExtra("notif_body", body);

                int flag = PendingIntent.FLAG_UPDATE_CURRENT;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    flag |= PendingIntent.FLAG_IMMUTABLE;
                }

                PendingIntent pendingIntent = PendingIntent.getBroadcast(
                    context,
                    (int) (notifId % 1000000),
                    intent,
                    flag
                );

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, epochMs, pendingIntent);
                } else {
                    alarmManager.setExact(AlarmManager.RTC_WAKEUP, epochMs, pendingIntent);
                }
            } catch (Throwable t) {
                android.util.Log.e("ATOMA_NOTIF", "Failed to schedule native alarm", t);
            }
        }

        @JavascriptInterface
        public void cancelNativeReminder(long notifId) {
            try {
                AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
                if (alarmManager == null) return;

                Intent intent = new Intent(context, NotificationAlarmReceiver.class);
                intent.setAction(NotificationAlarmReceiver.ACTION_FIRE_REMINDER);

                int flag = PendingIntent.FLAG_UPDATE_CURRENT;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    flag |= PendingIntent.FLAG_IMMUTABLE;
                }

                PendingIntent pendingIntent = PendingIntent.getBroadcast(
                    context,
                    (int) (notifId % 1000000),
                    intent,
                    flag
                );

                alarmManager.cancel(pendingIntent);
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
