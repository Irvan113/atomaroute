# 📋 ATOMA Changelog & Patch Notes

## [v1.0.1] - 2026-10-21 (07:34 – 08:21 WIB)

### 🎧 Audio & Notifikasi Kustom
- **Lossless Audio Preservation**: Menghapus resampler manual 22.05 kHz yang mendistorsi durasi audio kustom menjadi 1 detik. Audio MP3/WAV/OGG hingga 3 MB disimpan penuh dalam format terenkod aslinya ke `localStorage`.
- **Playback Alami**: Pemutaran audio kustom menggunakan native HTML5 Audio element via hardware decoder OS dengan sample rate asli 44.1/48 kHz.
- **Auto Fallback**: `notifyNow({ sound = null })` secara cerdas membunyikan audio kustom yang dikonfigurasi pengguna.

### 📱 Android Mobile & Sesi
- **Root Redirection**: Route `/` di `routes/web.php` kini langsung mengarahkan pengguna terautentikasi ke `/dashboard`, mengatasi persepsi "akun keluar sendiri / perlu auth lagi" saat APK dibuka.
- **Cookie Disk Flush**: Menambahkan hook lifecycle `onPause()`, `onStop()`, dan `onDestroy()` di `MainActivity.java` untuk mengeksekusi `CookieManager.getInstance().flush()`.
- **Persistent WebStorage**: Mengaktifkan `setDomStorageEnabled(true)` dan `setDatabaseEnabled(true)` pada WebView.

### ⏰ Notifikasi Alarm Android (Background & Offline)
- **Izin Runtime Android 13+**: Menambahkan permintaan izin runtime `POST_NOTIFICATIONS` via `ActivityCompat.requestPermissions()` di `MainActivity.java`.
- **Native AlarmManager Receiver**: Membuat `NotificationAlarmReceiver.java` dengan `AlarmManager.setExactAndAllowWhileIdle(RTC_WAKEUP)` pada Channel `IMPORTANCE_HIGH`. Notifikasi dijamin berbunyi tepat waktu meskipun APK ditutup atau HP dalam mode Doze.
- **Auto-Sync Reminders**: Endpoint `/api/widget/summary` menyertakan jadwal deadline hari ini (`reminders`) yang otomatis didaftarkan ke `AlarmManager` saat APK dibuka.

### 🧩 Homescreen AppWidgets
- **To-Do Widget Interaktif**: Menambahkan tombol checklist bulat (`ic_widget_checkbox_uncheck.xml` / `ic_widget_checkbox_check.xml`) dengan aksi `ACTION_TOGGLE_TASK` (0ms optimistic local feedback + background API sync ke `/api/widget/toggle-task`).
- **Keterangan Hari & Jam**: Menampilkan hari dan tanggal lokal Indonesia (misal: "Rabu, 21 Oktober") dan jam tugas ("🕒 14:00" / "Hari ini").
- **Notes Widget Preview**: Menampilkan kartu pratinjau judul, tanggal, dan cuplikan 3 baris catatan terakhir dengan aksi ketuk langsung membuka `/journal?id={id}`.

### 💻 Windows Desktop (.EXE) & CI/CD
- **Desktop Non-Intrusif**: Menghapus pemaksaan layar melompat ke depan (*unminimize* dan *always-on-top popup*). Notifikasi berjalan sebagai Native Windows Toast Banner di pojok kanan bawah layar.
- **Manual Build Only**: Trigger otomatis `push:` pada `.github/workflows/build-android.yml` dan `build-desktop.yml` dinonaktifkan. Build APK dan EXE hanya berjalan saat dipicu secara manual via `workflow_dispatch`.

### 🧪 Verifikasi & Integritas
- Seluruh 404/404 unit & feature test Laravel lulus 100% (2.503 assertions).
- Arsip patch server (`~/Desktop/atoma-patch-update.zip`) terverifikasi 0 file `.sqlite` (bebas risiko timpa database).
