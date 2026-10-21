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

import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.Scanner;

public class TodoWidgetProvider extends AppWidgetProvider {

    public static final String ACTION_SYNC_TODOS = "com.atoma.app.ACTION_SYNC_TODOS";
    public static final String ACTION_TOGGLE_TASK = "com.atoma.app.ACTION_TOGGLE_TASK";

    @Override
    public void onReceive(Context context, Intent intent) {
        super.onReceive(context, intent);
        String action = intent != null ? intent.getAction() : null;

        if (ACTION_TOGGLE_TASK.equals(action)) {
            long taskId = intent.getLongExtra("extra_task_id", -1L);
            if (taskId > 0) {
                // 1. Optimistic instant local update (0ms UI latency on homescreen)
                SharedPreferences prefs = context.getSharedPreferences("AtomaWidgetPrefs", Context.MODE_PRIVATE);
                String tasksJsonStr = prefs.getString("today_tasks_json", "[]");
                try {
                    JSONArray arr = new JSONArray(tasksJsonStr);
                    JSONArray newArr = new JSONArray();
                    for (int i = 0; i < arr.length(); i++) {
                        JSONObject obj = arr.getJSONObject(i);
                        if (obj.optLong("id") != taskId) {
                            newArr.put(obj);
                        }
                    }
                    int newCount = Math.max(0, prefs.getInt("remaining_count", 1) - 1);
                    prefs.edit()
                        .putString("today_tasks_json", newArr.toString())
                        .putInt("remaining_count", newCount)
                        .apply();
                } catch (Exception ignored) {}

                updateAllWidgets(context);

                // 2. Dispatch asynchronous API sync with session cookie
                dispatchToggleTaskApi(context, taskId);
            }
        } else if (ACTION_SYNC_TODOS.equals(action) || AppWidgetManager.ACTION_APPWIDGET_UPDATE.equals(action)) {
            updateAllWidgets(context);
        }
    }

    private static void dispatchToggleTaskApi(final Context context, final long taskId) {
        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    URL url = new URL("https://atomaroute.com/api/widget/toggle-task");
                    HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                    conn.setRequestMethod("POST");
                    conn.setRequestProperty("Content-Type", "application/json");
                    conn.setRequestProperty("Accept", "application/json");
                    conn.setRequestProperty("X-Requested-With", "XMLHttpRequest");
                    conn.setConnectTimeout(8000);
                    conn.setReadTimeout(8000);
                    conn.setDoOutput(true);

                    // Retrieve stored session cookies from WebView CookieManager
                    String cookie = android.webkit.CookieManager.getInstance().getCookie("https://atomaroute.com");
                    if (cookie != null && !cookie.isEmpty()) {
                        conn.setRequestProperty("Cookie", cookie);
                    }

                    // Extract XSRF-TOKEN from cookie if present
                    if (cookie != null) {
                        for (String part : cookie.split(";")) {
                            String trimmed = part.trim();
                            if (trimmed.startsWith("XSRF-TOKEN=")) {
                                String xsrfToken = URLDecoder.decode(trimmed.substring("XSRF-TOKEN=".length()), "UTF-8");
                                conn.setRequestProperty("X-XSRF-TOKEN", xsrfToken);
                                break;
                            }
                        }
                    }

                    String jsonBody = "{\"task_id\":" + taskId + "}";
                    byte[] postData = jsonBody.getBytes(StandardCharsets.UTF_8);
                    try (OutputStream os = conn.getOutputStream()) {
                        os.write(postData);
                    }

                    int responseCode = conn.getResponseCode();
                    if (responseCode >= 200 && responseCode < 300) {
                        InputStream is = conn.getInputStream();
                        Scanner s = new Scanner(is).useDelimiter("\\A");
                        String resp = s.hasNext() ? s.next() : "";
                        JSONObject respObj = new JSONObject(resp);
                        if (respObj.optBoolean("success", false)) {
                            int remaining = respObj.optInt("remaining_count", -1);
                            if (remaining >= 0) {
                                SharedPreferences prefs = context.getSharedPreferences("AtomaWidgetPrefs", Context.MODE_PRIVATE);
                                prefs.edit().putInt("remaining_count", remaining).apply();
                            }
                        }
                    }
                } catch (Throwable t) {
                    android.util.Log.w("ATOMA_WIDGET", "Task toggle sync error: " + t.getMessage());
                } finally {
                    updateAllWidgets(context);
                }
            }
        }).start();
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

            // Indonesian Day and Date header
            try {
                SimpleDateFormat sdf = new SimpleDateFormat("EEEE, d MMMM", new Locale("id", "ID"));
                String dateStr = sdf.format(new Date());
                views.setTextViewText(R.id.widget_todo_date, dateStr);
            } catch (Exception e) {
                views.setTextViewText(R.id.widget_todo_date, "Hari ini");
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
                int[] checkIds = {R.id.todo_item_1_check, R.id.todo_item_2_check, R.id.todo_item_3_check};
                int[] titleIds = {R.id.todo_item_1_title, R.id.todo_item_2_title, R.id.todo_item_3_title};
                int[] timeIds = {R.id.todo_item_1_time, R.id.todo_item_2_time, R.id.todo_item_3_time};

                try {
                    JSONArray tasksArray = new JSONArray(tasksJsonStr);
                    int totalItems = tasksArray.length();

                    for (int i = 0; i < 3; i++) {
                        if (i < totalItems) {
                            JSONObject task = tasksArray.getJSONObject(i);
                            long taskId = task.optLong("id", -1L);
                            String title = task.optString("title", "Tugas");
                            String time = task.optString("time", "");
                            if (time.isEmpty()) {
                                time = "Hari ini";
                            } else if (!time.startsWith("🕒")) {
                                time = "🕒 " + time;
                            }

                            views.setTextViewText(titleIds[i], title);
                            views.setTextViewText(timeIds[i], time);
                            views.setImageViewResource(checkIds[i], R.drawable.ic_widget_checkbox_uncheck);

                            // Interactive Checkbox Click PendingIntent -> ACTION_TOGGLE_TASK
                            if (taskId > 0) {
                                Intent toggleIntent = new Intent(context, TodoWidgetProvider.class);
                                toggleIntent.setAction(ACTION_TOGGLE_TASK);
                                toggleIntent.putExtra("extra_task_id", taskId);
                                PendingIntent pendingToggle = PendingIntent.getBroadcast(
                                    context,
                                    (int) (taskId % 100000),
                                    toggleIntent,
                                    flag
                                );
                                views.setOnClickPendingIntent(checkIds[i], pendingToggle);

                                // Title click opens tasks page
                                Intent taskIntent = new Intent(context, MainActivity.class);
                                taskIntent.setAction(Intent.ACTION_VIEW);
                                taskIntent.setData(Uri.parse("https://atomaroute.com/tasks"));
                                taskIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                                PendingIntent pendingTask = PendingIntent.getActivity(
                                    context,
                                    (int) (taskId % 100000) + 50000,
                                    taskIntent,
                                    flag
                                );
                                views.setOnClickPendingIntent(titleIds[i], pendingTask);
                            }

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

            // Open Tasks Page Button
            Intent openIntent = new Intent(context, MainActivity.class);
            openIntent.setAction(Intent.ACTION_VIEW);
            openIntent.setData(Uri.parse("https://atomaroute.com/tasks"));
            openIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            PendingIntent pendingOpen = PendingIntent.getActivity(context, 1001, openIntent, flag);
            views.setOnClickPendingIntent(R.id.btn_open_tasks, pendingOpen);
            views.setOnClickPendingIntent(R.id.widget_todo_root, pendingOpen);

            // Add Task Modal Button
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