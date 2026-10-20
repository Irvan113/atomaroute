package com.atoma.app;

import android.app.PendingIntent;
import android.appwidget.AppWidgetManager;
import android.appwidget.AppWidgetProvider;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.widget.RemoteViews;
import com.atoma.app.R;

public class PomodoroWidgetProvider extends AppWidgetProvider {

    @Override
    public void onUpdate(Context context, AppWidgetManager appWidgetManager, int[] appWidgetIds) {
        for (int appWidgetId : appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId);
        }
    }

    static void updateAppWidget(Context context, AppWidgetManager appWidgetManager, int appWidgetId) {
        RemoteViews views = new RemoteViews(context.getPackageName(), R.layout.widget_pomodoro);

        int flag = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flag |= PendingIntent.FLAG_IMMUTABLE;
        }

        // Start Pomodoro Focus
        Intent startIntent = new Intent(context, MainActivity.class);
        startIntent.setAction(Intent.ACTION_VIEW);
        startIntent.setData(Uri.parse("https://atomaroute.com/tasks?action=pomodoro"));
        startIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendingStart = PendingIntent.getActivity(context, 2001, startIntent, flag);
        views.setOnClickPendingIntent(R.id.btn_start_pomodoro, pendingStart);
        views.setOnClickPendingIntent(R.id.widget_pomodoro_root, pendingStart);

        // Open Ambience / Playlist
        Intent musicIntent = new Intent(context, MainActivity.class);
        musicIntent.setAction(Intent.ACTION_VIEW);
        musicIntent.setData(Uri.parse("https://atomaroute.com/tasks?action=pomodoro&audio=1"));
        musicIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendingMusic = PendingIntent.getActivity(context, 2002, musicIntent, flag);
        views.setOnClickPendingIntent(R.id.btn_open_pomodoro, pendingMusic);

        appWidgetManager.updateAppWidget(appWidgetId, views);
    }
}
