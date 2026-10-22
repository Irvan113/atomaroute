# 📋 ATOMA Changelog & Patch Notes

## [v1.0.1.3] - 2026-10-22 (Zero-Latency Navigation, Persistent Auth, & WebView Main-Thread Stabilization)

> **Catatan Rilis:** Patch v1.0.1.3 menuntaskan kendala navigasi lambat, jeda ikon Dynamic Center FAB hingga 10 detik, auto-redirect landing page pada APK setelah swipe/kill task, kebocoran background SpeechRecognition, dan pemblokiran PHP session lock akibat prefetch berlebih serta polling tidak terkendali.

### 🔐 Persistent Auth & Zero-Bounce APK Launch
- **Server-Side Auth Auto-Redirect**: Route `/` (welcome page) secara instan mengecek status autentikasi di server. Pengguna terdaftar langsung dialihkan ke `/dashboard` (`return redirect()->route('dashboard')`), mengeliminasi tampilan landing page/promosi saat APK dibuka kembali.
- **PWA & APK Entrypoint Alignment**: Mengunci `start_url` pada `public/manifest.json` ke `"/dashboard"`.
- **Extended Session Lifetime (30 Hari)**: Mengubah fallback default sesi `SESSION_LIFETIME` pada `config/session.php` dari 120 menit menjadi `43200` menit (30 hari) dengan `expire_on_close => false`.
- **Remember-Me Default**: Mengaktifkan `$remember = true` secara default pada form autentikasi (`LoginForm.php`) dan token exchange SSO, menjaga token cookie autentikasi tetap tersimpan saat memori dibersihkan oleh OS.

### ⚡ 0ms Optimistic UI Navigation & Dynamic Center FAB
- **Instant Client-Side Tab Switching**: State tab aktif (`activeTab`) pada Sidebar dan Bottom Nav dikelola langsung oleh Alpine.js dengan event `@click` instan (0ms), merubah visual highlight tab tanpa menunggu siklus response HTTP dari server.
- **Zero-Latency Dynamic Center FAB**: Menghubungkan ikon tombol tengah Bottom Nav (FAB) langsung ke variabel reaktif `activeTab`. Pergantian dari ikon `+` (Tugas) ke ikon pensil (Catatan) atau balon percakapan (Sosial) terjadi dalam 0ms pada frame pertama saat tombol disentuh.
- **Micro-Shimmer Loading Feedback**: Menambahkan shimmer bar tipis di bibir atas Bottom Nav (`x-show="isNavigating" x-cloak`) yang aktif selama request `wire:navigate` terbang ke server, memberikan umpan balik visual elegan kepada pengguna.
- **Anti-Rage Click Protection**: Memasang guard pencegah spam klik berulang pada tab yang sama saat request sedang berlangsung (`if (activeTab === tab && isNavigating) e.preventDefault()`), mencegah pembatalan request berantai (*abort flood*) di sisi browser.

### 🎙️ SpeechRecognition Guard & Thread Cleanup
- **Isolasi Pemicu Suara**: Memastikan `SpeechRecognition` hanya dapat aktif melalui klik eksplisit pengguna pada tombol mikrofon, dan dilarang keras aktif pada siklus inisialisasi (`x-init`).
- **Banner Red Leak Prevention**: Menambahkan `x-cloak` pada banner indikator pendengar suara *"Mendengarkan suara kamu..."* dan mematikan instance mikrofon saat navigasi halaman berpindah (`livewire:navigating` hook).
- **Global Speech Cleanup**: Mendaftarkan listener global `document.addEventListener('livewire:navigating')` yang otomatis mengeksekusi `window.__atomaSpeechRecognition.abort()`, mencegah kebocoran mic stream dan freeze pada main-thread.

