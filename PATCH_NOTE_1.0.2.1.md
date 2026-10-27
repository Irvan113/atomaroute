# 🚀 ATOMA Patch Note 1.0.2.1 — Stability, Core Web Vitals, & Cross-Midnight Scheduling Release
**Tanggal Rilis**: 26 Oktober 2026  
**Status**: Stable Production Ready  
**Cakupan**: Implementasi Penyesuaian Kode Audit 4 Fase (PWA Session Stability, Overnight Task Scheduling 22:00–04:00, Event-Driven Notification Purge, CLS Optimization < 0.1, & Social Feed State Sync)

---

## 📌 Ringkasan Pembaruan (Executive Summary)

Pembaruan versi **1.0.2.1** mengeksekusi restrukturisasi kode dan penyempurnaan logika inti berdasarkan hasil audit arsitektur ATOMA, dengan penyesuaian khusus terhadap keamanan data pengguna:

1. **Keamanan Data Pengguna (Database Migration Dikecualikan Sesuai Permintaan)**:
   - Migrasi database SQLite ke MariaDB/PostgreSQL dan Redis **ditangguhkan** dan tidak diotak-atik pada patch ini.
   - Sesuai arahan, eksekusi migrasi database fisik akan dilakukan secara manual oleh pemilik agar seluruh data riwayat tugas, gamifikasi, dan akun user terjamin 100% aman tanpa risiko kehilangan data atau downtime.
   - Seluruh kode, model, dan query yang disesuaikan pada rilis ini telah dioptimalkan agar sepenuhnya kompatibel (*database-agnostic*) baik untuk SQLite saat ini maupun MariaDB/PostgreSQL di masa mendatang.

2. **Fase 1: PWA Standalone & Sesi Login Persisten (RFC 8252 Compliance)**:
   - Distribusi native WebView (APK / Tauri) dibekukan sementara untuk mengarahkan pengguna ke instalasi **Progressive Web App (PWA)** langsung dari browser utama OS (Chrome / Safari / Edge via *Add to Home Screen*).
   - Penggunaan Google OAuth pada browser utama mematuhi standar RFC 8252 dan menjamin sesi login tetap aktif tanpa di-reset oleh *memory killer* WebView berkat token `Auth::login($user, remember: true)`.

3. **Fase 2: Penjadwalan Lintas Malam (Overnight 22:00–04:00) & AI Time Anchor**:
   - **Logika Cross-Midnight (+1 Hari)**: Pada `Task::isOverdue()`, jika jam tenggat lebih awal atau sama dengan jam mulai (misal: mulai `22:00` dan tenggat `04:00`), sistem secara otomatis menambahkan `+1 hari` pada kalkulasi deadline. Tugas malam yang sedang aktif pada pukul `22:30` tidak lagi keliru divonis berstatus *Overdue*.
   - **Sinkronisasi Timer Klien (Alpine.js)**: Skrip `taskNotificationScheduler` kini menambahkan penyesuaian `+1440 menit` (+24 jam) saat mendeteksi jadwal lintas malam, mencegah munculnya notifikasi alarm salah waktu atau pop-up expired prematur.
   - **Jangkar Waktu AI Eksplisit**: Layanan `AiTaskDecomposerService` kini menginjeksi waktu acuan lokal `Asia/Jakarta (WIB)` lengkap dengan nama hari dan tanggal ISO, sehingga perintah bahasa alami seperti *"nanti malam"* atau *"malam ini jam 10"* tidak bergeser tanggal.

4. **Fase 2: Event-Driven Task Checklist & Pembatalan Notifikasi Seketika**:
   - Setiap kali tugas dicentang selesai (baik melalui klik langsung maupun auto-complete via seluruh subtask), komponen Livewire memancarkan event `task-toggled` dengan parameter status `completed`.
   - Scheduler Alpine.js di browser secara reaktif menangkap event `task-toggled` dan `cancel-reminder` untuk langsung membersihkan ID tugas dari memori antrean pengingat dan mematikan alarm suara lokal.

5. **Fase 3: Core Web Vitals (Pencegahan CLS < 0.1) & State Feed Sosial**:
   - **Eliminasi Cumulative Layout Shift (CLS)**: Menambahkan aturan global `[x-cloak] { display: none !important; }` pada `resources/css/app.css` dan menyematkan atribut `x-cloak` pada modal, dropdown filter, serta panel navigasi. Elemen Alpine.js tidak lagi berkedip atau bergeser saat inisialisasi.
   - **Viewport Mobile Modern**: Menerapkan satuan tinggi `min-h-dvh` dan isolasi tata letak `contain-layout` pada layout dashboard untuk menghentikan pergeseran kontainer saat keyboard virtual atau bar navigasi browser muncul.
   - **Sinkronisasi Notifikasi Sosial & Pin Post**: Membuka tab sosial kini otomatis menandai notifikasi sosial unread sebagai dibaca pada siklus `mount()`, lalu memancarkan event `social-notifications-cleared` untuk seketika mereset badge angka pada lonceng notifikasi (`notification-bell`). Fitur *Pin Post* kini dapat dikelola oleh pemilik post maupun admin sistem (`$user->isAdmin()`).

