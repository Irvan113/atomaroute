package com.atoma.app;

import android.app.PendingIntent;
import android.appwidget.AppWidgetManager;
import android.appwidget.AppWidgetProvider;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.widget.RemoteViews;

public class NotesWidgetProvider extends AppWidgetProvider {

    @Override
    public void onUpdate(Context context, AppWidgetManager appWidgetManager, int[] appWidgetIds) {
        for (int appWidgetId : appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId);
        }
    }

    static void updateAppWidget(Context context, AppWidgetManager appWidgetManager, int appWidgetId) {
        RemoteViews views = new RemoteViews(context.getPackageName(), R.layout.widget_notes);

        int flag = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flag |= PendingIntent.FLAG_IMMUTABLE;
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
    }
}