### 🧩 Optimasi Sub-Menu Catatan & Throttling Polling
- **Instant Sub-Tab Switch**: Tombol segmented tab di halaman Catatan (`Note`, `Keuangan`, `Flashcard`) beralih secara optimistik lokal dalam 0ms menggunakan Alpine.js (`currentTab = '...'`).
- **Shortest Delay Skeleton Loader**: Menambahkan skeleton visual instan (`wire:loading.delay.shortest`) pada kontainer kartu catatan/keuangan untuk menghilangkan persepsi freeze saat pergantian sub-tab.
- **Polling Throttling & Visibility Guard**: Mengonversi seluruh polling aktif di modul sosial dan obrolan menjadi `wire:poll.60s.visible` dan `wire:poll.10s.visible`, menghentikan background polling saat tab/aplikasi tidak terlihat dan mengeliminasi PHP session lock.
- **Script Data-Navigate-Once Enforcement**: Menyematkan atribut `data-navigate-once` pada modal perayaan prestasi (`achievement-celebration`), `achievement-modal`, `command-palette`, `performance-monitor`, dan pustaka eksternal `html2canvas` guna mencegah eksekusi skrip berulang setiap kali berpindah halaman.

## [v1.0.1.2] - 2026-10-21 (Cumulative Master Patch: Mobile, Desktop, UI & Performance)

> **Catatan Rilis:** Versi 1.0.1.2 merupakan penggabungan kumulatif (*cumulative master patch*) dari Patch 1.0.1 (Audio kustom, notifikasi offline Android AlarmManager, Homescreen AppWidgets, sesi webview, dan desktop background toast) serta Patch 1.0.1.1 (Sidebar rail adaptif, eliminasi dead zone 768px–1280px, dynamic viewport 100dvh, isolasi view transitions, persistent app shell, dan stabilisasi sub-piksel GPU).

### 📱 Android Mobile, Sesi, & Notifikasi Alarm Background (Offline)
- **Root Redirection & Sesi Langsung**: Route `/` langsung mengarahkan pengguna terautentikasi ke `/dashboard`, mengatasi persepsi akun keluar sendiri atau harus login ulang saat APK dibuka.
- **Cookie Disk Flush Lifecycle**: Hook lifecycle `onPause()`, `onStop()`, dan `onDestroy()` di `MainActivity.java` mengeksekusi `CookieManager.getInstance().flush()`.
- **Persistent WebStorage & Database**: Mengaktifkan `setDomStorageEnabled(true)` dan `setDatabaseEnabled(true)` pada WebView.
- **Izin Runtime Android 13+**: Permintaan izin runtime `POST_NOTIFICATIONS` via `ActivityCompat.requestPermissions()` di `MainActivity.java`.
- **Native AlarmManager Receiver**: `NotificationAlarmReceiver.java` dengan `AlarmManager.setExactAndAllowWhileIdle(RTC_WAKEUP)` pada Channel `IMPORTANCE_HIGH`. Notifikasi deadline berbunyi tepat waktu meski aplikasi ditutup atau perangkat dalam mode Doze.
- **Auto-Sync Reminders**: Sinkronisasi jadwal pengingat deadline hari ini dari endpoint `/api/widget/summary` saat APK dibuka.

### 🧩 Homescreen AppWidgets
- **To-Do Widget Interaktif**: Checklist interaktif (`ic_widget_checkbox_uncheck.xml` / `ic_widget_checkbox_check.xml`) dengan aksi `ACTION_TOGGLE_TASK` (0ms feedback optimistik lokal + background API sync ke `/api/widget/toggle-task`).
- **Keterangan Hari & Jam Lokal**: Menampilkan tanggal lokal bahasa Indonesia ("Rabu, 21 Oktober") dan jam tugas ("🕒 14:00" / "Hari ini").
- **Notes Widget Preview**: Kartu pratinjau judul, tanggal, dan cuplikan catatan dengan aksi ketuk langsung membuka `/journal?id={id}`.