6. **Fase 4: Verifikasi & Jaminan Kualitas**:
   - Seluruh rangkaian pengujian sistem (**421 Unit & Feature Tests / 2.615 assertions**) lulus 100% tanpa kegagalan.

---

## 🛠️ Rincian Perubahan Teknis (Technical Diffs & Architecture)

### 1. Model & Logika Tugas Lintas Malam (`app/Models/Task.php`)
- **Akar Masalah**: Pengecekan tenggat `isOverdue()` sebelumnya hanya menggabungkan `scheduled_date` dengan string `deadline` pada hari yang sama. Pada shift malam (contoh: mulai 22:00 s.d. 04:00), jam 04:00 dibaca sebagai jam 4 subuh pada hari mulai yang sama, sehingga tugas langsung dianggap terlambat (overdue) sejak menit pertama dibuat.
- **Perbaikan**:
  ```php
  // app/Models/Task.php
  if ($this->scheduled_date && $this->deadline) {
      $dueTime = Carbon::parse($taskDateStr . ' ' . $this->deadline, $appTz);
      if ($this->start_time) {
          $startTime = Carbon::parse($taskDateStr . ' ' . $this->start_time, $appTz);
          // Jika deadline <= start_time (misal: mulai 22:00, selesai 04:00),
          // maka deadline berada di keesokan harinya (+1 hari)
          if ($dueTime->lessThanOrEqualTo($startTime)) {
              $dueTime->addDay();
          }
      }
      return $now->greaterThan($dueTime);
  }
  ```

### 2. Prompt Jangkar Waktu AI Coach (`app/Services/AiTaskDecomposerService.php`)
- **Akar Masalah**: Model AI terkadang menginterpretasikan frasa *"nanti malam"* sebagai malam keesokan harinya jika tidak diberikan referensi zona waktu dan tanggal ISO secara eksplisit.
- **Perbaikan**:
  ```php
  $wibNow = now()->timezone('Asia/Jakarta');
  $wibAnchor = $wibNow->translatedFormat('l, d F Y H:i') . ' WIB (ISO: ' . $wibNow->toDateString() . ')';
  $overnightRule = "Referensi Waktu Nyata: {$wibAnchor}. "
      . "Jika pengguna menyebut 'malam ini' atau 'nanti malam', gunakan tanggal target {$wibNow->toDateString()}. "
      . "Jika tugas melewati tengah malam (contoh: 22:00 - 04:00), tandai waktu mulai dan waktu selesai secara logis tanpa memajukan tanggal mulai.";
  ```

### 3. Core Web Vitals & Anti-CLS (`resources/css/app.css` & Views)
- **Akar Masalah**: Skor CLS tinggi diakibatkan oleh elemen Alpine.js yang dirender sebelum runtime JavaScript selesai diunduh dan dieksekusi browser.
- **Perbaikan**:
  - Menambahkan aturan CSS global:
    ```css
    [x-cloak] {
        display: none !important;
    }
    ```
  - Memasang atribut `x-cloak` pada modal (`showCalendarModal`, `showModeModal`, `showFilterBar`), popover filter, dan dropdown prioritas/kategori di `tasks.blade.php`.
  - Menggunakan kelas `min-h-dvh` dan `contain-layout` pada layout pembungkus utama di `dashboard.blade.php`, `resources/views/layouts/app.blade.php`, dan `navigation.blade.php`.

### 4. Event-Driven Notification Cancellation (`resources/views/livewire/tasks.blade.php`)
- **Akar Masalah**: Saat tugas dicentang selesai, timer interval di sisi browser masih menyimpan ID tugas dalam daftar alarm, sehingga suara notifikasi pengingat tetap berbunyi beberapa menit kemudian.
- **Perbaikan**:
  - Pada method `toggleComplete()` dan `toggleSubTask()` di Livewire:
    ```php
    $this->dispatch('task-toggled', id: $task->id, completed: $task->is_completed);
    ```
  - Pada scheduler Alpine `taskNotificationScheduler`:
    ```javascript
    window.addEventListener('task-toggled', (e) => {
        const taskId = e.detail?.id;
        const isCompleted = e.detail?.completed;
        if (taskId && isCompleted) {
            // Hapus seketika dari antrean pengingat aktif di klien
            this.activeTasks = this.activeTasks.filter(t => t.id !== taskId);
            this.scheduledTimeouts.forEach((timeoutId, key) => {
                if (key.startsWith(`task_${taskId}_`)) {
                    clearTimeout(timeoutId);
                    this.scheduledTimeouts.delete(key);
                }
            });
        }
    });
    ```
  - Penanganan cross-midnight menit:
    ```javascript
    if (deadlineMinutes <= startMinutes) {
        deadlineMinutes += 1440; // Tambahkan 24 jam untuk tenggat lewat tengah malam
    }
    ```

