<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATOMA Database Maintenance & Cleaner</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: #0f172a; color: #f8fafc; padding: 30px 20px; line-height: 1.5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #334155; padding-bottom: 16px; }
        h1 { font-size: 22px; font-weight: 800; color: #38bdf8; display: flex; align-items: center; gap: 10px; }
        .badge { background: #0284c7; color: white; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #064e3b; color: #6ee7b7; border: 1px solid #059669; }
        .alert-error { background: #7f1d1d; color: #fca5a5; border: 1px solid #dc2626; }
        .info-box { background: #0f172a; border-left: 4px solid #38bdf8; padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; color: #94a3b8; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th { background: #0f172a; color: #94a3b8; text-align: left; padding: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #334155; }
        td { padding: 12px; border-bottom: 1px solid #334155; vertical-align: middle; }
        tr:hover { background: #243248; }
        .checkbox-cell { width: 45px; text-align: center; }
        input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #0284c7; }
        .user-name { font-weight: 700; color: #f1f5f9; }
        .user-email { color: #94a3b8; font-size: 12px; }
        .count-badge { background: #334155; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .btn-submit { background: #ef4444; color: white; border: none; padding: 14px 28px; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4); transition: all 0.2s; }
        .btn-submit:hover { background: #dc2626; transform: translateY(-1px); }
        .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #334155; }
        .hint { font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div>
                    <h1>🛠️ ATOMA Database Cleaner</h1>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Kelola dan hapus user dummy/pengujian tanpa perlu akses Terminal cPanel</p>
                </div>
                <span class="badge">SQLite WAL Mode</span>
            </div>

            @if($message)
                <div class="alert {{ $messageType === 'success' ? 'alert-success' : 'alert-error' }}">
                    <span>{{ $messageType === 'success' ? '✅' : '⚠️' }}</span>
                    <span>{{ $message }}</span>
                </div>
            @endif

            <div class="info-box">
                💡 <strong>Cara Pakai:</strong> Berikan <strong>tanda centang (✓)</strong> HANYA pada <strong>User Asli</strong> yang ingin Anda simpan.<br>
                Semua user yang <strong>TIDAK dicentang</strong> akan dihapus beserta seluruh task dan aktivitasnya. Sistem akan secara <strong>otomatis membuat backup SQLite</strong> sebelum pembersihan dimulai.
            </div>

            <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; background: #0f172a; padding: 14px; border-radius: 10px; border: 1px solid #334155; align-items: center;">
                <span style="font-size: 12px; color: #94a3b8; font-weight: 600;">🛠️ Perbaikan 1-Klik:</span>
                <form method="POST" action="?secret={{ $secret }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="secret" value="{{ $secret }}">
                    <input type="hidden" name="action" value="fix_storage">
                    <button type="submit" style="background: #0284c7; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s;">
                        🔗 Hubungkan / Perbaiki Storage Link (Foto Profil)
                    </button>
                </form>

                <form method="POST" action="?secret={{ $secret }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="secret" value="{{ $secret }}">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" style="background: #334155; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s;">
                        🧹 Bersihkan Cache (optimize:clear)
                    </button>
                </form>
            </div>

            <form method="POST" action="?secret={{ $secret }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus seluruh user yang TIDAK dicentang? Backup otomatis database akan dibuat sebelum proses penghapusan.')">
                @csrf
                <input type="hidden" name="secret" value="{{ $secret }}">

                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">Keep?</th>
                                <th>ID</th>
                                <th>User (Nama & Email)</th>
                                <th>Tasks</th>
                                <th>Catatan</th>
                                <th>Sesi Fokus</th>
                                <th>Terdaftar Pada</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $u)
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="keep_ids[]" value="{{ $u->id }}" id="user_{{ $u->id }}">
                                    </td>
                                    <td style="font-weight: 700; color: #38bdf8;">#{{ $u->id }}</td>
                                    <td>
                                        <label for="user_{{ $u->id }}" style="cursor: pointer;">
                                            <div class="user-name">{{ $u->name }}</div>
                                            <div class="user-email">{{ $u->email }}</div>
                                        </label>
                                    </td>
                                    <td><span class="count-badge">{{ $u->tasks_count }}</span></td>
                                    <td><span class="count-badge">{{ $u->journals_count }}</span></td>
                                    <td><span class="count-badge">{{ $u->focus_sessions_count }}</span></td>
                                    <td style="color: #94a3b8; font-size: 12px;">{{ $u->created_at ? $u->created_at->format('d M Y, H:i') : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 30px;">Tidak ada user ditemukan di dalam database.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="actions-bar">
                    <div class="hint">
                        • Log testing lokal terdeteksi: <strong>{{ $logsCount }} baris</strong> (akan dibersihkan otomatis).<br>
                        • Total user saat ini: <strong>{{ $users->count() }} akun</strong>.
                    </div>
                    <button type="submit" class="btn-submit">
                        🗑️ Hapus User Dummy Terpilih
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
