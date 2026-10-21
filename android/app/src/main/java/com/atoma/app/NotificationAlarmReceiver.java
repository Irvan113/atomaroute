package com.atoma.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import androidx.core.app.NotificationCompat;

public class NotificationAlarmReceiver extends BroadcastReceiver {
    public static final String CHANNEL_ID = "atoma_reminders_channel";
    public static final String ACTION_FIRE_REMINDER = "com.atoma.app.ACTION_FIRE_REMINDER";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null) return;

        int notifId = intent.getIntExtra("notif_id", (int) System.currentTimeMillis());
        String title = intent.getStringExtra("notif_title");
        String body = intent.getStringExtra("notif_body");

        if (title == null || title.isEmpty()) {
            title = "⚠️ Pengingat Tugas ATOMA";
        }
        if (body == null || body.isEmpty()) {
            body = "Waktunya menyelesaikan target fokus Anda!";
        }

        NotificationManager manager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) return;

        // Create Channel if Android 8.0+ (Oreo)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "Pengingat & Alarm ATOMA",
                NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Notifikasi tepat waktu untuk deadline tugas dan reminder fokus");
            channel.enableLights(true);
            channel.enableVibration(true);
            manager.createNotificationChannel(channel);
        }

        // Click on notification opens MainActivity to Tasks page
        Intent tapIntent = new Intent(context, MainActivity.class);
        tapIntent.setAction(Intent.ACTION_VIEW);
        tapIntent.setData(Uri.parse("https://atomaroute.com/tasks"));
        tapIntent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);

        int flag = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flag |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent pendingTap = PendingIntent.getActivity(context, notifId, tapIntent, flag);

        Uri defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);

        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_widget_check)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setSound(defaultSoundUri)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setContentIntent(pendingTap);

        manager.notify(notifId, builder.build());
    }
}
