# 📋 ATOMA Changelog & Patch Notes

## [v1.0.1.1] - 2026-10-21 (Perbaikan UI & Arsitektur Antarmuka Responsif)

### 📐 Eliminasi "Zona Mati" Responsivitas (768px – 1280px) & OS Display Scaling
- **Sidebar Rail Adaptif (`md:w-20 xl:w-72`)**: Sidebar desktop berubah menjadi icon-rail ramping (`w-20` / 80px) pada resolusi menengah (`768px` – `1279px`), yang merupakan breakpoint efektif laptop Windows dengan Scale & Layout 125%–150% serta tablet split-screen. Pada layar lebar (`≥ 1280px` / `xl:`), sidebar secara mulus melebar ke format penuh (`w-72` / 288px).
- **Container Queries Root (`@container` / `.atoma-main-container`)**: Kontainer konten utama `<main>` didaftarkan sebagai context `container-type: inline-size`, memastikan modularitas kartu tugas, statistik, dan widget beradaptasi terhadap sisa ruang nyata dan bukan total lebar monitor.
- **Compact Streak Pill**: Menyediakan indikator streak ringkas pada mode rail `md:` tepat di bawah avatar profil.

### 📱 Dynamic Viewport (`100dvh`), Safe Bottom Padding, & Keyboard Isolation
- **Dynamic Viewport Unit (`min-h-[100dvh] h-[100dvh]`)**: Menggantikan `h-screen` (`100vh`) pada root body untuk mengeliminasi pemotongan konten akibat kemunculan address bar dan bar navigasi dinamis pada browser Android WebView / Mobile Safari.
- **Safe Area Bottom Navigation (`.pb-safe-nav`)**: Standardisasi padding bawah konten utama sebesar `calc(4.5rem + env(safe-area-inset-bottom, 0px))` mencegah konten terbawah tertutup oleh Bottom Navigation Bar dan Dynamic FAB.
- **Suppression Virtual Keyboard**: Menambahkan media query cerdas `@media (max-height: 480px) and (hover: none) and (pointer: coarse)` yang otomatis menyembunyikan `.atoma-bottom-nav` dan `.atoma-floating-pomodoro` ketika keyboard virtual terbuka pada layar sentuh.

### ⚡ Komposit GPU & Eliminasi Anomali Sub-Piksel
- **Integer Pixel Hover Stabilization (`-2px`)**: Menggantikan pergeseran pecahan sub-piksel `-1.5px` dengan bilangan bulat `-2px` pada hover `.atoma-card`. Mencegah degradasi anti-aliasing (ClearType) menjadi grayscale blur pada monitor 1080p dengan DPR 1.0.
- **Hardware Layer Acceleration**: Menambahkan `transform: translateZ(0)` dan `backface-visibility: hidden` pada `.atoma-card` untuk isolasi render layer GPU independen.
- **View Transitions Blur Bypass**: Menonaktifkan `backdrop-filter` secara otomatis selama fase `html:active-view-transition` guna mencegah GPU re-computation berat dan frame drop saat navigasi halaman.

### 👑 Kunci Geometri Kosmetik Avatar RPG (Discord & Steam Standard)
- **Constraint Aspect-Ratio (`aspect-square shrink-0`)**: Mengunci seluruh kontainer avatar, bingkai (*frames*), dan lencana (*equipped badges*) agar tidak mengalami distorsi pipih atau lonjong saat fleksibel di berbagai breakpoint.
- **Penempatan Mahkota Emas Berbasis Persentase**: Posisi mahkota `crown_gold.svg` diubah dari nilai piksel statis menjadi koordinat persentase relatif (`-top-[28%] left-1/2 -translate-x-1/2 w-[55%]`), menjamin posisi mahkota selalu menempel presisi di atas kepala avatar pada semua ukuran (mulai dari `32px` mobile header hingga `128px` halaman profil publik).

### ⏱️ Eliminasi Jitter Visual (*Micro-Layout Shift*)
- **Tipografi Tabular Numbers (`tabular-nums font-mono`)**: Menerapkan OpenType feature `tnum` pada jam real-time header, hitung mundur Pomodoro timer (baik modal utama maupun mini widget melayang), hitung mundur settlement XP harian, streak counter, dan indikator koin toko. Angka bergerak cepat tidak lagi menyebabkan pergeseran horizontal (*jitter*) pada kontainer.

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
