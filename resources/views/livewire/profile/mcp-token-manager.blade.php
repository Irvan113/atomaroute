<?php

use Livewire\Volt\Component;
use App\Models\McpToken;
use App\Services\McpServerService;

new class extends Component {
    public $tokenName = '';
    public $newTokenValue = null;
    public $configClient = 'cursor'; // 'cursor', 'claude', 'antigravity'

    public function createToken()
    {
        $name = trim($this->tokenName) ?: 'Client AI (' . date('d M Y') . ')';

        $token = McpToken::createForUser(auth()->user(), $name);

        $this->newTokenValue = $token->token;
        $this->tokenName = '';

        $this->dispatch('toast', message: "Token MCP '{$name}' berhasil dibuat!", type: 'success');
        $this->dispatch('play-sound', preset: 'level_up');
    }

    public function deleteToken($tokenId)
    {
        $token = auth()->user()->mcpTokens()->find($tokenId);
        if ($token) {
            $name = $token->name;
            $token->delete();
            $this->dispatch('toast', message: "Token '{$name}' berhasil dicabut.", type: 'info');
        }
    }

    public function with()
    {
        return [
            'tokens' => auth()->user()->mcpTokens()->latest()->get(),
            'tools' => McpServerService::getAvailableTools(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-purple-900/40 via-indigo-900/30 to-blue-900/20 border border-purple-500/30 rounded-3xl p-5 sm:p-7 relative overflow-hidden text-gray-900 dark:text-white">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-purple-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-purple-500/30 text-xl font-black">
                        🔌
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg sm:text-xl font-black tracking-tight">Model Context Protocol (MCP) Server</h3>
                            <span class="text-[10px] bg-purple-500/20 border border-purple-400/40 text-purple-600 dark:text-purple-300 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">v1.0 Ready</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            Hubungkan akun ATOMA kamu ke <strong>Claude Desktop</strong>, <strong>Cursor IDE</strong>, <strong>Antigravity</strong>, atau AI Agent apa pun via standar industri terbuka.
                        </p>
                    </div>
                </div>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Endpoint Aktif
                </span>
            </div>
        </div>
    </div>

    <!-- Beginner-Friendly Explanation & Guide Banner -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-purple-200/80 dark:border-purple-800/50 shadow-sm space-y-4" x-data="{ expanded: true }">
        <div class="flex items-start justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">💡</span>
                <div>
                    <h4 class="text-sm sm:text-base font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span>Apa Itu MCP & Mengapa Kamu Membutuhkannya?</span>
                        <span class="text-[10px] bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-extrabold px-2 py-0.5 rounded-full uppercase">Panduan Pemula</span>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Penjelasan sederhana bagaimana asisten AI bisa mengurus to-do & catatan ATOMA kamu</p>
                </div>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs font-bold transition-transform duration-200 flex items-center gap-1 cursor-pointer">
                <span x-text="expanded ? 'Tutup' : 'Lihat Panduan'"></span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
        </div>

        <div x-show="expanded" class="space-y-4 pt-2 border-t border-gray-100 dark:border-gray-700/60">
            <!-- Analogi Perbandingan Visual -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                <!-- AI Biasa -->
                <div class="p-4 rounded-2xl bg-red-50/60 dark:bg-red-950/20 border border-red-200/80 dark:border-red-900/40 space-y-2">
                    <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-black text-xs">
                        <span>❌</span>
                        <span>AI Biasa (Tanpa MCP)</span>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                        Asisten pintar tapi <strong>tangannya terikat</strong>. Kalau kamu suruh <em>"Tolong catatkan to-do ini"</em>, dia cuma bisa jawab di chat dan kamu harus copy-paste sendiri ke website ATOMA.
                    </p>
                </div>

                <!-- AI dengan MCP -->
                <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-900/40 space-y-2">
                    <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-black text-xs">
                        <span>✅</span>
                        <span>AI dengan MCP ATOMA</span>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                        Asisten diberi <strong>remote control & akses aman</strong>. Sekali kamu suruh di chat, to-do langsung otomatis masuk ke website ATOMA, tugas dicentang, dan <strong>EXP gamifikasimu bertambah!</strong>
                    </p>
                </div>
            </div>

            <!-- 3 Keuntungan Utama -->
            <div class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wider text-purple-700 dark:text-purple-300">3 Manfaat Terbesar untuk Kamu:</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-200/70 dark:border-gray-700/60 space-y-1">
                        <span class="text-base block">⚡</span>
                        <h5 class="text-xs font-black text-gray-900 dark:text-white">Fokus & Hemat Waktu</h5>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">Nggak perlu bolak-balik buka tab web ATOMA saat kamu lagi fokus kerja atau nulis.</p>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-200/70 dark:border-gray-700/60 space-y-1">
                        <span class="text-base block">🎮</span>
                        <h5 class="text-xs font-black text-gray-900 dark:text-white">EXP & Streak Otomatis</h5>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">Centang tugas lewat AI tetap dapat reward EXP, level akun naik, dan streak harian terjaga.</p>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-200/70 dark:border-gray-700/60 space-y-1">
                        <span class="text-base block">🧠</span>
                        <h5 class="text-xs font-black text-gray-900 dark:text-white">AI Mengingat Agendamu</h5>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">Tanya <em>"Apa to-do saya hari ini?"</em>, AI langsung membacakan agenda harianmu dari ATOMA.</p>
                    </div>
                </div>
            </div>

            <!-- Rekomendasi Aplikasi AI -->
            <div class="p-3.5 bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-transparent rounded-2xl border border-purple-200 dark:border-purple-800/50 space-y-2">
                <span class="text-xs font-black text-purple-800 dark:text-purple-300 flex items-center gap-1.5">
                    <span>📱</span> Aplikasi AI Apa Saja yang Bisa Kamu Gunakan?
                </span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 text-xs text-gray-700 dark:text-gray-300">
                    <div class="space-y-1">
                        <strong class="text-indigo-600 dark:text-indigo-400 block text-[11px] uppercase tracking-wide">Pengguna Umum / Aplikasi Chat Desktop:</strong>
                        <ul class="space-y-1 text-[11px] text-gray-600 dark:text-gray-300 pl-3 list-disc">
                            <li><strong>Claude Desktop</strong> (Mac & Windows) — Aplikasi chat resmi sangat stabil & pintar.</li>
                            <li><strong>Cherry Studio / Chatbox</strong> — Tampilan modern gratis, bisa pakai <strong>Gemini gratis</strong> atau GPT.</li>
                            <li><strong>Raycast</strong> (Mac) — Tekan shortcut keyboard di Mac langsung kelola to-do.</li>
                        </ul>
                    </div>
                    <div class="space-y-1">
                        <strong class="text-purple-600 dark:text-purple-400 block text-[11px] uppercase tracking-wide">Programmer & Developer:</strong>
                        <ul class="space-y-1 text-[11px] text-gray-600 dark:text-gray-300 pl-3 list-disc">
                            <li><strong>Cursor IDE</strong> — Cukup pasang config di <code>.cursor/mcp.json</code>.</li>
                            <li><strong>VS Code</strong> — Menggunakan ekstensi populer <strong>Cline</strong> atau <strong>Roo Code</strong>.</li>
                            <li><strong>Windsurf IDE</strong> — Editor koding AI dengan dukungan native MCP.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert: Newly Created Token -->
    @if($newTokenValue)
        <div class="p-5 rounded-3xl bg-amber-500/10 border-2 border-amber-500/40 text-amber-900 dark:text-amber-200 space-y-3 animate-fade-in">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚠️</span>
                    <h4 class="font-black text-sm">Simpan Token MCP Anda Sekarang!</h4>
                </div>
                <button type="button" wire:click="$set('newTokenValue', null)" class="text-xs opacity-60 hover:opacity-100 cursor-pointer">
                    ✕ Tutup
                </button>
            </div>
            <p class="text-xs text-amber-800 dark:text-amber-300">
                Demi keamanan, nilai token ini <strong>hanya ditampilkan satu kali ini</strong>. Segera salin dan tempel ke file konfigurasi MCP klien Anda.
            </p>
            <div class="flex items-center gap-2 bg-white dark:bg-gray-900 p-2.5 rounded-2xl border border-amber-500/30 font-mono text-xs text-gray-900 dark:text-white">
                <span class="truncate flex-1 select-all font-bold text-indigo-600 dark:text-indigo-400">{{ $newTokenValue }}</span>
                <button type="button" 
                        @click="navigator.clipboard.writeText('{{ $newTokenValue }}'); $dispatch('toast', { message: 'Token berhasil disalin ke clipboard!', type: 'success' })" 
                        class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer shrink-0">
                    📋 Salin Token
                </button>
            </div>
        </div>
    @endif

    <!-- Form: Generate Token -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200 dark:border-gray-700 space-y-4">
        <h4 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
            <span>🔑</span> Buat Token Klien Baru
        </h4>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <input type="text" 
                   wire:model="tokenName" 
                   wire:keydown.enter="createToken"
                   placeholder="Nama klien (misal: Cursor Laptop Kantor, Claude Mac)" 
                   class="flex-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 outline-none">
            
            <button type="button" 
                    wire:click="createToken" 
                    class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-md shadow-purple-500/25 transition-all cursor-pointer shrink-0 flex items-center justify-center gap-2">
                <span>➕</span>
                <span>Buat Token MCP</span>
            </button>
        </div>
    </div>

    <!-- Active Tokens List -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200 dark:border-gray-700 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                <span>📋</span> Token Aktif ({{ $tokens->count() }})
            </h4>
            <span class="text-[11px] text-gray-400">Total terhubung</span>
        </div>

        @if($tokens->isEmpty())
            <div class="text-center py-8 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                <span class="text-3xl block mb-2">🔌</span>
                <p class="text-xs font-bold text-gray-600 dark:text-gray-400">Belum ada token MCP yang dibuat.</p>
                <p class="text-[11px] text-gray-400 mt-0.5">Buat token di atas untuk menghubungkan Cursor atau Claude Desktop.</p>
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($tokens as $t)
                    <div class="p-3.5 bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700/80 rounded-2xl flex items-center justify-between gap-3">
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-gray-900 dark:text-white">{{ $t->name }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-md font-mono bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800">
                                    {{ substr($t->token, 0, 14) }}...{{ substr($t->token, -6) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-[10px] text-gray-400">
                                <span>Dibuat: {{ $t->created_at->format('d M Y') }}</span>
                                <span>•</span>
                                <span>Terakhir Dipakai: {{ $t->last_used_at ? $t->last_used_at->diffForHumans() : 'Belum pernah' }}</span>
                            </div>
                        </div>

                        <button type="button" 
                                wire:click="deleteToken({{ $t->id }})" 
                                wire:confirm="Yakin ingin mencabut token '{{ $t->name }}'? Klien AI yang menggunakan token ini tidak akan dapat mengakses ATOMA lagi."
                                class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 rounded-xl hover:bg-red-50 dark:hover:bg-red-950/40 font-bold transition-colors cursor-pointer shrink-0">
                            Cabut
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Quick Configuration Snippets -->
    @php
        $latestTokenString = $newTokenValue ?? ($tokens->first()?->token ?? 'atoma_mcp_YOUR_TOKEN_HERE');
        $endpointUrl = url('/api/mcp');
    @endphp

    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200 dark:border-gray-700 space-y-4" x-data="{ client: 'cursor' }">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-700/60 pb-3">
            <div>
                <h4 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <span>⚙️</span> Panduan Konfigurasi Klien AI
                </h4>
                <p class="text-[11px] text-gray-400 mt-0.5">Pilih aplikasi asisten AI yang ingin kamu hubungkan ke ATOMA:</p>
            </div>

            <!-- Tab Client Selector -->
            <div class="flex items-center bg-gray-100 dark:bg-gray-900 p-1 rounded-xl">
                <button type="button" 
                        @click="client = 'cursor'" 
                        :class="client === 'cursor' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm font-black' : 'text-gray-500 font-bold'" 
                        class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer">
                    Cursor IDE
                </button>
                <button type="button" 
                        @click="client = 'claude'" 
                        :class="client === 'claude' ? 'bg-white dark:bg-gray-800 text-purple-600 dark:text-purple-400 shadow-sm font-black' : 'text-gray-500 font-bold'" 
                        class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer">
                    Claude Desktop
                </button>
                <button type="button" 
                        @click="client = 'curl'" 
                        :class="client === 'curl' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-sm font-black' : 'text-gray-500 font-bold'" 
                        class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer">
                    cURL / HTTP
                </button>
            </div>
        </div>

        <!-- Cursor IDE Instructions -->
        <div x-show="client === 'cursor'" class="space-y-3">
            <p class="text-xs text-gray-600 dark:text-gray-300">
                Buka Cursor ➔ <strong>Settings</strong> ➔ <strong>Features</strong> ➔ <strong>MCP Servers</strong> (atau buat file <code>.cursor/mcp.json</code> di folder workspace proyekmu):
            </p>
            <div class="relative">
                <pre class="bg-gray-900 text-gray-100 p-4 rounded-2xl text-[11px] font-mono overflow-x-auto leading-relaxed select-all">{
  "mcpServers": {
    "atoma": {
      "url": "{{ $endpointUrl }}",
      "headers": {
        "Authorization": "Bearer {{ $latestTokenString }}"
      }
    }
  }
}</pre>
                <button type="button" 
                        @click="navigator.clipboard.writeText(`{\n  &quot;mcpServers&quot;: {\n    &quot;atoma&quot;: {\n      &quot;url&quot;: &quot;{{ $endpointUrl }}&quot;,\n      &quot;headers&quot;: {\n        &quot;Authorization&quot;: &quot;Bearer {{ $latestTokenString }}&quot;\n      }\n    }\n  }\n}`); $dispatch('toast', { message: 'Config Cursor berhasil disalin!', type: 'success' })" 
                        class="absolute top-2.5 right-2.5 px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-200 text-[10px] font-bold rounded-lg border border-gray-700 transition-colors cursor-pointer">
                    Salin Config
                </button>
            </div>
        </div>

        <!-- Claude Desktop Instructions -->
        <div x-show="client === 'claude'" class="space-y-3" style="display: none;">
            <p class="text-xs text-gray-600 dark:text-gray-300">
                Claude Desktop menggunakan protokol <code>stdio</code> bawaan. Buka file <code>~/Library/Application Support/Claude/claude_desktop_config.json</code> (Mac) atau <code>%APPDATA%\Claude\claude_desktop_config.json</code> (Windows):
            </p>
            <div class="relative">
                <pre class="bg-gray-900 text-gray-100 p-4 rounded-2xl text-[11px] font-mono overflow-x-auto leading-relaxed select-all">{
  "mcpServers": {
    "atoma": {
      "command": "php",
      "args": [
        "{{ base_path('artisan') }}",
        "mcp:serve",
        "--token={{ $latestTokenString }}"
      ]
    }
  }
}</pre>
                <button type="button" 
                        @click="navigator.clipboard.writeText(`{\n  &quot;mcpServers&quot;: {\n    &quot;atoma&quot;: {\n      &quot;command&quot;: &quot;php&quot;,\n      &quot;args&quot;: [\n        &quot;{{ base_path('artisan') }}&quot;,\n        &quot;mcp:serve&quot;,\n        &quot;--token={{ $latestTokenString }}&quot;\n      ]\n    }\n  }\n}`); $dispatch('toast', { message: 'Config Claude Desktop berhasil disalin!', type: 'success' })" 
                        class="absolute top-2.5 right-2.5 px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-200 text-[10px] font-bold rounded-lg border border-gray-700 transition-colors cursor-pointer">
                    Salin Config
                </button>
            </div>
            <p class="text-[11px] text-gray-400">
                💡 <em>Tip: Claude Desktop akan langsung mengeksekusi perintah di atas di latar belakang saat aplikasi dibuka.</em>
            </p>
        </div>

        <!-- cURL / Direct HTTP Test -->
        <div x-show="client === 'curl'" class="space-y-3" style="display: none;">
            <p class="text-xs text-gray-600 dark:text-gray-300">
                Uji coba langsung koneksi MCP server melalui terminal cURL:
            </p>
            <div class="relative">
                <pre class="bg-gray-900 text-gray-100 p-4 rounded-2xl text-[11px] font-mono overflow-x-auto leading-relaxed select-all">curl -X POST {{ $endpointUrl }} \
  -H "Authorization: Bearer {{ $latestTokenString }}" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "id": 1, "method": "tools/list"}'</pre>
                <button type="button" 
                        @click="navigator.clipboard.writeText(`curl -X POST {{ $endpointUrl }} -H &quot;Authorization: Bearer {{ $latestTokenString }}&quot; -H &quot;Content-Type: application/json&quot; -d '{&quot;jsonrpc&quot;: &quot;2.0&quot;, &quot;id&quot;: 1, &quot;method&quot;: &quot;tools/list&quot;}'`); $dispatch('toast', { message: 'Perintah cURL berhasil disalin!', type: 'success' })" 
                        class="absolute top-2.5 right-2.5 px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-200 text-[10px] font-bold rounded-lg border border-gray-700 transition-colors cursor-pointer">
                    Salin cURL
                </button>
            </div>
        </div>
    </div>

    <!-- Available Tools Catalog -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 sm:p-6 border border-gray-200 dark:border-gray-700 space-y-4">
        <div>
            <h4 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                <span>🛠️</span> Daftar Tools MCP yang Didukung ({{ count($tools) }})
            </h4>
            <p class="text-[11px] text-gray-400 mt-0.5">Asisten AI kamu dapat secara otomatis memanggil fungsi-fungsi ini di ATOMA:</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($tools as $tool)
                <div class="p-3.5 bg-gray-50 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $tool['name'] }}</span>
                        <span class="text-[9px] bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono font-bold">TOOL</span>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                        {{ $tool['description'] }}
                    </p>
                    @php
                        $props = (array) ($tool['inputSchema']['properties'] ?? []);
                    @endphp
                    @if(!empty($props))
                        <div class="pt-1 flex items-center gap-1 flex-wrap text-[10px] text-gray-400">
                            <span class="font-bold text-gray-500">Param:</span>
                            @foreach(array_keys($props) as $param)
                                <code class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-1 py-0.2 rounded font-mono">{{ $param }}</code>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
