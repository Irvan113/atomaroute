package com.atoma.app;

import android.app.PendingIntent;
import android.appwidget.AppWidgetManager;
import android.appwidget.AppWidgetProvider;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Build;
import android.os.SystemClock;
import android.widget.RemoteViews;

public class PomodoroWidgetProvider extends AppWidgetProvider {

    public static final String ACTION_TOGGLE_TIMER = "com.atoma.app.ACTION_TOGGLE_TIMER";
    public static final String ACTION_RESET_TIMER = "com.atoma.app.ACTION_RESET_TIMER";
    public static final long POMODORO_DURATION_MS = 25 * 60 * 1000L; // 25 Menit

    @Override
    public void onReceive(Context context, Intent intent) {
        super.onReceive(context, intent);
        String action = intent != null ? intent.getAction() : null;

        if (ACTION_TOGGLE_TIMER.equals(action)) {
            SharedPreferences prefs = context.getSharedPreferences("AtomaPomodoroPrefs", Context.MODE_PRIVATE);
            boolean isRunning = prefs.getBoolean("is_running", false);
            long now = SystemClock.elapsedRealtime();

            if (!isRunning) {
                long remaining = prefs.getLong("remaining_ms", POMODORO_DURATION_MS);
                if (remaining <= 0) remaining = POMODORO_DURATION_MS;
                long targetEndTime = now + remaining;

                prefs.edit()
                    .putBoolean("is_running", true)
                    .putLong("target_end_elapsed", targetEndTime)
                    .apply();
            } else {
                long targetEndTime = prefs.getLong("target_end_elapsed", now);
                long remaining = Math.max(0, targetEndTime - now);

                prefs.edit()
                    .putBoolean("is_running", false)
                    .putLong("remaining_ms", remaining)
                    .apply();
            }
            updateAllWidgets(context);
            if (MainActivity.instanceRef != null && MainActivity.instanceRef.get() != null) {
                MainActivity.instanceRef.get().syncNativePomodoroToWeb();
            }

        } else if (ACTION_RESET_TIMER.equals(action)) {
            SharedPreferences prefs = context.getSharedPreferences("AtomaPomodoroPrefs", Context.MODE_PRIVATE);
            prefs.edit()
                .putBoolean("is_running", false)
                .putLong("remaining_ms", POMODORO_DURATION_MS)
                .apply();
            updateAllWidgets(context);
            if (MainActivity.instanceRef != null && MainActivity.instanceRef.get() != null) {
                MainActivity.instanceRef.get().syncNativePomodoroToWeb();
            }

        } else if (AppWidgetManager.ACTION_APPWIDGET_UPDATE.equals(action)) {
            updateAllWidgets(context);
        }
    }

    @Override
    public void onUpdate(Context context, AppWidgetManager appWidgetManager, int[] appWidgetIds) {
        for (int appWidgetId : appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId);
        }
    }

    public static void updateAllWidgets(Context context) {
        try {
            AppWidgetManager manager = AppWidgetManager.getInstance(context);
            if (manager != null) {
                int[] ids = manager.getAppWidgetIds(new ComponentName(context, PomodoroWidgetProvider.class));
                if (ids != null && ids.length > 0) {
                    for (int id : ids) {
                        updateAppWidget(context, manager, id);
                    }
                }
            }
        } catch (Throwable ignored) {}
    }

    static void updateAppWidget(Context context, AppWidgetManager appWidgetManager, int appWidgetId) {
        try {
            RemoteViews views = new RemoteViews(context.getPackageName(), R.layout.widget_pomodoro);

            int flag = PendingIntent.FLAG_UPDATE_CURRENT;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                flag |= PendingIntent.FLAG_IMMUTABLE;
            }

            SharedPreferences prefs = context.getSharedPreferences("AtomaPomodoroPrefs", Context.MODE_PRIVATE);
            boolean isRunning = prefs.getBoolean("is_running", false);
            long remainingMs = prefs.getLong("remaining_ms", POMODORO_DURATION_MS);
            long now = SystemClock.elapsedRealtime();

            // Setup native Chronometer countdown
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                views.setChronometerCountDown(R.id.widget_pomodoro_chronometer, true);
            }

            if (isRunning) {
                long targetEndTime = prefs.getLong("target_end_elapsed", now + remainingMs);
                if (targetEndTime <= now) {
                    // Timer reached zero
                    prefs.edit()
                        .putBoolean("is_running", false)
                        .putLong("remaining_ms", POMODORO_DURATION_MS)
                        .apply();
                    isRunning = false;
                    long resetBase = SystemClock.elapsedRealtime() + POMODORO_DURATION_MS;
                    views.setChronometer(R.id.widget_pomodoro_chronometer, resetBase, null, false);
                    views.setTextViewText(R.id.btn_pomodoro_toggle, "Mulai");
                    views.setTextViewText(R.id.widget_pomodoro_status, "SELESAI");
                } else {
                    views.setChronometer(R.id.widget_pomodoro_chronometer, targetEndTime, null, true);
                    views.setTextViewText(R.id.btn_pomodoro_toggle, "Jeda");
                    views.setTextViewText(R.id.widget_pomodoro_status, "BERJALAN");
                }
            } else {
                long pausedBase = SystemClock.elapsedRealtime() + remainingMs;
                views.setChronometer(R.id.widget_pomodoro_chronometer, pausedBase, null, false);
                views.setTextViewText(R.id.btn_pomodoro_toggle, "Mulai");
                views.setTextViewText(R.id.widget_pomodoro_status, "FOKUS 25M");
            }

            // Button 1: Toggle Broadcast Intent
            Intent toggleIntent = new Intent(context, PomodoroWidgetProvider.class);
            toggleIntent.setAction(ACTION_TOGGLE_TIMER);
            PendingIntent pendingToggle = PendingIntent.getBroadcast(context, 2001, toggleIntent, flag);
            views.setOnClickPendingIntent(R.id.btn_pomodoro_toggle, pendingToggle);

            // Button 2: Reset Broadcast Intent
            Intent resetIntent = new Intent(context, PomodoroWidgetProvider.class);
            resetIntent.setAction(ACTION_RESET_TIMER);
            PendingIntent pendingReset = PendingIntent.getBroadcast(context, 2002, resetIntent, flag);
            views.setOnClickPendingIntent(R.id.btn_pomodoro_reset, pendingReset);

            // Root click opens Pomodoro in app
            Intent openIntent = new Intent(context, MainActivity.class);
            openIntent.setAction(Intent.ACTION_VIEW);
            openIntent.setData(Uri.parse("https://atomaroute.com/tasks?focus=pomodoro"));
            openIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            PendingIntent pendingOpen = PendingIntent.getActivity(context, 2003, openIntent, flag);
            views.setOnClickPendingIntent(R.id.widget_pomodoro_root, pendingOpen);

            appWidgetManager.updateAppWidget(appWidgetId, views);
        } catch (Throwable t) {
            android.util.Log.e("ATOMA_WIDGET", "Failed to update Pomodoro widget", t);
        }
    }
}