### 💻 Windows Desktop (.EXE) & Pengalaman Non-Intrusif
- **Desktop Toast Non-Intrusif**: Menghapus pemaksaan jendela melompat ke depan (*unminimize* dan *always-on-top popup*). Notifikasi berjalan sebagai Native Windows Toast Banner di pojok kanan bawah.
- **System Tray Persistence**: Aplikasi diminimize ke System Tray saat tombol close ditekan agar sesi fokus Pomodoro dan pengingat tetap aktif di latar belakang.
- **Build Otomatis Terkontrol**: Menghindari build otomatis pada setiap commit dengan memindahkan trigger ke `workflow_dispatch` (build hanya berjalan saat diperintahkan).

### 🎧 Audio & Notifikasi Kustom
- **Lossless Audio Preservation**: Menghapus resampler manual 22.05 kHz yang mendistorsi durasi audio kustom menjadi 1 detik. Audio MP3/WAV/OGG hingga 3 MB disimpan penuh dalam format aslinya ke `localStorage`.
- **Playback Alami**: Pemutaran audio kustom menggunakan native HTML5 Audio element via hardware decoder OS dengan sample rate asli 44.1/48 kHz.
- **Auto Fallback**: `notifyNow({ sound = null })` cerdas membunyikan audio kustom yang dikonfigurasi pengguna.

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

### 🚀 Akselerasi Navigasi SPA & View Transitions Terisolasi (Zero Full Refresh Sensation)
- **Isolasi View Transitions ke Kontainer Main (`atoma-main-canvas`)**: Menghilangkan animasi transisi pada `::view-transition-old/new(root)` dan memindahkannya ke kanvas internal `.atoma-main-container`. Sidebar, Header, dan Bottom Nav tidak lagi berkedip (*flicker*) atau memudar saat pengguna berpindah menu.
- **Persistent App Shell (`@persist('atoma-navigation-shell')` & `@persist('atoma-top-header')`)**: Membungkus Sidebar dan Top Header dengan `@persist` Livewire v3 sehingga pohon DOM navigasi tidak dihancurkan (*unmount*) dan dibuat ulang di setiap klik halaman.
- **Reaktif State Navigasi Klien (Alpine.js Instant Highlighting)**: Memindahkan penentuan status menu aktif dari server-side `request()->routeIs()` ke state reaktif Alpine.js (`isActive(route)`) yang merespons event `livewire:navigated` dan `popstate` secara instan (0ms) tanpa menunggu respon SSR.
- **Eliminasi Prefetch Flood & PHP Session Lock**: Menghapus seluruh `wire:navigate.hover` dan menggantinya dengan standar `wire:navigate`. Mencegah penumpukan antrean HTTP request akibat sapuan kursor yang sebelumnya mengunci sesi PHP (*Session Locking*) dan menahan TTFB hingga 300ms–800ms.
- **Pembersihan Siklus Hidup Script Global (`data-navigate-once`)**: Menambahkan `data-navigate-once` pada seluruh tag `<script>` global (Synthesizer Web Audio, Platform Bridge Capacitor/Desktop, Echo Nudges, Service Worker, Dark Mode FOUC) agar tidak dieksekusi berulang atau mengakumulasi event listener di background.
- **Scroll Restoration Kontainer Internal**: Menyematkan reset `scrollTop = 0` pada `.atoma-main-container` di setiap event `livewire:navigated`.
- **Query Caching RPG Sidebar (60s)**: Mengoptimalkan pengecekan tugas belum selesai `hasUnfinishedTasksToday()` di Sidebar menggunakan cache memori 60 detik dengan invalidasi berbasis event `#[On('task-completed')]` dan `#[On('streak-updated')]`.

### 🧪 Verifikasi & Integritas
- Seluruh 404/404 unit & feature test Laravel lulus 100% (2.503 assertions).
- Arsip patch server (`~/Desktop/atoma-patch-update.zip`) terverifikasi 0 file `.sqlite` (bebas risiko timpa database).
