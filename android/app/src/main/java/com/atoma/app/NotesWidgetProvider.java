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

public class NotesWidgetProvider extends AppWidgetProvider {

    public static final String ACTION_SYNC_NOTES = "com.atoma.app.ACTION_SYNC_NOTES";

    @Override
    public void onReceive(Context context, Intent intent) {
        super.onReceive(context, intent);
        String action = intent != null ? intent.getAction() : null;
        if (ACTION_SYNC_NOTES.equals(action) || AppWidgetManager.ACTION_APPWIDGET_UPDATE.equals(action)) {
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
                int[] ids = manager.getAppWidgetIds(new ComponentName(context, NotesWidgetProvider.class));
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
            RemoteViews views = new RemoteViews(context.getPackageName(), R.layout.widget_notes);

            int flag = PendingIntent.FLAG_UPDATE_CURRENT;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                flag |= PendingIntent.FLAG_IMMUTABLE;
            }

            // Read latest note from SharedPreferences
            SharedPreferences prefs = context.getSharedPreferences("AtomaNotesPrefs", Context.MODE_PRIVATE);
            long noteId = prefs.getLong("latest_note_id", 0);
            String title = prefs.getString("latest_note_title", "");
            String snippet = prefs.getString("latest_note_snippet", "");
            String dateStr = prefs.getString("latest_note_date", "");

            if (noteId > 0 && (!title.isEmpty() || !snippet.isEmpty())) {
                views.setTextViewText(R.id.widget_notes_title, !title.isEmpty() ? title : "Catatan Harian");
                views.setTextViewText(R.id.widget_notes_snippet, snippet);
                if (!dateStr.isEmpty()) {
                    views.setTextViewText(R.id.widget_notes_date, dateStr);
                }
                views.setViewVisibility(R.id.widget_notes_preview, View.VISIBLE);
                views.setViewVisibility(R.id.widget_notes_empty, View.GONE);

                // Click preview opens specific note in Journal
                Intent previewIntent = new Intent(context, MainActivity.class);
                previewIntent.setAction(Intent.ACTION_VIEW);
                previewIntent.setData(Uri.parse("https://atomaroute.com/journal?id=" + noteId));
                previewIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                PendingIntent pendingPreview = PendingIntent.getActivity(context, 3010, previewIntent, flag);
                views.setOnClickPendingIntent(R.id.widget_notes_preview, pendingPreview);
            } else {
                views.setViewVisibility(R.id.widget_notes_preview, View.GONE);
                views.setViewVisibility(R.id.widget_notes_empty, View.VISIBLE);
            }

            // New Note Entry
            Intent newNoteIntent = new Intent(context, MainActivity.class);
            newNoteIntent.setAction(Intent.ACTION_VIEW);
            newNoteIntent.setData(Uri.parse("https://atomaroute.com/journal?action=new"));
            newNoteIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            PendingIntent pendingNew = PendingIntent.getActivity(context, 3001, newNoteIntent, flag);
            views.setOnClickPendingIntent(R.id.btn_new_note, pendingNew);

            // Open Journal Page
            Intent journalIntent = new Intent(context, MainActivity.class);
            journalIntent.setAction(Intent.ACTION_VIEW);
            journalIntent.setData(Uri.parse("https://atomaroute.com/journal"));
            journalIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            PendingIntent pendingJournal = PendingIntent.getActivity(context, 3002, journalIntent, flag);
            views.setOnClickPendingIntent(R.id.btn_open_journal, pendingJournal);
            views.setOnClickPendingIntent(R.id.widget_notes_root, pendingJournal);

            appWidgetManager.updateAppWidget(appWidgetId, views);
        } catch (Throwable t) {
            android.util.Log.e("ATOMA_WIDGET", "Failed to update Notes widget", t);
        }
    }
}