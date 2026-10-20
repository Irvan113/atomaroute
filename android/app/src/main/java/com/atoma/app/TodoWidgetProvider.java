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

public class TodoWidgetProvider extends AppWidgetProvider {

    @Override
    public void onUpdate(Context context, AppWidgetManager appWidgetManager, int[] appWidgetIds) {
        for (int appWidgetId : appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId);
        }
    }

    static void updateAppWidget(Context context, AppWidgetManager appWidgetManager, int appWidgetId) {
        RemoteViews views = new RemoteViews(context.getPackageName(), R.layout.widget_todo);

        int flag = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flag |= PendingIntent.FLAG_IMMUTABLE;
        }

        // Open Tasks Page
        Intent openIntent = new Intent(context, MainActivity.class);
        openIntent.setAction(Intent.ACTION_VIEW);
        openIntent.setData(Uri.parse("https://atomaroute.com/tasks"));
        openIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendingOpen = PendingIntent.getActivity(context, 1001, openIntent, flag);
        views.setOnClickPendingIntent(R.id.btn_open_tasks, pendingOpen);
        views.setOnClickPendingIntent(R.id.widget_todo_root, pendingOpen);

        // Add Task Modal
        Intent addIntent = new Intent(context, MainActivity.class);
        addIntent.setAction(Intent.ACTION_VIEW);
        addIntent.setData(Uri.parse("https://atomaroute.com/tasks?action=new"));
        addIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pendingAdd = PendingIntent.getActivity(context, 1002, addIntent, flag);
        views.setOnClickPendingIntent(R.id.btn_add_task, pendingAdd);

        appWidgetManager.updateAppWidget(appWidgetId, views);
    }
}
