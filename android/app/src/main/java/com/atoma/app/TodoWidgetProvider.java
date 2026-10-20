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
import android.view.View;
import android.widget.RemoteViews;
import org.json.JSONArray;
import org.json.JSONObject;

public class TodoWidgetProvider extends AppWidgetProvider {

    public static final String ACTION_SYNC_TODOS = "com.atoma.app.ACTION_SYNC_TODOS";

    @Override
    public void onReceive(Context context, Intent intent) {
        super.onReceive(context, intent);
        String action = intent != null ? intent.getAction() : null;
        if (ACTION_SYNC_TODOS.equals(action) || AppWidgetManager.ACTION_APPWIDGET_UPDATE.equals(action)) {
            AppWidgetManager manager = AppWidgetManager.getInstance(context);
            if (manager != null) {
                int[] ids = manager.getAppWidgetIds(new ComponentName(context, TodoWidgetProvider.class));
                if (ids != null && ids.length > 0) {
                    onUpdate(context, manager, ids);
                }
            }
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
                int[] ids = manager.getAppWidgetIds(new ComponentName(context, TodoWidgetProvider.class));
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
            RemoteViews views = new RemoteViews(context.getPackageName(), R.layout.widget_todo);

            int flag = PendingIntent.FLAG_UPDATE_CURRENT;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                flag |= PendingIntent.FLAG_IMMUTABLE;
            }

            // Read dynamic tasks from SharedPreferences
            SharedPreferences prefs = context.getSharedPreferences("AtomaWidgetPrefs", Context.MODE_PRIVATE);
            int remainingCount = prefs.getInt("remaining_count", 0);
            String tasksJsonStr = prefs.getString("today_tasks_json", "[]");

            if (remainingCount <= 0) {
                views.setTextViewText(R.id.widget_todo_counter, "Selesai");
                views.setViewVisibility(R.id.widget_todo_empty, View.VISIBLE);
                views.setViewVisibility(R.id.widget_todo_list_container, View.GONE);
            } else {
                views.setTextViewText(R.id.widget_todo_counter, remainingCount + " Tugas");
                views.setViewVisibility(R.id.widget_todo_empty, View.GONE);
                views.setViewVisibility(R.id.widget_todo_list_container, View.VISIBLE);

                int[] layoutIds = {R.id.todo_item_1_layout, R.id.todo_item_2_layout, R.id.todo_item_3_layout};
                int[] titleIds = {R.id.todo_item_1_title, R.id.todo_item_2_title, R.id.todo_item_3_title};
                int[] timeIds = {R.id.todo_item_1_time, R.id.todo_item_2_time, R.id.todo_item_3_time};

                try {
                    JSONArray tasksArray = new JSONArray(tasksJsonStr);
                    int totalItems = tasksArray.length();

                    for (int i = 0; i < 3; i++) {
                        if (i < totalItems) {
                            JSONObject task = tasksArray.getJSONObject(i);
                            String title = task.optString("title", "Tugas");
                            String time = task.optString("time", "");

                            views.setTextViewText(titleIds[i], title);
                            views.setTextViewText(timeIds[i], time);
                            views.setViewVisibility(layoutIds[i], View.VISIBLE);
                        } else {
                            views.setViewVisibility(layoutIds[i], View.GONE);
                        }
                    }
                } catch (Exception e) {
                    views.setViewVisibility(R.id.todo_item_1_layout, View.GONE);
                    views.setViewVisibility(R.id.todo_item_2_layout, View.GONE);
                    views.setViewVisibility(R.id.todo_item_3_layout, View.GONE);
                }
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
        } catch (Throwable t) {
            android.util.Log.e("ATOMA_WIDGET", "Failed to update Todo widget", t);
        }
    }
}