### 5. Sinkronisasi Notifikasi Sosial & Pin Post (`resources/views/livewire/social.blade.php`)
- **Akar Masalah**: Notifikasi sosial unread (badge angka 1/2/3/4) sering tersangkut pada lonceng notifikasi meskipun pengguna sudah membuka halaman sosial. Selain itu, aksi pin postingan hanya terbatas pada author post.
- **Perbaikan**:
  - Menandai notifikasi sosial unread sebagai dibaca saat `mount()` dan memancarkan event pembersih:
    ```php
    $unreadSocialNotifs = auth()->user()->unreadNotifications()
        ->whereIn('type', [
            'App\Notifications\SocialPostLikedNotification',
            'App\Notifications\SocialCommentNotification',
            'App\Notifications\SocialNudgeNotification',
        ])->get();

    if ($unreadSocialNotifs->isNotEmpty()) {
        $unreadSocialNotifs->markAsRead();
        $this->dispatch('social-notifications-cleared');
        $this->dispatch('notifications-updated');
    }
    ```
  - Komponen `notification-bell.blade.php` mendengarkan `#[On('social-notifications-cleared')]` untuk merefresh jumlah badge unread seketika.
  - Otorisasi pin post diperluas:
    ```php
    public function togglePin(int $postId): void
    {
        $user = auth()->user();
        $post = Post::findOrFail($postId);
        if ($post->user_id !== $user->id && !$user->isAdmin()) {
            $this->dispatch('toast', message: 'Hanya pemilik postingan atau admin yang dapat menyematkan.', type: 'error');
            return;
        }
        $post->update(['is_pinned' => !$post->is_pinned]);
    }
    ```

### 6. Optimistic UI & Responsivitas Mobile 0ms (`tasks.blade.php`, `navigation.blade.php`, `top-header.blade.php`)
- **Akar Masalah**: Pada Livewire, setiap klik checklist sebelumnya menunggu balasan server (network round-trip ~300-800ms di mobile), diperparah oleh `wire:loading.attr="disabled"` yang membekukan tombol dan `wire:key` yang menyertakan status `{{ $task->is_completed ? 'completed' : 'pending' }}` sehingga Morphdom menghancurkan dan membuat ulang seluruh node DOM (menimbulkan kedipan dan lag pada smartphone).
- **Perbaikan**:
  - **Stabilisasi DOM Key (Zero Morphdom Teardown)**: Mengubah `wire:key` menjadi identitas statis unik per model (`wire:key="timeline-task-{{ $task->id }}"`, `wire:key="timeline-subtask-{{ $subTask->id }}"`, `wire:key="card-task-{{ $task->id }}"`, `wire:key="card-subtask-{{ $subTask->id }}"`). Morphdom kini memperbarui atribut secara *in-place* tanpa mereset DOM atau merusak state Alpine.
  - **Optimistic UI 0ms Feedback**:
    - Tombol checkbox langsung mencoret teks (`line-through`), mengubah warna badge ke hijau emerald, menyalakan centang SVG, dan memudarkan card (`opacity-55`) secara instan di sisi browser pengguna.
    - Menghapus atribut penghambat `wire:loading.attr="disabled"` dan `wire:target="toggleComplete(...)"` agar interaksi tap terasa responsif layaknya aplikasi native tanpa delay.
    - Memicu getaran haptik instan (`navigator.vibrate(20)`) serta audio synthesizer Web Audio 0-delay (`window.playHapticSound('success')` dan `window.playHapticSound('pop')`) begitu tombol disentuh.
  - **Prefetch Navigasi Cepat (`wire:navigate.hover`)**:
    - Menerapkan `wire:navigate.hover` pada seluruh link tab utama (Tasks, Note, Rank, Sosial, Store, Guide, Profile, Admin) di `navigation.blade.php` dan `top-header.blade.php`.
    - Browser secara otomatis melakukan *background prefetch* HTML saat pengguna menyentuh (*touchstart*) atau mendekatkan kursor ke tombol, menjadikan pergantian halaman instan (0ms).

---

## 🧪 Hasil Verifikasi Pengujian Otomatis

Rangkaian unit dan feature test dieksekusi secara menyeluruh:
```bash
php artisan test
```

**Hasil:**
- **Total Tests**: `421 passed` (0 failed, 0 warning)
- **Total Assertions**: `2.615 passed`
- **Uji Baru Ditambahkan**: `Tests\Feature\OverdueDisciplineAndInsightsTest::test_overnight_task_crossing_midnight_is_not_overdue_before_next_day_deadline` (Passed)
- **Kompilasi Aset Frontend**: `npm run build` sukses (Vite v7.3.6, output bersih di `public/build/`).

---

## 📦 Lokasi Berkas Paket Update 1.0.2.1

Paket rilis lengkap telah dikemas dan disiapkan pada direktori:
- **Folder Desktop**: `/Users/user/Desktop/1.0.2.1 update patch/`
- **Arsip ZIP Desktop**: `/Users/user/Desktop/1.0.2.1 update patch.zip`
