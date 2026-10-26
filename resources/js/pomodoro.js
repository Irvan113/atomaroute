// ATOMA Pomodoro Engine (v1.0.1.4 - Zero Inline Script Architecture)
// Extracted and bundled into Vite app.js for instant navigation, zero DOM-swap overhead, and immutable caching.

export function pomodoroTimer(initialTasks = [], userStreak = 0, dueFlashcardsCount = 0, proConfig = {}) {
    return {
                _initialized: false,
                show: false,
                showSettings: false,
                showAudioDeck: false,
                showYtVideoFrame: false,
                showDailyLogModal: false,
                showFlowtimeModal: false,
                
                // Productivity Hub Tabs ('pomodoro' | 'tasks' | 'notes' | 'flashcards')
                widgetTab: 'pomodoro',
                quickTaskInput: '',
                isAddingQuickTask: false,
                quickNoteContent: localStorage.getItem('atoma_widget_quick_note') || '',
                isSavingQuickNote: false,
                dueFlashcardsCount: dueFlashcardsCount,

                // Mode: 'work' | 'flowtime' | 'shortBreak' | 'longBreak'
                mode: 'work',
                timeLeft: 25 * 60,
                flowtimeSeconds: 0,
                isRunning: false,
                isPaused: false,
                targetEndTime: null,
                startedAt: null,
                interval: null,
                timerWorker: null,
                wakeLockSentinel: null,
                sessionCount: 0,
                userStreak: userStreak,
                
                // Tasks
                activeTasks: initialTasks,
                selectedTaskId: '',
                selectedTaskTitle: '',
                
                // Float states & audio mute
                isCompact: false,
                isAudioMuted: false,

                // Draggable Float Bubble Coordinates & Physics
                customX: null,
                customY: null,
                dockSide: 'right',
                isDragging: false,
                hasDragged: false,
                dragStartX: 0,
                dragStartY: 0,
                initialX: 0,
                initialY: 0,

                // Canvas PiP Fallback State
                canvasPipActive: false,
                canvasPipVideo: null,
                canvasPipInterval: null,

                // Offline status
                isOnline: navigator.onLine,
                
                // Flowtime complete state
                flowtimeResultMinutes: 0,
                flowtimeRecommendedBreak: 5,
                
                // Daily Session Log
                isLoadingDailyLog: false,
                dailySessions: [],
                dailyLogSummary: { total_sessions: 0, total_minutes: 0, total_xp: 0, total_coins: 0 },
                
                // Micro-break tips
                activeBreakTipIndex: 0,
                breakTips: [
                    { icon: '💧', title: 'Hidrasi Segar', description: 'Ambil dan minum 1-2 gelas air putih untuk menjaga sirkulasi darah & konsentrasi otak.' },
                    { icon: '👀', title: 'Aturan 20-20-20', description: 'Tatap objek berjarak 6 meter selama 20 detik untuk merelaksasi otot fokus mata.' },
                    { icon: '🧘', title: 'Regangkan Leher & Bahu', description: 'Putar bahu ke belakang 5 kali dan miringkan kepala perlahan ke kanan & kiri.' },
                    { icon: '🌬️', title: 'Pernapasan Rileks 4-7-8', description: 'Tarik napas 4 detik, tahan 7 detik, lalu hembuskan napas perlahan selama 8 detik.' },
                    { icon: '🚶', title: 'Jalan Ringan 2 Menit', description: 'Berdirilah dan berjalan santai mengitari ruangan untuk melemaskan persendian panggul.' },
                    { icon: '🪟', title: 'Tatap Cahaya Luar', description: 'Buka jendela atau pandang dedaunan luar ruangan untuk menyegarkan dopamin alami.' },
                ],
                
                // Music tab: 'ytmusic' | 'ambient'
                musicTab: 'ytmusic',
                youtubeLoaded: false,
                
                // YouTube Player State & Multi-Folder Playlist
                ytVideoId: '5qap5aO4i9A',
                ytTitle: 'Lofi Beats - Deep Focus',
                ytAuthor: 'Lofi Music',
                ytThumbnail: 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg',
                ytQuery: '',
                ytEmbedUrl: 'https://www.youtube.com/embed/5qap5aO4i9A?enablejsapi=1&autoplay=1',
                ytResults: [],
                isYtSearching: false,
                isYtPlaying: false,
                isYtAutoNext: true,
                isYtShuffle: false,
                ytLoopMode: 'all',
                ytVolume: 80,
                isYtMuted: false,

                // Multi-Folder Playlist State
                isPro: Boolean(proConfig && proConfig.isPro),
                maxFolders: Number((proConfig && proConfig.maxFolders) || 2),
                showPlaylistModal: false,
                targetTrackToSave: {},
                newFolderName: '',
                activeFolderId: 'focus',
                editingFolderId: null,
                editFolderName: '',
                editFolderIcon: '📁',
                playlistFolders: [
                    {
                        id: 'focus',
                        name: 'Deep Focus',
                        icon: '🧠',
                        tracks: [
                            { video_id: '5qap5aO4i9A', title: 'Lofi Beats - Deep Focus', author: 'Lofi Girl', thumbnail: 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg' },
                            { video_id: '2OEL4P1rub0', title: 'Peaceful Piano Focus', author: 'Piano Relaxation', thumbnail: 'https://i.ytimg.com/vi/2OEL4P1rub0/hqdefault.jpg' }
                        ]
                    },
                    {
                        id: 'chill',
                        name: 'Chill & Relax',
                        icon: '☕',
                        tracks: [
                            { video_id: '4xDzrJKXOOY', title: 'Rain & Ambient Beats', author: 'Ambient Sound', thumbnail: 'https://i.ytimg.com/vi/4xDzrJKXOOY/hqdefault.jpg' }
                        ]
                    }
                ],

                // Procedural Ambient Offline Player (Web Audio API)
                isAmbientPlaying: false,
                currentAmbientPreset: 'rain',
                ambientVolume: 70,
                ambientPresets: [
                    { id: 'rain', name: 'Hujan Rintik', icon: '🌧️', type: 'pink_lowpass' },
                    { id: 'ocean', name: 'Ombak Laut', icon: '🌊', type: 'ocean_swell' },
                    { id: 'cafe', name: 'Kafe Santai', icon: '☕', type: 'brown_noise' },
                    { id: 'forest', name: 'Hutan Hening', icon: '🌲', type: 'forest_wind' },
                    { id: 'alpha', name: 'Alpha 10Hz', icon: '🧘', type: 'binaural_10hz' },
                    { id: 'gamma', name: 'Gamma 40Hz', icon: '⚡', type: 'binaural_40hz' }
                ],
                ambientAudioCtx: null,
                ambientSourceNodes: [],
                ambientGainNode: null,
                _noiseBuffers: { pink: null, brown: null },

                // Mode Persistence Memory (Prevents accidental reset when switching tabs)
                savedWorkTimeLeft: null,
                savedShortBreakTimeLeft: null,
                savedLongBreakTimeLeft: null,

                // Cache Storage Metric
                cacheSizeText: '0 KB',

                // Telemetry
                todayFocusMinutes: 0,
                todaySessionsCount: 0,
                
                settings: {
                    work: 25,
                    shortBreak: 5,
                    longBreak: 15,
                    interval: 4,
                    autoStartBreak: false,
                    autoStartWork: false,
                    autoPauseMusic: true,
                    alwaysShowFloat: true,
                },

                // --- Inline Web Worker Timer Engine (Zero-Drift Background Execution) ---
                initTimerWorker() {
                    if (this.timerWorker) return;
                    try {
                        const workerCode = `
                            let timerId = null;
                            self.onmessage = function(e) {
                                if (e.data.command === 'start') {
                                    if (timerId) clearInterval(timerId);
                                    timerId = setInterval(() => self.postMessage('tick'), e.data.interval || 1000);
                                } else if (e.data.command === 'stop') {
                                    if (timerId) clearInterval(timerId);
                                    timerId = null;
                                }
                            };
                        `;
                        const blob = new Blob([workerCode], { type: 'application/javascript' });
                        this.timerWorker = new Worker(URL.createObjectURL(blob));
                        this.timerWorker.onmessage = (e) => {
                            if (e.data === 'tick') {
                                this.onTimerTick();
                            }
                        };
                    } catch (e) {
                        console.warn('Web Worker not supported or blocked in this environment', e);
                    }
                },

                // --- Screen Wake Lock API ---
                async requestWakeLock() {
                    if ('wakeLock' in navigator) {
                        try {
                            if (!this.wakeLockSentinel) {
                                this.wakeLockSentinel = await navigator.wakeLock.request('screen');
                                this.wakeLockSentinel.addEventListener('release', () => {
                                    this.wakeLockSentinel = null;
                                });
                            }
                        } catch (e) {
                            console.debug('Screen WakeLock error:', e);
                        }
                    }
                },

                releaseWakeLock() {
                    if (this.wakeLockSentinel) {
                        try {
                            this.wakeLockSentinel.release();
                        } catch (e) {}
                        this.wakeLockSentinel = null;
                    }
                },

                // --- Native Android Bridge & Widget Synchronization ---
                syncWithNativeAndroid() {
                    if (!window.AtomaBridge) return;
                    const remainingMs = this.mode === 'flowtime'
                        ? this.flowtimeSeconds * 1000
                        : this.timeLeft * 1000;
                    if (typeof window.AtomaBridge.syncPomodoro === 'function') {
                        window.AtomaBridge.syncPomodoro(Boolean(this.isRunning), remainingMs);
                    }
                    if (this.isRunning && this.mode !== 'flowtime' && this.timeLeft > 0) {
                        if (typeof window.AtomaBridge.scheduleNativeReminder === 'function') {
                            const triggerAtMs = Date.now() + (this.timeLeft * 1000);
                            const notifTitle = this.mode === 'work' ? '🎉 Focus Time Selesai!' : '⚡ Istirahat Selesai!';
                            const notifBody = this.mode === 'work' ? 'Waktunya istirahat sejenak.' : 'Kembali fokus ke tugasmu!';
                            window.AtomaBridge.scheduleNativeReminder(999999, notifTitle, notifBody, triggerAtMs);
                        }
                    } else {
                        if (typeof window.AtomaBridge.cancelNativeReminder === 'function') {
                            window.AtomaBridge.cancelNativeReminder(999999);
                        }
                    }
                },

                // --- Continuous Ultradian Rhythm Flowtime Break Curve ---
                calculateFlowtimeBreak(focusMinutes) {
                    const minutes = Math.max(1, Number(focusMinutes) || 0);
                    if (minutes < 10) return 2;
                    const ratio = 0.18 + (0.001 * Math.min(minutes, 90));
                    return Math.min(25, Math.max(3, Math.round(minutes * ratio)));
                },

                // --- Server-Authoritative Start-Commit Handshake ---
                sendFocusStart() {
                    if (this.mode !== 'work' && this.mode !== 'flowtime') return;
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const targetDuration = this.mode === 'flowtime' ? 60 : (this.settings.work || 25);
                    fetch('/api/focus/start', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                            mode: this.mode,
                            task_id: this.selectedTaskId || null,
                            target_duration: targetDuration
                        })
                    }).catch(() => {});
                },

                // --- Monotonic Timestamp Delta Tick Handler ---
                onTimerTick() {
                    if (!this.isRunning) return;

                    if (this.mode === 'flowtime') {
                        this.flowtimeSeconds = Math.max(0, Math.round((Date.now() - (this.startedAt || Date.now())) / 1000));
                        document.title = `(${this.formattedTime}) ATOMA Flow`;
                        if (this.flowtimeSeconds % 5 === 0) {
                            this.saveActiveTimerState();
                        }
                        if (this.flowtimeSeconds % 30 === 0) {
                            this.sendFocusHeartbeat();
                        }
                    } else {
                        const remaining = Math.max(0, Math.round(((this.targetEndTime || (Date.now() + this.timeLeft * 1000)) - Date.now()) / 1000));
                        this.timeLeft = remaining;
                        document.title = `(${this.formattedTime}) ATOMA`;
                        if (this.timeLeft % 5 === 0) {
                            this.saveActiveTimerState();
                        }
                        if (this.timeLeft % 30 === 0) {
                            this.sendFocusHeartbeat();
                        }
                        if (remaining <= 0) {
                            this.timerComplete();
                        }
                    }
                },

                // --- Tab Visibility Reconciliation (Instantly corrects drift when foregrounded) ---
                reconcileTimerState() {
                    if (!this.isRunning) return;
                    if (this.mode === 'flowtime') {
                        if (this.startedAt) {
                            this.flowtimeSeconds = Math.max(0, Math.round((Date.now() - this.startedAt) / 1000));
                        }
                    } else {
                        if (this.targetEndTime) {
                            const diff = Math.round((this.targetEndTime - Date.now()) / 1000);
                            if (diff <= 0) {
                                this.timeLeft = 0;
                                this.timerComplete();
                            } else {
                                this.timeLeft = diff;
                            }
                        }
                    }
                    this.saveActiveTimerState();
                },

                init() {
                    if (this._initialized) return;
                    this._initialized = true;

                    this.loadSettings();
                    this.loadTelemetry();
                    this.loadSavedPlaylist();
                    this.loadYtVolume();
                    this.loadActiveTimerState();
                    this.loadFloatPosition();
                    this.initOfflineCache();
                    this.fetchTodaySessions();
                    this.initTimerWorker();

                    this.$nextTick(() => this.updateVideoSlotPosition());

                    window.addEventListener('toggle-pomodoro-widget', () => this.toggleShow());

                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.get('action') === 'pomodoro' || urlParams.get('focus') === '1') {
                            this.show = true;
                            this.widgetTab = 'pomodoro';
                        }
                    } catch (e) {}

                    window.addEventListener('resize', () => {
                        this.clampFloatPosition();
                        this.updateVideoSlotPosition();
                    });
                    window.addEventListener('scroll', () => {
                        this.updateVideoSlotPosition();
                    }, true);

                    // Document visibility change listener (fixes background drift & manages screen wake lock)
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            this.reconcileTimerState();
                            this.loadSavedPlaylist();
                            this.fetchTodaySessions();
                            if (this.isRunning) {
                                this.requestWakeLock();
                            }
                        } else {
                            this.releaseWakeLock();
                        }
                    });

                    // Native Android two-way synchronization listener
                    window.addEventListener('native-pomodoro-sync', (e) => {
                        const detail = e.detail || {};
                        if (typeof detail.isRunning === 'boolean') {
                            if (detail.isRunning && !this.isRunning) {
                                if (detail.remainingMs > 0 && this.mode !== 'flowtime') {
                                    this.timeLeft = Math.round(detail.remainingMs / 1000);
                                    this.targetEndTime = Date.now() + detail.remainingMs;
                                }
                                this.startTimer();
                            } else if (!detail.isRunning && this.isRunning) {
                                this.pauseTimer();
                                if (detail.remainingMs !== undefined && this.mode !== 'flowtime') {
                                    this.timeLeft = Math.round(detail.remainingMs / 1000);
                                }
                            }
                        }
                    });

                    // Global subtask synchronization
                    window.addEventListener('subtask-toggled-global', (e) => {
                        const detail = e.detail || {};
                        const task = this.activeTasks.find(t => String(t.id) === String(detail.taskId));
                        if (task && task.subtasks) {
                            const sub = task.subtasks.find(s => String(s.id) === String(detail.subTaskId));
                            if (sub) {
                                sub.is_completed = detail.is_completed;
                            }
                        }
                        if (window.pipWindow && !window.pipWindow.closed) {
                            this.updatePipContent();
                        }
                    });

                    // Online/Offline detection
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.loadSavedPlaylist();
                        this.syncPlaylistToServer();
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });

                    // Keyboard shortcuts listener (global window)
                    window.addEventListener('keydown', (e) => {
                        const tag = (e.target.tagName || '').toLowerCase();
                        if (['input', 'textarea', 'select'].includes(tag) || e.target.isContentEditable) {
                            return;
                        }

                        // Space: toggle play/pause
                        if (e.code === 'Space') {
                            e.preventDefault();
                            this.toggleTimer();
                        }
                        // Alt+P: toggle widget show/hide
                        else if (e.altKey && (e.key === 'p' || e.key === 'P')) {
                            e.preventDefault();
                            this.toggleShow();
                        }
                        // Alt+R: reset timer
                        else if (e.altKey && (e.key === 'r' || e.key === 'R')) {
                            e.preventDefault();
                            this.resetTimer();
                        }
                        // Alt+F: switch to flowtime
                        else if (e.altKey && (e.key === 'f' || e.key === 'F')) {
                            e.preventDefault();
                            this.setMode('flowtime');
                        }
                        // Alt+[ or Alt+ArrowLeft: previous song
                        else if (e.altKey && (e.key === '[' || e.key === 'ArrowLeft')) {
                            e.preventDefault();
                            this.playPrevTrack();
                        }
                        // Alt+] or Alt+ArrowRight: next song
                        else if (e.altKey && (e.key === ']' || e.key === 'ArrowRight')) {
                            e.preventDefault();
                            this.playNextTrack();
                        }
                    });

                    // Hardware Media Keys & OS Media Center Action Handlers
                    if ('mediaSession' in navigator) {
                        try {
                            navigator.mediaSession.setActionHandler('previoustrack', () => this.playPrevTrack());
                            navigator.mediaSession.setActionHandler('nexttrack', () => this.playNextTrack());
                        } catch (e) {}
                    }

                    this.$watch('show', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => setTimeout(() => this.updateVideoSlotPosition(), 150));
                    });
                    this.$watch('showYtVideoFrame', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => {
                            setTimeout(() => this.updateVideoSlotPosition(), 50);
                            setTimeout(() => this.updateVideoSlotPosition(), 200);
                            setTimeout(() => this.updateVideoSlotPosition(), 400);
                        });
                    });
                    this.$watch('showAudioDeck', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => {
                            setTimeout(() => this.updateVideoSlotPosition(), 50);
                            setTimeout(() => this.updateVideoSlotPosition(), 200);
                            setTimeout(() => this.updateVideoSlotPosition(), 400);
                        });
                    });
                    this.$watch('musicTab', () => {
                        this.saveActiveTimerState();
                        this.$nextTick(() => this.updateVideoSlotPosition());
                    });
                    this.$watch('activeFolderId', () => this.saveActiveTimerState());
                    this.$watch('isYtShuffle', () => this.saveActiveTimerState());
                    this.$watch('ytLoopMode', () => this.saveActiveTimerState());
                    this.$watch('isYtAutoNext', () => this.saveActiveTimerState());
                    this.$watch('ytVolume', () => this.saveActiveTimerState());
                    this.$watch('isYtMuted', () => this.saveActiveTimerState());

                    // Livewire 3 wire:navigate persistence hook
                    document.addEventListener('livewire:navigated', () => {
                        this.fetchTodaySessions();
                        if (this.youtubeLoaded) {
                            this.ensurePermanentAudioHost();
                        }
                        this.$nextTick(() => {
                            this.updateVideoSlotPosition();
                        });
                    });

                    window.addEventListener('beforeunload', () => this.saveActiveTimerState());
                    window.addEventListener('pagehide', () => this.saveActiveTimerState());

                    // Listen to YouTube Iframe postMessage events
                    window.addEventListener('message', (event) => {
                        try {
                            let data = event.data;
                            if (typeof data === 'string') {
                                try { data = JSON.parse(data); } catch (e) { return; }
                            }
                            if (!data) return;

                            if (data.event === 'onReady' || data.event === 'initialDelivery') {
                                this.sendYtCommand('addEventListener', ['onStateChange']);
                                this.sendYtListening();
                            }

                            if (data.event === 'infoDelivery' && data.info && data.info.playerState !== undefined) {
                                const state = Number(data.info.playerState);
                                if (state === 0) { // ENDED
                                    this.onYtTrackEnded();
                                } else if (state === 1) { // PLAYING
                                    this.isYtPlaying = true;
                                } else if (state === 2) { // PAUSED
                                    this.isYtPlaying = false;
                                }
                            }
                        } catch (e) {}
                    });
                },

                get currentAmbientName() {
                    const p = this.ambientPresets.find(x => x.id === this.currentAmbientPreset);
                    return p ? (p.icon + ' ' + p.name) : 'Ambient Offline';
                },

                get formattedTime() {
                    if (this.mode === 'flowtime') {
                        const totalSecs = this.flowtimeSeconds;
                        const m = Math.floor(totalSecs / 60);
                        const s = totalSecs % 60;
                        if (m >= 60) {
                            const h = Math.floor(m / 60);
                            const remM = m % 60;
                            return `${h}:${remM.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                        }
                        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                    }

                    const m = Math.floor(this.timeLeft / 60);
                    const s = this.timeLeft % 60;
                    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                },

                get modeName() {
                    if (this.mode === 'work') return 'Focus Time';
                    if (this.mode === 'flowtime') return 'Flowtime';
                    if (this.mode === 'shortBreak') return 'Short Break';
                    return 'Long Break';
                },

                get totalTimeForMode() {
                    if (this.mode === 'work') return this.settings.work * 60;
                    if (this.mode === 'flowtime') return 60 * 60;
                    if (this.mode === 'shortBreak') return this.settings.shortBreak * 60;
                    return this.settings.longBreak * 60;
                },

                get progressPercentage() {
                    if (this.mode === 'flowtime') {
                        return ((this.flowtimeSeconds % 3600) / 3600) * 100;
                    }
                    const total = this.totalTimeForMode;
                    if (total <= 0) return 0;
                    const elapsed = total - this.timeLeft;
                    return Math.min(100, Math.max(0, (elapsed / total) * 100));
                },

                get ringOffset() {
                    return 94.25 - (94.25 * this.progressPercentage / 100);
                },

                get floatPositionStyle() {
                    if (this.customX !== null && this.customY !== null) {
                        return `left: ${this.customX}px; top: ${this.customY}px; bottom: auto; right: auto;`;
                    }
                    return 'bottom: 5rem; right: 1.25rem;';
                },

                get recommendedBreakMinutes() {
                    const mins = Math.floor(this.flowtimeSeconds / 60);
                    return this.calculateFlowtimeBreak(mins);
                },

                get isTaskConnected() {
                    return Boolean(this.selectedTaskId && this.activeTasks.some(t => String(t.id) === String(this.selectedTaskId)));
                },

                get currentSelectedTask() {
                    if (!this.selectedTaskId) return null;
                    return this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId)) || null;
                },

                get currentBreakTip() {
                    return this.breakTips[this.activeBreakTipIndex] || this.breakTips[0];
                },

                shuffleBreakTip() {
                    this.activeBreakTipIndex = (this.activeBreakTipIndex + 1) % this.breakTips.length;
                },

                get currentAmbientPresetObj() {
                    return this.ambientPresets.find(p => p.id === this.currentAmbientPreset) || this.ambientPresets[0];
                },

                setMode(newMode) {
                    // Cache current mode's elapsed time before pausing
                    if (this.mode === 'work') this.savedWorkTimeLeft = this.timeLeft;
                    else if (this.mode === 'shortBreak') this.savedShortBreakTimeLeft = this.timeLeft;
                    else if (this.mode === 'longBreak') this.savedLongBreakTimeLeft = this.timeLeft;

                    this.pauseTimer();
                    this.mode = newMode;
                    if (newMode === 'work' || newMode === 'focus') {
                        this.timeLeft = (this.savedWorkTimeLeft !== null && this.savedWorkTimeLeft > 0) 
                            ? this.savedWorkTimeLeft 
                            : (Math.max(60, Number(this.settings.work || 25) * 60));
                    } else if (newMode === 'flowtime') {
                        if (this.flowtimeSeconds === undefined) this.flowtimeSeconds = 0;
                    } else if (newMode === 'shortBreak') {
                        this.timeLeft = (this.savedShortBreakTimeLeft !== null && this.savedShortBreakTimeLeft > 0)
                            ? this.savedShortBreakTimeLeft
                            : (Math.max(60, Number(this.settings.shortBreak || 5) * 60));
                        this.shuffleBreakTip();
                    } else {
                        this.timeLeft = (this.savedLongBreakTimeLeft !== null && this.savedLongBreakTimeLeft > 0)
                            ? this.savedLongBreakTimeLeft
                            : (Math.max(60, Number(this.settings.longBreak || 15) * 60));
                        this.shuffleBreakTip();
                    }
                    this.saveActiveTimerState();
                },

                onTaskSelect(e) {
                    const val = e.target.value;
                    this.selectedTaskId = val ? String(val) : '';
                    const found = this.activeTasks.find(t => String(t.id) === String(val));
                    this.selectedTaskTitle = found ? found.title : '';
                    this.saveActiveTimerState();
                },

                markSelectedTaskDone() {
                    if (!this.selectedTaskId) return;
                    const taskIdToComplete = this.selectedTaskId;
                    
                    if (window.Livewire) {
                        window.Livewire.dispatch('complete-task-from-pomodoro', { taskId: taskIdToComplete });
                    }

                    // Remove task from local list
                    this.activeTasks = this.activeTasks.filter(t => String(t.id) !== String(taskIdToComplete));
                    this.selectedTaskId = '';
                    this.selectedTaskTitle = '';
                },

                // --- Finish Session Early (Progressive XP: 2.5 XP/min, min 1 min) ---
                finishSessionEarly() {
                    if (this.mode === 'flowtime') {
                        this.finishFlowtime();
                        return;
                    }

                    if (this.mode === 'shortBreak' || this.mode === 'longBreak') {
                        this.pauseTimer();
                        this.setMode('work');
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: 'Istirahat selesai lebih awal. Waktunya kembali fokus! 🍅', 
                                type: 'info' 
                            });
                        }
                        return;
                    }

                    // Mode: 'work'
                    this.pauseTimer();
                    const totalSeconds = (this.settings.work || 25) * 60;
                    const elapsedSeconds = Math.max(0, totalSeconds - this.timeLeft);
                    const focusMinutes = Math.floor(elapsedSeconds / 60);

                    if (focusMinutes < 1) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: 'Sesi fokus kurang dari 1 menit tidak mendapatkan XP (minimal 1 menit fokus).', 
                                type: 'info' 
                            });
                        }
                        this.timeLeft = totalSeconds;
                        this.saveActiveTimerState();
                        return;
                    }

                    this.playWebAudioChime();
                    this.triggerHaptic('success');

                    // Auto-pause audio if enabled
                    if (this.settings.autoPauseMusic) {
                        this.autoPauseAudio();
                    }

                    // Dispatch progressive reward to backend (2.5 XP per minute)
                    if (window.Livewire) {
                        const ambient = this.isYtPlaying ? 'youtube' : (this.isAmbientPlaying ? this.currentAmbientPreset : null);
                        window.Livewire.dispatch('pomodoro-completed', {
                            taskId: this.selectedTaskId || null,
                            minutes: focusMinutes,
                            ambientSound: ambient,
                            mode: 'work'
                        });
                    }

                    this.switchMode(focusMinutes);
                    this.fetchTodaySessions();
                    this.show = true;
                    this.saveActiveTimerState();
                },

                // --- Flowtime Actions ---
                finishFlowtime() {
                    this.pauseTimer();
                    const focusMinutes = Math.round(this.flowtimeSeconds / 60);

                    if (focusMinutes < 1) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Sesi Flowtime minimal 1 menit untuk dapat dicatat.', type: 'info' });
                        }
                        this.flowtimeSeconds = 0;
                        this.setMode('flowtime');
                        return;
                    }

                    this.playWebAudioChime();
                    this.todayFocusMinutes += focusMinutes;
                    this.todaySessionsCount++;
                    this.saveTelemetry();

                    // Auto-pause audio if enabled
                    if (this.settings.autoPauseMusic) {
                        this.autoPauseAudio();
                    }

                    // Dispatch reward to backend
                    if (window.Livewire) {
                        const ambient = this.isYtPlaying ? 'youtube' : (this.isAmbientPlaying ? this.currentAmbientPreset : null);
                        window.Livewire.dispatch('pomodoro-completed', {
                            taskId: this.selectedTaskId,
                            minutes: focusMinutes,
                            ambientSound: ambient,
                            mode: 'flowtime'
                        });
                    }

                    this.flowtimeResultMinutes = focusMinutes;
                    this.flowtimeRecommendedBreak = this.calculateFlowtimeBreak(focusMinutes);
                    this.showFlowtimeModal = true;
                    this.flowtimeSeconds = 0;
                    this.saveActiveTimerState();
                },

                startFlowtimeBreak() {
                    this.showFlowtimeModal = false;
                    this.mode = 'shortBreak';
                    this.timeLeft = this.flowtimeRecommendedBreak * 60;
                    this.saveActiveTimerState();
                    this.startTimer();
                },

                // --- Daily Session Log ---
                openDailyLog() {
                    this.showDailyLogModal = true;
                    this.fetchTodaySessions();
                },

                fetchTodaySessions() {
                    this.isLoadingDailyLog = true;
                    fetch('/api/pomodoro/today-sessions')
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.sessions) {
                                this.dailySessions = data.sessions;
                                this.dailyLogSummary = data.summary || {
                                    total_sessions: data.sessions.length,
                                    total_minutes: this.todayFocusMinutes,
                                    total_xp: 0,
                                    total_coins: 0
                                };
                                if (data.summary) {
                                    this.todaySessionsCount = Number(data.summary.total_sessions) || 0;
                                    this.todayFocusMinutes = Number(data.summary.total_minutes) || 0;
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Failed to load today sessions:', err);
                        })
                        .finally(() => {
                            this.isLoadingDailyLog = false;
                        });
                },

                // --- Audio / Auto-Pause Controls ---
                autoPauseAudio() {
                    if (this.isYtPlaying) {
                        this.toggleYtPlayback();
                    }
                    if (this.isAmbientPlaying) {
                        this.toggleAmbientPlayback();
                    }
                },

                // Web Audio API Dual-Tone Chime (Reliable, 100% Offline)
                playWebAudioChime() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const ctx = new AudioCtx();
                        const now = ctx.currentTime;

                        // Tone 1: 587.33 Hz (D5)
                        const osc1 = ctx.createOscillator();
                        const gain1 = ctx.createGain();
                        osc1.type = 'sine';
                        osc1.frequency.setValueAtTime(587.33, now);
                        gain1.gain.setValueAtTime(0.3, now);
                        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
                        osc1.connect(gain1);
                        gain1.connect(ctx.destination);
                        osc1.start(now);
                        osc1.stop(now + 0.5);

                        // Tone 2: 880 Hz (A5)
                        const osc2 = ctx.createOscillator();
                        const gain2 = ctx.createGain();
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(880, now + 0.22);
                        gain2.gain.setValueAtTime(0.35, now + 0.22);
                        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.1);
                        osc2.connect(gain2);
                        gain2.connect(ctx.destination);
                        osc2.start(now + 0.22);
                        osc2.stop(now + 1.1);
                    } catch (e) {
                        console.warn('Web Audio chime error:', e);
                    }
                },

                // Procedural Ambient Synthesizer Engine (0 KB Server, 100% Offline)
                selectAmbientPreset(presetId) {
                    this.currentAmbientPreset = presetId;
                    if (this.isAmbientPlaying) {
                        this.stopAmbientSynth();
                        this.startAmbientSynth();
                    }
                },

                toggleAmbientPlayback() {
                    if (this.isAmbientPlaying) {
                        this.stopAmbientSynth();
                        this.isAmbientPlaying = false;
                    } else {
                        // Pause YT if playing to free up audio decoders
                        if (this.isYtPlaying) {
                            this.sendYtCommand('pauseVideo');
                            this.isYtPlaying = false;
                        }
                        this.startAmbientSynth();
                        this.isAmbientPlaying = true;
                    }
                    this.saveActiveTimerState();
                },

                // Pre-computed looping AudioBuffers with crossfaded boundaries (CPU optimized)
                getPinkNoiseBuffer(ctx) {
                    if (this._noiseBuffers && this._noiseBuffers.pink) return this._noiseBuffers.pink;
                    const duration = 10;
                    const bufferSize = ctx.sampleRate * duration;
                    const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                    const data = buffer.getChannelData(0);
                    let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0;
                    for (let i = 0; i < bufferSize; i++) {
                        const white = Math.random() * 2 - 1;
                        b0 = 0.99886 * b0 + white * 0.0555179;
                        b1 = 0.99332 * b1 + white * 0.0750759;
                        b2 = 0.96900 * b2 + white * 0.1538520;
                        b3 = 0.86650 * b3 + white * 0.3104856;
                        b4 = 0.55000 * b4 + white * 0.5329522;
                        b5 = -0.7616 * b5 + white * 0.0168980;
                        data[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362) * 0.11;
                        b6 = white * 0.115926;
                    }
                    // 100ms seamless loop crossfade
                    const crossfadeSamples = Math.floor(ctx.sampleRate * 0.1);
                    for (let i = 0; i < crossfadeSamples; i++) {
                        const factor = i / crossfadeSamples;
                        data[i] = (data[i] * factor) + (data[bufferSize - crossfadeSamples + i] * (1 - factor));
                    }
                    if (!this._noiseBuffers) this._noiseBuffers = {};
                    this._noiseBuffers.pink = buffer;
                    return buffer;
                },

                getBrownNoiseBuffer(ctx) {
                    if (this._noiseBuffers && this._noiseBuffers.brown) return this._noiseBuffers.brown;
                    const duration = 10;
                    const bufferSize = ctx.sampleRate * duration;
                    const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                    const data = buffer.getChannelData(0);
                    let lastOut = 0.0;
                    for (let i = 0; i < bufferSize; i++) {
                        const white = Math.random() * 2 - 1;
                        data[i] = (lastOut + (0.02 * white)) / 1.02;
                        lastOut = data[i];
                        data[i] *= 3.5;
                    }
                    // 100ms seamless loop crossfade
                    const crossfadeSamples = Math.floor(ctx.sampleRate * 0.1);
                    for (let i = 0; i < crossfadeSamples; i++) {
                        const factor = i / crossfadeSamples;
                        data[i] = (data[i] * factor) + (data[bufferSize - crossfadeSamples + i] * (1 - factor));
                    }
                    if (!this._noiseBuffers) this._noiseBuffers = {};
                    this._noiseBuffers.brown = buffer;
                    return buffer;
                },

                startAmbientSynth() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        if (!window.atomaAmbientAudioCtx || window.atomaAmbientAudioCtx.state === 'closed') {
                            window.atomaAmbientAudioCtx = new AudioCtx();
                        }
                        this.ambientAudioCtx = window.atomaAmbientAudioCtx;
                        const ctx = this.ambientAudioCtx;
                        if (ctx.state === 'suspended') {
                            ctx.resume();
                        }

                        this.stopAmbientSynth();

                        const masterGain = ctx.createGain();
                        masterGain.gain.setValueAtTime((this.ambientVolume / 100) * 0.4, ctx.currentTime);
                        masterGain.connect(ctx.destination);
                        this.ambientGainNode = masterGain;

                        const preset = this.currentAmbientPreset;

                        if (preset === 'rain') {
                            const buffer = this.getPinkNoiseBuffer(ctx);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(1100, ctx.currentTime);

                            src.connect(filter);
                            filter.connect(masterGain);
                            src.start();
                            this.ambientSourceNodes.push(src, filter);
                        } else if (preset === 'ocean') {
                            const buffer = this.getPinkNoiseBuffer(ctx);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(800, ctx.currentTime);

                            const swellGain = ctx.createGain();
                            swellGain.gain.setValueAtTime(0.4, ctx.currentTime);

                            const lfo = ctx.createOscillator();
                            lfo.frequency.setValueAtTime(0.1, ctx.currentTime);
                            const lfoGain = ctx.createGain();
                            lfoGain.gain.setValueAtTime(0.3, ctx.currentTime);
                            lfo.connect(lfoGain);
                            lfoGain.connect(swellGain.gain);

                            src.connect(filter);
                            filter.connect(swellGain);
                            swellGain.connect(masterGain);

                            src.start();
                            lfo.start();
                            this.ambientSourceNodes.push(src, filter, swellGain, lfo, lfoGain);
                        } else if (preset === 'cafe') {
                            const buffer = this.getBrownNoiseBuffer(ctx);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(550, ctx.currentTime);

                            src.connect(filter);
                            filter.connect(masterGain);
                            src.start();
                            this.ambientSourceNodes.push(src, filter);
                        } else if (preset === 'forest') {
                            const buffer = this.getBrownNoiseBuffer(ctx);
                            const src = ctx.createBufferSource();
                            src.buffer = buffer;
                            src.loop = true;

                            const filter = ctx.createBiquadFilter();
                            filter.type = 'lowpass';
                            filter.frequency.setValueAtTime(260, ctx.currentTime);

                            const windLfo = ctx.createOscillator();
                            windLfo.type = 'sine';
                            windLfo.frequency.setValueAtTime(0.09, ctx.currentTime);

                            const windLfoGain = ctx.createGain();
                            windLfoGain.gain.setValueAtTime(120, ctx.currentTime);
                            windLfo.connect(windLfoGain);
                            windLfoGain.connect(filter.frequency);

                            const leafBuffer = this.getPinkNoiseBuffer(ctx);
                            const leafSrc = ctx.createBufferSource();
                            leafSrc.buffer = leafBuffer;
                            leafSrc.loop = true;
                            const leafFilter = ctx.createBiquadFilter();
                            leafFilter.type = 'bandpass';
                            leafFilter.frequency.setValueAtTime(1600, ctx.currentTime);
                            leafFilter.Q.setValueAtTime(1.5, ctx.currentTime);
                            const leafGain = ctx.createGain();
                            leafGain.gain.setValueAtTime(0.07, ctx.currentTime);
                            leafSrc.connect(leafFilter);
                            leafFilter.connect(leafGain);
                            leafGain.connect(masterGain);

                            src.connect(filter);
                            filter.connect(masterGain);

                            src.start();
                            windLfo.start();
                            leafSrc.start();
                            this.ambientSourceNodes.push(src, filter, windLfo, windLfoGain, leafSrc, leafFilter, leafGain);
                        } else if (preset === 'alpha') {
                            // True Binaural Beats 10 Hz (Alpha Band: 8-12 Hz for relaxed alert focus)
                            const oscLeft = ctx.createOscillator();
                            oscLeft.type = 'sine';
                            oscLeft.frequency.setValueAtTime(200, ctx.currentTime);

                            const oscRight = ctx.createOscillator();
                            oscRight.type = 'sine';
                            oscRight.frequency.setValueAtTime(210, ctx.currentTime);

                            if (ctx.createStereoPanner) {
                                const panL = ctx.createStereoPanner();
                                panL.pan.setValueAtTime(-1, ctx.currentTime);
                                const panR = ctx.createStereoPanner();
                                panR.pan.setValueAtTime(1, ctx.currentTime);

                                oscLeft.connect(panL);
                                panL.connect(masterGain);
                                oscRight.connect(panR);
                                panR.connect(masterGain);
                                this.ambientSourceNodes.push(oscLeft, oscRight, panL, panR);
                            } else {
                                oscLeft.connect(masterGain);
                                oscRight.connect(masterGain);
                                this.ambientSourceNodes.push(oscLeft, oscRight);
                            }

                            oscLeft.start();
                            oscRight.start();
                        } else if (preset === 'gamma') {
                            // Gamma Waves 40 Hz (Deep Focus / Cognition stimulation)
                            const carrier = ctx.createOscillator();
                            carrier.type = 'sine';
                            carrier.frequency.setValueAtTime(200, ctx.currentTime);

                            const tremoloGain = ctx.createGain();
                            tremoloGain.gain.setValueAtTime(0.5, ctx.currentTime);

                            const lfo = ctx.createOscillator();
                            lfo.type = 'sine';
                            lfo.frequency.setValueAtTime(40, ctx.currentTime);

                            const lfoAmp = ctx.createGain();
                            lfoAmp.gain.setValueAtTime(0.4, ctx.currentTime);
                            lfo.connect(lfoAmp);
                            lfoAmp.connect(tremoloGain.gain);

                            carrier.connect(tremoloGain);
                            tremoloGain.connect(masterGain);

                            carrier.start();
                            lfo.start();
                            this.ambientSourceNodes.push(carrier, lfo, tremoloGain, lfoAmp);
                        }
                    } catch (e) {
                        console.error('Ambient synth start error:', e);
                    }
                },

                stopAmbientSynth() {
                    this.ambientSourceNodes.forEach(node => {
                        try {
                            if (typeof node.stop === 'function') node.stop();
                            if (typeof node.disconnect === 'function') node.disconnect();
                        } catch (e) {}
                    });
                    this.ambientSourceNodes = [];
                    if (this.ambientGainNode) {
                        try { this.ambientGainNode.disconnect(); } catch (e) {}
                        this.ambientGainNode = null;
                    }
                },

                updateAmbientVolume() {
                    if (this.ambientGainNode && this.ambientAudioCtx) {
                        this.ambientGainNode.gain.setValueAtTime((this.ambientVolume / 100) * 0.4, this.ambientAudioCtx.currentTime);
                    }
                },

                // Spotify-Style Offline Cache Management
                async initOfflineCache() {
                    try {
                        if ('caches' in window) {
                            await caches.open('atoma-offline-audio-v1');
                        }
                        this.updateCacheStorageMetric();
                    } catch (e) {}
                },

                async updateCacheStorageMetric() {
                    if ('storage' in navigator && 'estimate' in navigator.storage) {
                        try {
                            const estimate = await navigator.storage.estimate();
                            const usageBytes = estimate.usage || 0;
                            if (usageBytes < 1024 * 1024) {
                                this.cacheSizeText = (usageBytes / 1024).toFixed(1) + ' KB';
                            } else {
                                this.cacheSizeText = (usageBytes / (1024 * 1024)).toFixed(1) + ' MB';
                            }
                        } catch (e) {
                            this.cacheSizeText = 'Tersedia';
                        }
                    }
                },

                async clearAudioCache() {
                    try {
                        if ('caches' in window) {
                            await caches.delete('atoma-offline-audio-v1');
                            await caches.open('atoma-offline-audio-v1');
                        }
                        this.cacheSizeText = '0 KB';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Cache offline audio berhasil dibersihkan!', type: 'success' });
                        }
                    } catch (e) {
                        console.error('Failed to clear cache:', e);
                    }
                },

                // --- YouTube Controls & Persistent Audio Portal ---
                ensurePermanentAudioHost(force = false) {
                    if (!this.youtubeLoaded && !force) {
                        return null;
                    }

                    // Clean up any stale host from old implementations if present in body
                    const stale = document.getElementById('atoma-global-yt-host');
                    if (stale) stale.remove();

                    let iframe = document.getElementById('atomaYtIframe');
                    if (!iframe) {
                        let portal = document.getElementById('atoma-permanent-audio-portal');
                        if (!portal) {
                            portal = document.createElement('div');
                            portal.id = 'atoma-permanent-audio-portal';
                            portal.style.cssText = 'position: fixed; z-index: 99998; pointer-events: none;';
                            document.documentElement.appendChild(portal);
                        }
                        
                        iframe = document.createElement('iframe');
                        iframe.id = 'atomaYtIframe';
                        iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
                        iframe.setAttribute('allowfullscreen', 'true');
                        iframe.style.cssText = 'position: fixed; left: -9999px; bottom: -9999px; width: 1px; height: 1px; opacity: 0.001; pointer-events: none; border: 0;';
                        
                        iframe.addEventListener('load', () => {
                            this.sendYtListening();
                            setTimeout(() => this.updateYtVolume(), 600);
                        });
                        
                        portal.appendChild(iframe);
                    }

                    if (!iframe.src || iframe.src === 'about:blank' || !iframe.src.includes('youtube.com')) {
                        if (this.ytEmbedUrl) {
                            iframe.src = this.ytEmbedUrl;
                        }
                    }
                    
                    return iframe;
                },

                loadYouTubeIframe() {
                    if (!this.youtubeLoaded) {
                        this.youtubeLoaded = true;
                        return this.ensurePermanentAudioHost(true);
                    }
                    return document.getElementById('atomaYtIframe') || this.ensurePermanentAudioHost(true);
                },

                updateVideoSlotPosition() {
                    if (!this.youtubeLoaded) return;
                    const iframe = document.getElementById('atomaYtIframe');
                    if (!iframe) return;
                    
                    const slot = document.getElementById('atoma-yt-video-slot');
                    if (this.show && this.showAudioDeck && this.showYtVideoFrame && this.musicTab === 'ytmusic' && slot) {
                        const rect = slot.getBoundingClientRect();
                        if (rect.width > 30 && rect.height > 30 && rect.bottom > 0 && rect.top < window.innerHeight) {
                            iframe.style.position = 'fixed';
                            iframe.style.left = `${Math.round(rect.left)}px`;
                            iframe.style.top = `${Math.round(rect.top)}px`;
                            iframe.style.width = `${Math.round(rect.width)}px`;
                            iframe.style.height = `${Math.round(rect.height)}px`;
                            iframe.style.opacity = '1';
                            iframe.style.pointerEvents = 'auto';
                            iframe.style.zIndex = '10001';
                            iframe.style.borderRadius = '1rem';
                            iframe.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.3)';
                            iframe.style.transition = 'none';
                            return;
                        }
                    }
                    
                    // Park off-screen (never display:none so background audio continues)
                    iframe.style.position = 'fixed';
                    iframe.style.left = '-9999px';
                    iframe.style.bottom = '-9999px';
                    iframe.style.top = 'auto';
                    iframe.style.width = '1px';
                    iframe.style.height = '1px';
                    iframe.style.opacity = '0.001';
                    iframe.style.pointerEvents = 'none';
                    iframe.style.zIndex = '-1';
                    iframe.style.borderRadius = '0';
                    iframe.style.boxShadow = 'none';
                },

                sendYtListening() {
                    if (!this.youtubeLoaded) return;
                    const iframe = this.ensurePermanentAudioHost();
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage(JSON.stringify({
                            event: 'listening',
                            id: 1,
                            channel: 'widget'
                        }), '*');
                    }
                },

                loadYtVideo(videoId, title, author, thumbnail) {
                    this.loadYouTubeIframe();
                    if (!this.isOnline) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Sedang offline — beralih otomatis ke Ambient Offline.', type: 'info' });
                        }
                        this.musicTab = 'ambient';
                        if (!this.isAmbientPlaying) {
                            this.toggleAmbientPlayback();
                        }
                        return;
                    }

                    if (this.isAmbientPlaying) {
                        this.toggleAmbientPlayback();
                    }

                    this.ytVideoId = videoId;
                    this.ytTitle = title || 'YouTube Track';
                    this.ytAuthor = author || 'YouTube';
                    this.ytThumbnail = thumbnail || `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`;
                    this.ytEmbedUrl = `https://www.youtube.com/embed/${videoId}?enablejsapi=1&autoplay=1&origin=${encodeURIComponent(window.location.origin)}`;

                    const iframe = this.ensurePermanentAudioHost(true);
                    if (iframe) {
                        iframe.src = this.ytEmbedUrl;
                    }
                    this.isYtPlaying = true;
                    this.saveActiveTimerState();

                    if (window.Livewire && title) {
                        window.Livewire.dispatch('toast', { message: `🎵 ${title}`, type: 'info' });
                    }
                    if ('mediaSession' in navigator) {
                        try {
                            navigator.mediaSession.metadata = new MediaMetadata({
                                title: this.ytTitle,
                                artist: this.ytAuthor,
                                album: 'ATOMA Pomodoro Music',
                                artwork: [{ src: this.ytThumbnail, sizes: '512x512', type: 'image/jpeg' }]
                            });
                        } catch (e) {}
                    }

                    this.$nextTick(() => {
                        this.updateVideoSlotPosition();
                    });

                    // Re-establish API listening and volume sync
                    setTimeout(() => this.sendYtListening(), 600);
                    setTimeout(() => this.updateYtVolume(), 1200);
                    setTimeout(() => this.sendYtListening(), 2000);
                },

                playYtQuery() {
                    if (!this.ytQuery.trim()) return;
                    this.loadYouTubeIframe();
                    if (!this.isOnline) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Pencarian YouTube membutuhkan koneksi internet.', type: 'error' });
                        }
                        return;
                    }
                    this.isYtSearching = true;
                    fetch('/api/youtube/search?q=' + encodeURIComponent(this.ytQuery))
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.video_id) {
                                this.loadYtVideo(data.video_id, data.title, data.author, data.thumbnail);
                                this.ytResults = data.results || [];
                            }
                        })
                        .catch(err => {
                            console.error('YouTube search error:', err);
                        })
                        .finally(() => {
                            this.isYtSearching = false;
                        });
                },

                sendYtCommand(func, args = []) {
                    if (!this.youtubeLoaded) return;
                    const iframe = this.ensurePermanentAudioHost();
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage(JSON.stringify({
                            event: 'command',
                            func: func,
                            args: args
                        }), '*');
                    }
                },

                toggleYtPlayback() {
                    this.loadYouTubeIframe();
                    if (!this.isOnline && !this.isYtPlaying) {
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'YouTube tidak dapat diputar saat offline. Membuka Ambient Offline.', type: 'info' });
                        }
                        this.musicTab = 'ambient';
                        if (!this.isAmbientPlaying) this.toggleAmbientPlayback();
                        return;
                    }

                    const iframe = this.ensurePermanentAudioHost(true);

                    if (this.isYtPlaying) {
                        this.sendYtCommand('pauseVideo');
                        this.isYtPlaying = false;
                    } else {
                        if (this.isAmbientPlaying) this.toggleAmbientPlayback();
                        if (iframe && (!iframe.src || iframe.src === 'about:blank' || !iframe.src.includes(this.ytVideoId))) {
                            iframe.src = this.ytEmbedUrl;
                            setTimeout(() => this.sendYtListening(), 600);
                            setTimeout(() => this.updateYtVolume(), 1200);
                        } else {
                            this.sendYtCommand('playVideo');
                        }
                        this.isYtPlaying = true;
                    }
                    this.saveActiveTimerState();
                    this.$nextTick(() => this.updateVideoSlotPosition());
                },

                updateYtVolume() {
                    this.isYtMuted = (this.ytVolume === 0);
                    this.sendYtCommand('unMute');
                    this.sendYtCommand('setVolume', [this.ytVolume]);
                    localStorage.setItem('atoma_yt_volume', this.ytVolume);
                },

                toggleYtMute() {
                    this.isYtMuted = !this.isYtMuted;
                    if (this.isYtMuted) {
                        this.sendYtCommand('mute');
                    } else {
                        if (this.ytVolume === 0) {
                            this.ytVolume = 50;
                            localStorage.setItem('atoma_yt_volume', 50);
                        }
                        this.sendYtCommand('unMute');
                        this.sendYtCommand('setVolume', [this.ytVolume]);
                    }
                    this.saveActiveTimerState();
                },

                loadYtVolume() {
                    const saved = localStorage.getItem('atoma_yt_volume');
                    if (saved !== null) {
                        this.ytVolume = parseInt(saved) || 80;
                    }
                },

                get activeFolder() {
                    return this.playlistFolders.find(f => f.id === this.activeFolderId) || this.playlistFolders[0] || { id: 'default', name: 'Favorit', icon: '📁', tracks: [] };
                },

                get currentFolderTracks() {
                    if (this.activeFolder && this.activeFolder.tracks && this.activeFolder.tracks.length > 0) {
                        if (!this.ytVideoId || this.activeFolder.tracks.some(t => t.video_id === this.ytVideoId)) {
                            return this.activeFolder.tracks;
                        }
                    }
                    if (this.ytVideoId) {
                        const folderWithTrack = this.playlistFolders.find(f => f.tracks && f.tracks.some(t => t.video_id === this.ytVideoId));
                        if (folderWithTrack && folderWithTrack.tracks && folderWithTrack.tracks.length > 0) {
                            return folderWithTrack.tracks;
                        }
                    }
                    if (this.activeFolder && this.activeFolder.tracks && this.activeFolder.tracks.length > 0) {
                        return this.activeFolder.tracks;
                    }
                    const anyFolder = this.playlistFolders.find(f => f.tracks && f.tracks.length > 0);
                    return anyFolder ? anyFolder.tracks : [];
                },

                playNextYtTrack(isAuto = false) {
                    const tracks = this.currentFolderTracks;
                    if (!tracks || tracks.length === 0) {
                        if (this.ytResults && this.ytResults.length > 1) {
                            const curIdx = this.ytResults.findIndex(r => r.video_id === this.ytVideoId);
                            const nextIdx = (curIdx + 1) % this.ytResults.length;
                            const next = this.ytResults[nextIdx];
                            this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                        }
                        return;
                    }

                    if (tracks.length === 1) {
                        this.sendYtCommand('seekTo', [0, true]);
                        this.sendYtCommand('playVideo');
                        this.isYtPlaying = true;
                        return;
                    }

                    if (this.isYtShuffle) {
                        // Exclude current track when possible to avoid repeating the exact same track
                        const candidates = tracks.filter(t => t.video_id !== this.ytVideoId);
                        const pool = candidates.length > 0 ? candidates : tracks;
                        const randIdx = Math.floor(Math.random() * pool.length);
                        const next = pool[randIdx];
                        this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                        return;
                    }

                    const curIdx = tracks.findIndex(item => item.video_id === this.ytVideoId);
                    if (curIdx === -1) {
                        const next = tracks[0];
                        this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                    } else if (curIdx + 1 < tracks.length) {
                        const next = tracks[curIdx + 1];
                        this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                    } else {
                        // Reached end of playlist
                        if (!isAuto || this.ytLoopMode === 'all' || this.isYtAutoNext) {
                            const next = tracks[0];
                            this.loadYtVideo(next.video_id, next.title, next.author, next.thumbnail);
                        } else {
                            this.isYtPlaying = false;
                        }
                    }
                },

                playPrevYtTrack() {
                    const tracks = this.currentFolderTracks;
                    if (!tracks || tracks.length === 0) {
                        if (this.ytResults && this.ytResults.length > 1) {
                            const curIdx = this.ytResults.findIndex(r => r.video_id === this.ytVideoId);
                            const prevIdx = (curIdx - 1 + this.ytResults.length) % this.ytResults.length;
                            const prev = this.ytResults[prevIdx];
                            this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                        }
                        return;
                    }

                    if (tracks.length === 1) {
                        this.sendYtCommand('seekTo', [0, true]);
                        this.sendYtCommand('playVideo');
                        this.isYtPlaying = true;
                        return;
                    }

                    if (this.isYtShuffle) {
                        const candidates = tracks.filter(t => t.video_id !== this.ytVideoId);
                        const pool = candidates.length > 0 ? candidates : tracks;
                        const randIdx = Math.floor(Math.random() * pool.length);
                        const prev = pool[randIdx];
                        this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                        return;
                    }

                    const curIdx = tracks.findIndex(item => item.video_id === this.ytVideoId);
                    if (curIdx > 0) {
                        const prev = tracks[curIdx - 1];
                        this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                    } else {
                        const prev = tracks[tracks.length - 1];
                        this.loadYtVideo(prev.video_id, prev.title, prev.author, prev.thumbnail);
                    }
                },

                // --- Universal Audio Track Navigation (Float & PiP & Hardware Media Keys) ---
                playNextTrack() {
                    this.triggerHaptic('light');
                    if (this.isYtPlaying || (this.ytVideoId && !this.isAmbientPlaying)) {
                        this.playNextYtTrack();
                    } else if (this.isAmbientPlaying) {
                        const presets = this.ambientPresets;
                        const curIdx = presets.findIndex(p => p.id === this.currentAmbientPreset);
                        const nextIdx = (curIdx + 1) % presets.length;
                        this.selectAmbientPreset(presets[nextIdx].id);
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: `Suara: ${presets[nextIdx].icon} ${presets[nextIdx].name}`, 
                                type: 'info' 
                            });
                        }
                    } else if (this.currentFolderTracks && this.currentFolderTracks.length > 0) {
                        this.playNextYtTrack();
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                    this.saveActiveTimerState();
                },

                playPrevTrack() {
                    this.triggerHaptic('light');
                    if (this.isYtPlaying || (this.ytVideoId && !this.isAmbientPlaying)) {
                        this.playPrevYtTrack();
                    } else if (this.isAmbientPlaying) {
                        const presets = this.ambientPresets;
                        const curIdx = presets.findIndex(p => p.id === this.currentAmbientPreset);
                        const prevIdx = (curIdx - 1 + presets.length) % presets.length;
                        this.selectAmbientPreset(presets[prevIdx].id);
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { 
                                message: `Suara: ${presets[prevIdx].icon} ${presets[prevIdx].name}`, 
                                type: 'info' 
                            });
                        }
                    } else if (this.currentFolderTracks && this.currentFolderTracks.length > 0) {
                        this.playPrevYtTrack();
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                    this.saveActiveTimerState();
                },

                cycleYtLoop() {
                    if (this.ytLoopMode === 'off') {
                        this.ytLoopMode = 'all';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Loop Seluruh Playlist Aktif', type: 'info' });
                        }
                    } else if (this.ytLoopMode === 'all') {
                        this.ytLoopMode = 'single';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Ulang 1 Lagu (Repeat 1) Aktif', type: 'info' });
                        }
                    } else {
                        this.ytLoopMode = 'off';
                        if (window.Livewire) {
                            window.Livewire.dispatch('toast', { message: 'Loop Dinonaktifkan', type: 'info' });
                        }
                    }
                    this.saveActiveTimerState();
                },

                toggleYtShuffle() {
                    this.isYtShuffle = !this.isYtShuffle;
                    if (window.Livewire) {
                        window.Livewire.dispatch('toast', { 
                            message: this.isYtShuffle ? 'Acak Lagu (Shuffle) Aktif' : 'Acak Lagu (Shuffle) Mati', 
                            type: 'info' 
                        });
                    }
                    this.saveActiveTimerState();
                },

                toggleYtAutoNext() {
                    this.isYtAutoNext = !this.isYtAutoNext;
                    if (window.Livewire) {
                        window.Livewire.dispatch('toast', { 
                            message: this.isYtAutoNext ? 'Auto-Next Aktif' : 'Auto-Next Mati', 
                            type: 'info' 
                        });
                    }
                    this.saveActiveTimerState();
                },

                onYtTrackEnded() {
                    if (this.ytLoopMode === 'single') {
                        // Smoothly restart track from 0 without re-mounting iframe
                        this.sendYtCommand('seekTo', [0, true]);
                        this.sendYtCommand('playVideo');
                        this.isYtPlaying = true;
                    } else if (this.isYtAutoNext || this.ytLoopMode === 'all') {
                        this.playNextYtTrack(true);
                    } else {
                        this.isYtPlaying = false;
                    }
                    this.saveActiveTimerState();
                },

                get isCurrentTrackSaved() {
                    return this.playlistFolders.some(f => f.tracks && f.tracks.some(t => t.video_id === this.ytVideoId));
                },

                openSaveToPlaylistModal(videoId, title, author, thumbnail) {
                    this.targetTrackToSave = {
                        video_id: videoId || this.ytVideoId,
                        title: title || this.ytTitle,
                        author: author || this.ytAuthor,
                        thumbnail: thumbnail || this.ytThumbnail
                    };
                    this.newFolderName = '';
                    this.showPlaylistModal = true;
                },

                isTrackInFolder(folderId, videoId) {
                    const folder = this.playlistFolders.find(f => f.id === folderId);
                    return folder && folder.tracks ? folder.tracks.some(t => t.video_id === videoId) : false;
                },

                toggleTrackInFolder(folderId, track) {
                    if (!track || !track.video_id) return;
                    if (this.isTrackInFolder(folderId, track.video_id)) {
                        this.removeTrackFromFolder(folderId, track.video_id);
                    } else {
                        this.addTrackToFolder(folderId, track);
                    }
                },

                addTrackToFolder(folderId, track) {
                    const folder = this.playlistFolders.find(f => f.id === folderId);
                    if (!folder) return;
                    if (!folder.tracks) folder.tracks = [];
                    if (!folder.tracks.some(t => t.video_id === track.video_id)) {
                        folder.tracks.unshift({
                            video_id: track.video_id,
                            title: track.title || 'YouTube Track',
                            author: track.author || 'YouTube',
                            thumbnail: track.thumbnail || `https://i.ytimg.com/vi/${track.video_id}/hqdefault.jpg`
                        });
                        this.saveSavedPlaylist();
                    }
                },

                removeTrackFromFolder(folderId, videoId) {
                    const folder = this.playlistFolders.find(f => f.id === folderId);
                    if (!folder || !folder.tracks) return;
                    folder.tracks = folder.tracks.filter(t => t.video_id !== videoId);
                    this.saveSavedPlaylist();
                },

                createFolderFromModal() {
                    const name = this.newFolderName.trim();
                    if (!name) return;

                    if (!this.isPro && this.playlistFolders.length >= this.maxFolders) {
                        window.Livewire?.dispatch('toast', { 
                            message: 'Batas Akun Free: Maksimal 2 folder playlist YouTube. Upgrade ke ATOMA PRO untuk membuat folder tanpa batas!', 
                            type: 'warning' 
                        });
                        window.dispatchEvent(new CustomEvent('open-pro-modal'));
                        return;
                    }

                    const icons = ['📁', '🎧', '🧠', '🎹', '☕', '⚡', '🌌', '🌧️'];
                    const randIcon = icons[Math.floor(Math.random() * icons.length)];
                    const newFolder = {
                        id: 'folder_' + Date.now(),
                        name: name,
                        icon: randIcon,
                        tracks: []
                    };

                    if (this.targetTrackToSave && this.targetTrackToSave.video_id) {
                        newFolder.tracks.push({
                            video_id: this.targetTrackToSave.video_id,
                            title: this.targetTrackToSave.title || 'YouTube Track',
                            author: this.targetTrackToSave.author || 'YouTube',
                            thumbnail: this.targetTrackToSave.thumbnail || `https://i.ytimg.com/vi/${this.targetTrackToSave.video_id}/hqdefault.jpg`
                        });
                    }

                    this.playlistFolders.push(newFolder);
                    this.activeFolderId = newFolder.id;
                    this.newFolderName = '';
                    this.saveSavedPlaylist();
                },

                deleteFolder(folderId) {
                    if (this.playlistFolders.length <= 1) return;
                    this.playlistFolders = this.playlistFolders.filter(f => f.id !== folderId);
                    if (this.activeFolderId === folderId) {
                        this.activeFolderId = this.playlistFolders[0].id;
                    }
                    this.saveSavedPlaylist();
                },

                startFocusTask(detail) {
                    if (!detail) return;
                    const taskId = detail.taskId ? String(detail.taskId) : '';
                    if (taskId) {
                        this.selectedTaskId = taskId;
                        this.selectedTaskTitle = detail.taskTitle || '';
                        if (!this.activeTasks.some(t => String(t.id) === taskId)) {
                            this.activeTasks.push({
                                id: taskId,
                                title: detail.taskTitle || 'Tugas',
                                estimated_sessions: 1,
                                completed_sessions: 0,
                                estimated_minutes: 25
                            });
                        }
                    } else {
                        this.selectedTaskId = '';
                        this.selectedTaskTitle = '';
                    }
                    this.show = true;
                    this.mode = 'work';
                    this.timeLeft = this.settings.work * 60;
                    this.saveActiveTimerState();
                },

                startEditFolder(folder) {
                    if (!folder) return;
                    this.editingFolderId = folder.id;
                    this.editFolderName = folder.name;
                    this.editFolderIcon = folder.icon || '📁';
                },

                saveFolderRename() {
                    if (!this.editingFolderId) return;
                    const name = this.editFolderName.trim();
                    if (!name) return;
                    const folder = this.playlistFolders.find(f => f.id === this.editingFolderId);
                    if (folder) {
                        folder.name = name;
                        folder.icon = this.editFolderIcon || '📁';
                        this.saveSavedPlaylist();
                        this.saveActiveTimerState();
                    }
                    this.editingFolderId = null;
                },

                cancelEditFolder() {
                    this.editingFolderId = null;
                },

                saveSavedPlaylist() {
                    localStorage.setItem('atoma_yt_playlist_folders', JSON.stringify(this.playlistFolders));
                    this.syncPlaylistToServer();
                },

                syncPlaylistToServer() {
                    if (!this.isOnline) return;
                    fetch('/api/pomodoro/playlists', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ folders: this.playlistFolders })
                    }).catch(e => console.log('Playlist sync error', e));
                },

                loadSavedPlaylist() {
                    // 1. Optimistic local cache load to prevent UI flicker
                    const savedFolders = localStorage.getItem('atoma_yt_playlist_folders');
                    let hasLocal = false;
                    if (savedFolders) {
                        try {
                            const parsed = JSON.parse(savedFolders);
                            if (Array.isArray(parsed) && parsed.length > 0) {
                                this.playlistFolders = parsed;
                                if (!this.isPro && this.playlistFolders.length > this.maxFolders) {
                                    this.playlistFolders = this.playlistFolders.slice(0, this.maxFolders);
                                }
                                if (this.playlistFolders.length > 0) {
                                    this.activeFolderId = this.playlistFolders[0].id;
                                }
                                hasLocal = true;
                            }
                        } catch (e) {
                            this.playlistFolders = [];
                        }
                    }

                    // 2. Fallback to default presets if neither local nor cloud has loaded yet
                    if (!hasLocal) {
                        const legacySaved = localStorage.getItem('atoma_yt_saved_playlist');
                        let legacyTracks = [];
                        if (legacySaved) {
                            try { legacyTracks = JSON.parse(legacySaved); } catch (e) {}
                        }

                        this.playlistFolders = [
                            {
                                id: 'focus',
                                name: 'Deep Focus',
                                icon: '🧠',
                                tracks: legacyTracks.length > 0 ? legacyTracks : [
                                    { video_id: '5qap5aO4i9A', title: 'Lofi Beats - Deep Focus', author: 'Lofi Girl', thumbnail: 'https://i.ytimg.com/vi/5qap5aO4i9A/hqdefault.jpg' },
                                    { video_id: '2OEL4P1rub0', title: 'Peaceful Piano Focus', author: 'Piano Relaxation', thumbnail: 'https://i.ytimg.com/vi/2OEL4P1rub0/hqdefault.jpg' }
                                ]
                            },
                            {
                                id: 'chill',
                                name: 'Chill & Relax',
                                icon: '☕',
                                tracks: [
                                    { video_id: '4xDzrJKXOOY', title: 'Rain & Ambient Beats', author: 'Ambient Sound', thumbnail: 'https://i.ytimg.com/vi/4xDzrJKXOOY/hqdefault.jpg' }
                                ]
                            }
                        ];
                        this.activeFolderId = 'focus';
                    }

                    // 3. Always synchronize with cloud database for seamless multi-device library sharing across browsers
                    if (this.isOnline) {
                        fetch('/api/pomodoro/playlists')
                            .then(res => res.json())
                            .then(data => {
                                if (data && Array.isArray(data.folders) && data.folders.length > 0) {
                                    let loaded = data.folders;
                                    if (!this.isPro && loaded.length > this.maxFolders) {
                                        loaded = loaded.slice(0, this.maxFolders);
                                    }
                                    this.playlistFolders = loaded;
                                    const folderExists = this.playlistFolders.some(f => f.id === this.activeFolderId);
                                    if (!folderExists && this.playlistFolders.length > 0) {
                                        this.activeFolderId = this.playlistFolders[0].id;
                                    }
                                    localStorage.setItem('atoma_yt_playlist_folders', JSON.stringify(this.playlistFolders));
                                } else if (!data || !Array.isArray(data.folders) || data.folders.length === 0) {
                                    // If cloud database has no folders yet, upload the current library to cloud database
                                    this.syncPlaylistToServer();
                                }
                            })
                            .catch(() => {});
                    }
                },

                toggleShow() {
                    this.show = !this.show;
                    this.saveActiveTimerState();
                },

                closeWidget() {
                    this.show = false;
                    this.saveActiveTimerState();
                },

                saveActiveTimerState() {
                    let targetEnd = null;
                    if (this.isRunning && this.mode !== 'flowtime') {
                        targetEnd = this.targetEndTime || (Date.now() + this.timeLeft * 1000);
                    }
                    const state = {
                        savedDate: new Date().toLocaleDateString('en-CA'),
                        show: this.show,
                        mode: this.mode,
                        isRunning: this.isRunning,
                        isPaused: this.isPaused,
                        timeLeft: this.timeLeft,
                        flowtimeSeconds: this.flowtimeSeconds,
                        targetEndTime: targetEnd,
                        startedAt: this.startedAt,
                        sessionCount: this.sessionCount,
                        selectedTaskId: this.selectedTaskId,
                        selectedTaskTitle: this.selectedTaskTitle,
                        showAudioDeck: this.showAudioDeck,
                        showYtVideoFrame: this.showYtVideoFrame,
                        musicTab: this.musicTab,
                        activeFolderId: this.activeFolderId,
                        ytVideoId: this.ytVideoId,
                        ytTitle: this.ytTitle,
                        ytAuthor: this.ytAuthor,
                        ytThumbnail: this.ytThumbnail,
                        isYtPlaying: this.isYtPlaying,
                        isAmbientPlaying: this.isAmbientPlaying,
                        currentAmbientPreset: this.currentAmbientPreset,
                        isYtShuffle: this.isYtShuffle,
                        ytLoopMode: this.ytLoopMode,
                        isYtAutoNext: this.isYtAutoNext,
                        ytVolume: this.ytVolume,
                        isYtMuted: this.isYtMuted
                    };
                    try {
                        localStorage.setItem('atoma_pomodoro_active_state', JSON.stringify(state));
                    } catch (e) {}
                },

                loadActiveTimerState() {
                    const saved = localStorage.getItem('atoma_pomodoro_active_state');
                    if (!saved) {
                        this.timeLeft = this.settings.work * 60;
                        return;
                    }
                    try {
                        const state = JSON.parse(saved);
                        if (state.show !== undefined) this.show = Boolean(state.show);
                        if (state.mode) this.mode = state.mode;
                        if (state.isPaused !== undefined) this.isPaused = Boolean(state.isPaused);
                        if (state.flowtimeSeconds !== undefined) this.flowtimeSeconds = Number(state.flowtimeSeconds);
                        if (state.sessionCount !== undefined) this.sessionCount = Number(state.sessionCount);
                        if (state.targetEndTime) this.targetEndTime = Number(state.targetEndTime);
                        if (state.startedAt) this.startedAt = Number(state.startedAt);
                        
                        // Check if saved selected task actually exists
                        if (state.selectedTaskId && this.activeTasks.some(t => String(t.id) === String(state.selectedTaskId))) {
                            this.selectedTaskId = String(state.selectedTaskId);
                            const found = this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId));
                            this.selectedTaskTitle = found ? found.title : '';
                        } else {
                            this.selectedTaskId = '';
                            this.selectedTaskTitle = '';
                        }

                        if (state.showAudioDeck !== undefined) this.showAudioDeck = Boolean(state.showAudioDeck);
                        if (state.showYtVideoFrame !== undefined) this.showYtVideoFrame = Boolean(state.showYtVideoFrame);
                        if (state.musicTab !== undefined) this.musicTab = state.musicTab;
                        if (state.activeFolderId !== undefined) this.activeFolderId = state.activeFolderId;
                        if (state.ytVideoId) this.ytVideoId = state.ytVideoId;
                        if (state.ytTitle) this.ytTitle = state.ytTitle;
                        if (state.ytAuthor) this.ytAuthor = state.ytAuthor;
                        if (state.ytThumbnail) this.ytThumbnail = state.ytThumbnail;
                        if (state.currentAmbientPreset) this.currentAmbientPreset = state.currentAmbientPreset;
                        if (state.isYtShuffle !== undefined) this.isYtShuffle = Boolean(state.isYtShuffle);
                        if (state.ytLoopMode !== undefined) this.ytLoopMode = state.ytLoopMode;
                        if (state.isYtAutoNext !== undefined) this.isYtAutoNext = Boolean(state.isYtAutoNext);
                        if (state.ytVolume !== undefined) this.ytVolume = Number(state.ytVolume);
                        if (state.isYtMuted !== undefined) this.isYtMuted = Boolean(state.isYtMuted);

                        // Check if day has changed (midnight rollover / new day)
                        const today = new Date().toLocaleDateString('en-CA');
                        const isNewDay = Boolean(state.savedDate && state.savedDate !== today);

                        if (isNewDay) {
                            // Settle yesterday's unfinished work session so yesterday's effort is rewarded
                            if (state.mode === 'work' && state.timeLeft !== undefined) {
                                const totalSecs = (this.settings.work || 25) * 60;
                                const elapsedSecs = totalSecs - state.timeLeft;
                                const elapsedMinutes = Math.floor(elapsedSecs / 60);
                                if (elapsedMinutes >= 1 && window.Livewire) {
                                    window.Livewire.dispatch('pomodoro-completed', {
                                        taskId: state.selectedTaskId || null,
                                        minutes: elapsedMinutes,
                                        ambientSound: state.isYtPlaying ? 'youtube' : (state.isAmbientPlaying ? state.currentAmbientPreset : null),
                                        mode: 'work'
                                    });
                                }
                            }
                            // Fresh day reset: 25 mins full for today
                            this.timeLeft = this.settings.work * 60;
                            this.isRunning = false;
                            this.isPaused = false;
                            this.flowtimeSeconds = 0;
                            this.mode = 'work';
                            this.sessionCount = 0;
                            this.targetEndTime = null;
                            this.startedAt = null;
                            this.saveActiveTimerState();
                        } else {
                            if (this.mode === 'flowtime') {
                                this.isRunning = false;
                            } else if (state.isRunning && state.targetEndTime) {
                                const now = Date.now();
                                const diff = Math.round((state.targetEndTime - now) / 1000);
                                if (diff > 0) {
                                    this.timeLeft = diff;
                                    this.startTimer();
                                } else {
                                    this.timeLeft = 0;
                                    this.timerComplete();
                                }
                            } else {
                                this.timeLeft = (state.timeLeft !== undefined && state.timeLeft > 0) ? state.timeLeft : this.totalTimeForMode;
                                this.isRunning = false;
                            }
                        }

                        // Restore Audio playback state across tab/navigation/reload (lazy loaded)
                        if (state.isYtPlaying) {
                            this.isYtPlaying = true;
                            this.loadYouTubeIframe();
                            setTimeout(() => {
                                this.sendYtCommand('playVideo');
                                this.sendYtListening();
                                this.updateYtVolume();
                                this.updateVideoSlotPosition();
                            }, 600);
                        }
                        if (state.isAmbientPlaying) {
                            setTimeout(() => {
                                if (!this.isAmbientPlaying) {
                                    this.toggleAmbientPlayback();
                                }
                            }, 400);
                        }
                    } catch (e) {
                        this.timeLeft = this.settings.work * 60;
                    }
                },

                toggleTimer() {
                    if (this.isRunning) {
                        this.pauseTimer();
                    } else {
                        this.startTimer();
                    }
                },

                sendFocusHeartbeat() {
                    if (!this.isRunning) return;
                    if (this.mode !== 'work' && this.mode !== 'flowtime') return;

                    const minutesRemaining = this.mode === 'flowtime' 
                        ? Math.floor(this.flowtimeSeconds / 60) 
                        : Math.max(1, Math.ceil(this.timeLeft / 60));

                    fetch('/api/focus/heartbeat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            mode: this.mode,
                            task_title: this.selectedTaskTitle || 'Sesi Fokus Belajar',
                            minutes_remaining: minutesRemaining
                        })
                    }).catch(() => {});
                },

                stopFocusHeartbeat() {
                    fetch('/api/focus/stop', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    }).catch(() => {});
                },

                startTimer() {
                    if (this.interval) {
                        clearInterval(this.interval);
                        this.interval = null;
                    }
                    this.isRunning = true;
                    this.isPaused = false;

                    if (this.mode === 'flowtime') {
                        this.startedAt = Date.now() - (this.flowtimeSeconds * 1000);
                        this.targetEndTime = null;
                    } else {
                        this.targetEndTime = Date.now() + (this.timeLeft * 1000);
                        this.startedAt = null;
                    }

                    this.saveActiveTimerState();
                    this.sendFocusStart();
                    this.sendFocusHeartbeat();
                    this.requestWakeLock();
                    this.syncWithNativeAndroid();

                    // Start Web Worker if available, fallback to interval
                    if (this.timerWorker) {
                        this.timerWorker.postMessage({ command: 'start', interval: 1000 });
                    }
                    this.interval = setInterval(() => {
                        if (!this.timerWorker) {
                            this.onTimerTick();
                        }
                    }, 1000);
                },

                pauseTimer() {
                    this.isRunning = false;
                    this.isPaused = true;
                    if (this.interval) {
                        clearInterval(this.interval);
                        this.interval = null;
                    }
                    if (this.timerWorker) {
                        this.timerWorker.postMessage({ command: 'stop' });
                    }
                    this.targetEndTime = null;
                    this.releaseWakeLock();
                    this.saveActiveTimerState();
                    this.stopFocusHeartbeat();
                    this.syncWithNativeAndroid();
                    document.title = 'ATOMA';
                },

                resetTimer() {
                    // 1. Single-Entry Atomic Reset: Stop worker & interval immediately
                    if (this.interval) {
                        clearInterval(this.interval);
                        this.interval = null;
                    }
                    if (this.timerWorker) {
                        this.timerWorker.postMessage({ command: 'stop' });
                    }

                    // 2. Clear running flags & timestamps BEFORE resetting time values
                    this.isRunning = false;
                    this.isPaused = false;
                    this.targetEndTime = null;
                    this.startedAt = null;

                    // 3. Clear mode saved time caches
                    this.savedWorkTimeLeft = null;
                    this.savedShortBreakTimeLeft = null;
                    this.savedLongBreakTimeLeft = null;

                    // 4. Reset time according to current mode
                    if (this.mode === 'flowtime') {
                        this.flowtimeSeconds = 0;
                        this.timeLeft = 0;
                    } else {
                        const defaultMin = (this.mode === 'shortBreak' ? (this.settings.shortBreak || 5) : (this.mode === 'longBreak' ? (this.settings.longBreak || 15) : (this.settings.work || 25)));
                        this.timeLeft = Math.max(60, Number(defaultMin) * 60 || 1500);
                    }

                    // 5. Release Wake Lock, stop server heartbeat, and sync Android
                    this.releaseWakeLock();
                    this.stopFocusHeartbeat();
                    this.syncWithNativeAndroid();
                    document.title = 'ATOMA';

                    // 6. Explicitly save clean reset state to localStorage
                    this.saveActiveTimerState();

                    if (window.Livewire) {
                        window.Livewire.dispatch('toast', { message: 'Timer di-reset.', type: 'info' });
                    }
                },

                switchMode(customMinutes = null) {
                    if (this.mode === 'work') {
                        this.sessionCount++;
                        this.todaySessionsCount++;
                        const minutesToAdd = customMinutes !== null ? customMinutes : (this.settings.work || 25);
                        this.todayFocusMinutes += minutesToAdd;
                        this.saveTelemetry();

                        // Auto pause audio if enabled
                        if (this.settings.autoPauseMusic) {
                            this.autoPauseAudio();
                        }

                        if (this.sessionCount % (this.settings.interval || 4) === 0) {
                            this.mode = 'longBreak';
                            this.timeLeft = (this.settings.longBreak || 15) * 60;
                        } else {
                            this.mode = 'shortBreak';
                            this.timeLeft = (this.settings.shortBreak || 5) * 60;
                        }
                        this.shuffleBreakTip();

                        this.saveActiveTimerState();

                        if (this.settings.autoStartBreak) {
                            this.startTimer();
                        }
                    } else {
                        this.mode = 'work';
                        this.timeLeft = (this.settings.work || 25) * 60;
                        this.saveActiveTimerState();
                        if (this.settings.autoStartWork) {
                            this.startTimer();
                        }
                    }
                },

                timerComplete() {
                    this.pauseTimer();
                    this.releaseWakeLock();
                    this.triggerHaptic('success');
                    this.syncWithNativeAndroid();
                    
                    // 1. Client-Side Synthesized Chime (Reliable, Zero Network)
                    this.playWebAudioChime();

                    // 2. Play configured notification sound (custom or chosen preset)
                    if (window.AtomaAudio) {
                        window.AtomaAudio.play(null);
                    } else if (this.$refs.alarmSound) {
                        this.$refs.alarmSound.play().catch(e => console.log('Alarm play error', e));
                    }
                    
                    if (window.AtomaNativeNotification) {
                        window.AtomaNativeNotification.notifyNow({
                            title: this.mode === 'work' ? '🎉 Focus Time Selesai!' : '⚡ Istirahat Selesai!',
                            body: this.mode === 'work' ? 'Waktunya istirahat sejenak.' : 'Kembali fokus ke tugasmu!',
                            sound: 'chime'
                        });
                    } else if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                        new Notification(this.mode === 'work' ? '🎉 Focus Time Selesai!' : '⚡ Istirahat Selesai!', {
                            body: this.mode === 'work' ? 'Waktunya istirahat sejenak.' : 'Kembali fokus ke tugasmu!',
                            icon: '/favicon.ico'
                        });
                    }
                    
                    if (this.mode === 'work') {
                        if (window.Livewire) {
                            let ambient = this.isYtPlaying ? 'youtube' : (this.isAmbientPlaying ? this.currentAmbientPreset : null);
                            window.Livewire.dispatch('pomodoro-completed', { 
                                taskId: this.selectedTaskId, 
                                minutes: this.settings.work,
                                ambientSound: ambient,
                                mode: 'work'
                            });
                        }
                    }
                    
                    this.switchMode();
                    this.fetchTodaySessions();
                    this.show = true;
                    this.saveActiveTimerState();
                },

                toggleSettings() {
                    this.showSettings = !this.showSettings;
                },

                saveSettings() {
                    localStorage.setItem('atoma_pomodoro_settings', JSON.stringify(this.settings));
                    this.showSettings = false;
                    this.setMode(this.mode);
                },

                async addQuickTask() {
                    let title = this.quickTaskInput ? this.quickTaskInput.trim() : '';
                    if (!title) return;
                    this.isAddingQuickTask = true;
                    try {
                        const res = await fetch('/api/widget/quick-task', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ title: title })
                        });
                        const data = await res.json();
                        if (data.success && data.task) {
                            this.activeTasks.unshift(data.task);
                            this.quickTaskInput = '';
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Tugas berhasil ditambahkan!', type: 'success' }
                            }));
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Gagal menambahkan tugas', type: 'error' }
                            }));
                        }
                    } catch (e) {
                        console.error('Error adding quick task:', e);
                    } finally {
                        this.isAddingQuickTask = false;
                    }
                },

                async toggleTaskCompletion(task) {
                    if (!task) return;
                    task.is_completed = !task.is_completed;
                    try {
                        const res = await fetch('/api/widget/toggle-task', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ task_id: task.id })
                        });
                        const data = await res.json();
                        if (data.success) {
                            if (task.is_completed) {
                                window.dispatchEvent(new CustomEvent('play-sound', { detail: { type: 'success' } }));
                            }
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { 
                                    message: (data.evaluation && data.evaluation.message) ? data.evaluation.message : (data.message || 'Status tugas diperbarui!'), 
                                    type: (data.evaluation && data.evaluation.type) ? data.evaluation.type : 'info' 
                                }
                            }));
                            window.dispatchEvent(new CustomEvent('profile-updated'));
                        }
                    } catch (e) {
                        console.error('Error toggling task:', e);
                    }
                },

                saveQuickNoteDraft() {
                    localStorage.setItem('atoma_widget_quick_note', this.quickNoteContent);
                },

                async exportNoteToJournal() {
                    let text = this.quickNoteContent ? this.quickNoteContent.trim() : '';
                    if (!text) return;
                    this.isSavingQuickNote = true;
                    try {
                        const res = await fetch('/api/widget/quick-note', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ content: text })
                        });
                        const data = await res.json();
                        if (data.success) {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { message: data.message || 'Catatan tersimpan ke Jurnal!', type: 'success' }
                            }));
                        }
                    } catch (e) {
                        console.error('Error exporting note:', e);
                    } finally {
                        this.isSavingQuickNote = false;
                    }
                },

                loadSettings() {
                    const saved = localStorage.getItem('atoma_pomodoro_settings');
                    if (saved) {
                        try {
                            this.settings = Object.assign(this.settings, JSON.parse(saved));
                        } catch (e) {}
                    }
                },

                saveTelemetry() {
                    const data = {
                        date: new Date().toISOString().split('T')[0],
                        minutes: this.todayFocusMinutes,
                        sessions: this.todaySessionsCount
                    };
                    localStorage.setItem('atoma_pomodoro_telemetry', JSON.stringify(data));
                },

                loadTelemetry() {
                    const saved = localStorage.getItem('atoma_pomodoro_telemetry');
                    if (saved) {
                        try {
                            const parsed = JSON.parse(saved);
                            const today = new Date().toISOString().split('T')[0];
                            if (parsed.date === today) {
                                this.todayFocusMinutes = parsed.minutes || 0;
                                this.todaySessionsCount = parsed.sessions || 0;
                            }
                        } catch (e) {}
                    }
                },

                // Mobile Haptic Feedback
                triggerHaptic(type = 'light') {
                    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
                        try {
                            if (type === 'light') navigator.vibrate(15);
                            else if (type === 'success') navigator.vibrate([30, 50, 60]);
                            else if (type === 'warning') navigator.vibrate([50, 50, 50]);
                        } catch (e) {}
                    }
                },

                // Draggable Floating Bubble with Snap to Edge
                startDrag(e) {
                    if (e.target.closest('button') || e.target.closest('input') || e.target.closest('a')) {
                        return;
                    }

                    const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
                    const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;
                    this.dragStartX = clientX;
                    this.dragStartY = clientY;
                    this.hasDragged = false;

                    const el = e.currentTarget;
                    const rect = el.getBoundingClientRect();
                    this.initialX = rect.left;
                    this.initialY = rect.top;

                    const onMove = (moveEvt) => {
                        const curX = moveEvt.type.startsWith('touch') ? moveEvt.touches[0].clientX : moveEvt.clientX;
                        const curY = moveEvt.type.startsWith('touch') ? moveEvt.touches[0].clientY : moveEvt.clientY;
                        const deltaX = curX - this.dragStartX;
                        const deltaY = curY - this.dragStartY;

                        if (Math.hypot(deltaX, deltaY) > 6) {
                            this.isDragging = true;
                            this.hasDragged = true;
                        }

                        if (this.isDragging) {
                            const floatWidth = el.offsetWidth || 180;
                            const floatHeight = el.offsetHeight || 50;
                            let newX = this.initialX + deltaX;
                            let newY = this.initialY + deltaY;

                            // Keep inside screen viewport (avoid sticky header & bottom nav)
                            newX = Math.max(10, Math.min(window.innerWidth - floatWidth - 10, newX));
                            newY = Math.max(60, Math.min(window.innerHeight - floatHeight - 75, newY));

                            this.customX = newX;
                            this.customY = newY;
                        }
                    };

                    const onEnd = () => {
                        window.removeEventListener('mousemove', onMove);
                        window.removeEventListener('mouseup', onEnd);
                        window.removeEventListener('touchmove', onMove);
                        window.removeEventListener('touchend', onEnd);

                        if (this.isDragging) {
                            this.isDragging = false;
                            // Magnetic snap to nearest edge
                            const floatWidth = el.offsetWidth || 180;
                            const midX = window.innerWidth / 2;
                            if (this.customX + floatWidth / 2 < midX) {
                                this.customX = 16;
                                this.dockSide = 'left';
                            } else {
                                this.customX = window.innerWidth - floatWidth - 16;
                                this.dockSide = 'right';
                            }
                            this.triggerHaptic('light');
                            this.saveFloatPosition();
                        }

                        setTimeout(() => {
                            this.hasDragged = false;
                        }, 150);
                    };

                    window.addEventListener('mousemove', onMove, { passive: false });
                    window.addEventListener('mouseup', onEnd);
                    window.addEventListener('touchmove', onMove, { passive: false });
                    window.addEventListener('touchend', onEnd);
                },

                onFloatClick() {
                    if (this.hasDragged) return;
                    this.triggerHaptic('light');
                    this.toggleShow();
                },

                toggleCompactMode() {
                    this.isCompact = !this.isCompact;
                    try {
                        localStorage.setItem('atoma_pomodoro_compact', this.isCompact ? '1' : '0');
                    } catch (e) {}
                    this.triggerHaptic('light');
                    this.$nextTick(() => {
                        this.clampFloatPosition();
                    });
                },

                saveFloatPosition() {
                    try {
                        localStorage.setItem('atoma_pomodoro_float_pos', JSON.stringify({
                            x: this.customX,
                            y: this.customY,
                            dockSide: this.dockSide
                        }));
                    } catch (e) {}
                },

                loadFloatPosition() {
                    try {
                        const savedCompact = localStorage.getItem('atoma_pomodoro_compact');
                        if (savedCompact !== null) {
                            this.isCompact = savedCompact === '1';
                        }
                        const savedPos = localStorage.getItem('atoma_pomodoro_float_pos');
                        if (savedPos) {
                            const pos = JSON.parse(savedPos);
                            if (typeof pos.x === 'number' && typeof pos.y === 'number') {
                                this.customX = pos.x;
                                this.customY = pos.y;
                                this.dockSide = pos.dockSide || 'right';
                                this.clampFloatPosition();
                            }
                        }
                    } catch (e) {}
                },

                clampFloatPosition() {
                    if (this.customX !== null && this.customY !== null) {
                        const floatWidth = this.isCompact ? 48 : 220;
                        const floatHeight = this.isCompact ? 48 : 50;
                        if (this.dockSide === 'left') {
                            this.customX = 16;
                        } else {
                            this.customX = Math.max(16, window.innerWidth - floatWidth - 16);
                        }
                        this.customY = Math.max(60, Math.min(window.innerHeight - floatHeight - 75, this.customY));
                    }
                },

                togglePlayPauseFromFloat() {
                    this.triggerHaptic('light');
                    this.toggleTimer();
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                toggleAudioMute() {
                    this.triggerHaptic('light');
                    this.isAudioMuted = !this.isAudioMuted;
                    if (this.isYtPlaying) {
                        if (this.isAudioMuted) {
                            this.sendYtCommand('mute');
                        } else {
                            this.sendYtCommand('unMute');
                            this.sendYtCommand('setVolume', [this.ytVolume || 80]);
                        }
                    }
                    if (this.ambientGainNode && this.ambientAudioCtx) {
                        if (this.isAudioMuted) {
                            this.ambientGainNode.gain.setValueAtTime(0, this.ambientAudioCtx.currentTime);
                        } else {
                            this.ambientGainNode.gain.setValueAtTime((this.ambientVolume / 100) * 0.4, this.ambientAudioCtx.currentTime);
                        }
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                quickTaskDone() {
                    this.triggerHaptic('success');
                    this.markSelectedTaskDone();
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                toggleSubTask(taskId, subTaskId) {
                    this.triggerHaptic('light');
                    if (window.Livewire) {
                        window.Livewire.dispatch('toggle-subtask-from-pomodoro', {
                            taskId: Number(taskId),
                            subTaskId: Number(subTaskId)
                        });
                    }
                    const task = this.activeTasks.find(t => String(t.id) === String(taskId));
                    if (task && task.subtasks) {
                        const sub = task.subtasks.find(s => String(s.id) === String(subTaskId));
                        if (sub) {
                            sub.is_completed = !sub.is_completed;
                        }
                    }
                    if (window.pipWindow && !window.pipWindow.closed) {
                        this.updatePipContent();
                    }
                },

                // Interactive Document Picture-in-Picture Float Hero + Canvas Fallback
                async togglePip() {
                    if (window.pipWindow && !window.pipWindow.closed) {
                        window.pipWindow.close();
                        window.pipWindow = null;
                        return;
                    }

                    if (this.canvasPipActive) {
                        if (document.pictureInPictureElement) {
                            await document.exitPictureInPicture().catch(() => {});
                        }
                        this.canvasPipActive = false;
                        if (this.canvasPipInterval) {
                            clearInterval(this.canvasPipInterval);
                            this.canvasPipInterval = null;
                        }
                        return;
                    }

                    if ('documentPictureInPicture' in window) {
                        await this.openDocumentPip();
                    } else if (document.pictureInPictureEnabled) {
                        await this.openCanvasPip();
                    } else {
                        alert('Browser kamu belum mendukung Picture-in-Picture API.');
                    }
                },

                async openDocumentPip() {
                    try {
                        const currentTask = this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId));
                        const hasSubtasks = Boolean(currentTask && currentTask.subtasks && currentTask.subtasks.length > 0);
                        const hasAudio = Boolean(this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId);
                        const initialHeight = (hasSubtasks ? 330 : 210) + (hasAudio ? 45 : 0);

                        const pipWin = await window.documentPictureInPicture.requestWindow({
                            width: 340,
                            height: initialHeight
                        });
                        window.pipWindow = pipWin;

                        const isDark = document.documentElement.classList.contains('dark');
                        const bgColor = isDark ? '#0f172a' : '#f8fafc';
                        const textColor = isDark ? '#f8fafc' : '#0f172a';
                        const subColor = isDark ? '#94a3b8' : '#64748b';
                        const secBtnBg = isDark ? '#1e293b' : '#ffffff';
                        const secBtnColor = isDark ? '#cbd5e1' : '#334155';
                        const secBtnBorder = isDark ? '#334155' : '#e2e8f0';

                        const style = pipWin.document.createElement('style');
                        style.textContent = `
                            body {
                                margin: 0; padding: 12px; background: ${bgColor}; color: ${textColor};
                                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                                display: flex; flex-direction: column; gap: 8px; min-height: 100vh; height: auto;
                                box-sizing: border-box; user-select: none; overflow-y: auto; justify-content: flex-start;
                            }
                            body::-webkit-scrollbar { width: 4px; }
                            body::-webkit-scrollbar-thumb { background: rgba(150, 150, 150, 0.3); border-radius: 4px; }
                            .btn { border: none; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 11px; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
                            .btn-primary { background: #f43f5e; color: white; box-shadow: 0 4px 12px rgba(244,63,94,0.3); }
                            .btn-secondary { background: ${secBtnBg}; color: ${secBtnColor}; border: 1px solid ${secBtnBorder}; }
                            .card { background: ${secBtnBg}; border: 1px solid ${secBtnBorder}; border-radius: 12px; padding: 8px 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
                            .subtask-item { display: flex; align-items: center; gap: 8px; padding: 4px 6px; border-radius: 6px; cursor: pointer; transition: background 0.15s; }
                            .subtask-item:hover { background: rgba(150, 150, 150, 0.1); }
                        `;
                        pipWin.document.head.appendChild(style);

                        const modeColor = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');

                        pipWin.document.body.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span id="pipMode" style="font-weight: 900; font-size: 12px; color: ${modeColor};">🍅 ${this.modeName}</span>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="font-size: 9px; font-weight: 700; color: #f97316;">🔥 ${this.userStreak}d</span>
                                    <span style="font-size: 9px; background: rgba(245,158,11,0.15); color: #d97706; padding: 2px 6px; border-radius: 6px; font-weight: bold;">📌 OS FLOAT</span>
                                </div>
                            </div>

                            <div style="text-align: center; margin: 2px 0;">
                                <div id="pipTime" style="font-size: 40px; font-weight: 900; font-family: monospace; color: ${textColor}; line-height: 1.1;">${this.formattedTime}</div>
                                <div id="pipTask" style="font-size: 11px; color: ${subColor}; font-weight: 600; margin-top: 2px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${this.selectedTaskTitle || 'Tetap fokus & tuntaskan!'}</div>
                            </div>

                            <div style="display: flex; justify-content: center; gap: 6px; align-items: center; flex-wrap: wrap;">
                                <button id="pipReset" class="btn btn-secondary" title="Reset Timer">🔄 Reset</button>
                                <button id="pipToggle" class="btn btn-primary" style="padding: 7px 16px; font-size: 12px;">${this.isRunning ? '⏸ Jeda' : '▶ Mulai'}</button>
                                <button id="pipFinishEarly" class="btn btn-secondary" style="color: #10b981; font-weight: bold;" title="Selesaikan Sesi (+XP)">✓ Selesai</button>
                                ${this.selectedTaskId ? '<button id="pipQuickDone" class="btn btn-secondary" style="color: #6366f1;" title="Tandai Tugas Selesai">📋 Tugas</button>' : ''}
                            </div>

                            <!-- Audio Section Card -->
                            <div id="pipAudioSection" class="card" style="display: ${this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId ? 'block' : 'none'};">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                                    <div style="display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1;">
                                        <span style="font-size: 12px;">🎵</span>
                                        <span id="pipAudioTitle" style="font-size: 10px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            ${this.isYtPlaying ? this.ytTitle : (this.isAmbientPlaying ? this.currentAmbientName : (this.ytTitle || 'Audio'))}
                                        </span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 3px; flex-shrink: 0;">
                                        <button id="pipAudioPrev" class="btn btn-secondary" style="padding: 3px 6px; font-size: 10px;" title="Lagu Sebelumnya">⏮</button>
                                        <button id="pipAudioNext" class="btn btn-secondary" style="padding: 3px 6px; font-size: 10px;" title="Lagu Berikutnya">⏭</button>
                                        <button id="pipAudioMute" class="btn btn-secondary" style="padding: 3px 8px; font-size: 10px;">
                                            ${this.isAudioMuted ? '🔇 Bisu' : '🔊 Suara'}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtasks Section Card -->
                            <div id="pipSubtasksSection" class="card" style="display: none; max-height: 120px; overflow-y: auto;">
                                <div id="pipSubtasksHeader" style="font-size: 10px; font-weight: 700; color: ${subColor}; margin-bottom: 4px;">📝 Subtasks</div>
                                <div id="pipSubtasksList" style="display: flex; flex-direction: column; gap: 2px;"></div>
                            </div>
                        `;

                        pipWin.document.getElementById('pipToggle').onclick = () => {
                            this.toggleTimer();
                            this.updatePipContent();
                        };
                        pipWin.document.getElementById('pipReset').onclick = () => {
                            this.resetTimer();
                            this.updatePipContent();
                        };
                        const fe = pipWin.document.getElementById('pipFinishEarly');
                        if (fe) {
                            fe.onclick = () => {
                                this.finishSessionEarly();
                                this.updatePipContent();
                            };
                        }
                        const qd = pipWin.document.getElementById('pipQuickDone');
                        if (qd) {
                            qd.onclick = () => {
                                this.quickTaskDone();
                                this.updatePipContent();
                            };
                        }
                        const prevBtn = pipWin.document.getElementById('pipAudioPrev');
                        if (prevBtn) {
                            prevBtn.onclick = () => {
                                this.playPrevTrack();
                                this.updatePipContent();
                            };
                        }
                        const nextBtn = pipWin.document.getElementById('pipAudioNext');
                        if (nextBtn) {
                            nextBtn.onclick = () => {
                                this.playNextTrack();
                                this.updatePipContent();
                            };
                        }
                        const muteBtn = pipWin.document.getElementById('pipAudioMute');
                        if (muteBtn) {
                            muteBtn.onclick = () => {
                                this.toggleAudioMute();
                                this.updatePipContent();
                            };
                        }

                        this.updatePipContent();

                        const updatePipInterval = setInterval(() => {
                            if (pipWin.closed) {
                                clearInterval(updatePipInterval);
                                window.pipWindow = null;
                                return;
                            }
                            this.updatePipContent();
                        }, 500);

                    } catch (err) {
                        console.log('PiP Error:', err);
                    }
                },

                updatePipContent() {
                    const pipWin = window.pipWindow;
                    if (!pipWin || pipWin.closed) return;

                    const doc = pipWin.document;
                    const timeEl = doc.getElementById('pipTime');
                    const modeEl = doc.getElementById('pipMode');
                    const toggleBtn = doc.getElementById('pipToggle');
                    const taskEl = doc.getElementById('pipTask');
                    const audioSection = doc.getElementById('pipAudioSection');
                    const audioTitle = doc.getElementById('pipAudioTitle');
                    const audioMuteBtn = doc.getElementById('pipAudioMute');
                    const subtasksContainer = doc.getElementById('pipSubtasksList');
                    const subtaskHeader = doc.getElementById('pipSubtasksHeader');
                    const subtasksSection = doc.getElementById('pipSubtasksSection');

                    if (timeEl) timeEl.textContent = this.formattedTime;
                    if (taskEl) taskEl.textContent = this.selectedTaskTitle || 'Tetap fokus & tuntaskan!';
                    if (modeEl) {
                        const modeIcon = this.mode === 'work' ? '🍅' : (this.mode === 'flowtime' ? '🌊' : '☕');
                        modeEl.textContent = `${modeIcon} ${this.modeName}`;
                        modeEl.style.color = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');
                    }
                    if (toggleBtn) {
                        toggleBtn.textContent = this.isRunning ? '⏸ Jeda' : '▶ Mulai';
                        toggleBtn.style.background = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');
                    }
                    const finishBtn = doc.getElementById('pipFinishEarly');
                    if (finishBtn) {
                        finishBtn.title = this.mode === 'work' ? 'Selesaikan Sesi Fokus (+XP)' : (this.mode === 'flowtime' ? 'Selesaikan Flowtime' : 'Selesaikan Istirahat');
                    }

                    if (audioSection) {
                        if (this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId) {
                            audioSection.style.display = 'block';
                            if (audioTitle) {
                                audioTitle.textContent = this.isYtPlaying ? this.ytTitle : (this.isAmbientPlaying ? this.currentAmbientName : (this.ytTitle || 'Audio'));
                            }
                            if (audioMuteBtn) {
                                audioMuteBtn.textContent = this.isAudioMuted ? '🔇 Bisu' : '🔊 Suara';
                                audioMuteBtn.style.color = this.isAudioMuted ? '#f43f5e' : 'inherit';
                            }
                        } else {
                            audioSection.style.display = 'none';
                        }
                    }

                    const currentTask = this.activeTasks.find(t => String(t.id) === String(this.selectedTaskId));
                    const hasActiveSubtasks = Boolean(currentTask && currentTask.subtasks && currentTask.subtasks.length > 0);

                    // Dynamic PiP window height auto-fit
                    if (pipWin.resizeTo) {
                        const hasAudio = Boolean(this.isYtPlaying || this.isAmbientPlaying || this.ytVideoId);
                        let targetHeight = 210;
                        if (hasAudio) targetHeight += 45;
                        if (hasActiveSubtasks) targetHeight += 120;
                        try {
                            if (Math.abs(pipWin.outerHeight - targetHeight) > 20) {
                                pipWin.resizeTo(340, targetHeight);
                            }
                        } catch (e) {}
                    }

                    if (subtasksContainer && subtaskHeader && subtasksSection) {
                        if (hasActiveSubtasks) {
                            subtasksSection.style.display = 'block';
                            const completedCount = currentTask.subtasks.filter(s => s.is_completed).length;
                            subtaskHeader.textContent = `📝 Subtasks (${completedCount}/${currentTask.subtasks.length})`;
                            
                            const currentSubCount = subtasksContainer.children.length;
                            if (currentSubCount !== currentTask.subtasks.length) {
                                subtasksContainer.innerHTML = '';
                                currentTask.subtasks.forEach(sub => {
                                    const item = doc.createElement('label');
                                    item.className = 'subtask-item';
                                    item.id = 'pip-sub-label-' + sub.id;
                                    item.innerHTML = `
                                        <input type="checkbox" id="pip-sub-cb-${sub.id}" ${sub.is_completed ? 'checked' : ''} style="cursor: pointer; accent-color: #f43f5e;" />
                                        <span id="pip-sub-text-${sub.id}" style="flex: 1; min-width: 0; font-size: 11px; font-weight: 500; ${sub.is_completed ? 'text-decoration: line-through; opacity: 0.6;' : ''}">${sub.title}</span>
                                    `;
                                    const cb = item.querySelector('input');
                                    cb.onchange = (e) => {
                                        e.preventDefault();
                                        this.toggleSubTask(currentTask.id, sub.id);
                                    };
                                    subtasksContainer.appendChild(item);
                                });
                            } else {
                                currentTask.subtasks.forEach(sub => {
                                    const cb = doc.getElementById('pip-sub-cb-' + sub.id);
                                    const txt = doc.getElementById('pip-sub-text-' + sub.id);
                                    if (cb && cb.checked !== Boolean(sub.is_completed)) {
                                        cb.checked = Boolean(sub.is_completed);
                                    }
                                    if (txt) {
                                        txt.style.textDecoration = sub.is_completed ? 'line-through' : 'none';
                                        txt.style.opacity = sub.is_completed ? '0.6' : '1';
                                    }
                                });
                            }
                        } else {
                            subtasksSection.style.display = 'none';
                        }
                    }
                },

                async openCanvasPip() {
                    let canvas = document.getElementById('pomodoro-pip-canvas');
                    if (!canvas) {
                        canvas = document.createElement('canvas');
                        canvas.id = 'pomodoro-pip-canvas';
                        canvas.width = 360;
                        canvas.height = 200;
                        canvas.style.display = 'none';
                        document.body.appendChild(canvas);
                    }
                    const ctx = canvas.getContext('2d');

                    let video = document.getElementById('pomodoro-pip-video');
                    if (!video) {
                        video = document.createElement('video');
                        video.id = 'pomodoro-pip-video';
                        video.muted = true;
                        video.autoplay = true;
                        video.playsInline = true;
                        video.style.display = 'none';
                        document.body.appendChild(video);
                    }

                    const drawFrame = () => {
                        const isDark = document.documentElement.classList.contains('dark');
                        ctx.fillStyle = isDark ? '#0f172a' : '#ffffff';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);

                        const modeColor = this.mode === 'work' ? '#f43f5e' : (this.mode === 'flowtime' ? '#06b6d4' : '#10b981');
                        ctx.fillStyle = modeColor;
                        ctx.font = 'bold 15px system-ui, sans-serif';
                        ctx.fillText(`${this.mode === 'work' ? '🍅' : (this.mode === 'flowtime' ? '🌊' : '☕')} ${this.modeName}`, 20, 35);

                        ctx.fillStyle = isDark ? '#94a3b8' : '#64748b';
                        ctx.font = 'bold 12px system-ui, sans-serif';
                        ctx.fillText(`Sesi: ${this.todaySessionsCount}/${this.settings.interval}`, canvas.width - 95, 35);

                        ctx.fillStyle = isDark ? '#f8fafc' : '#0f172a';
                        ctx.font = 'bold 52px monospace';
                        ctx.textAlign = 'center';
                        ctx.fillText(this.formattedTime, canvas.width / 2, 110);

                        ctx.fillStyle = isDark ? '#1e293b' : '#e2e8f0';
                        if (ctx.roundRect) {
                            ctx.beginPath();
                            ctx.roundRect(20, 135, canvas.width - 40, 8, 4);
                            ctx.fill();
                        } else {
                            ctx.fillRect(20, 135, canvas.width - 40, 8);
                        }

                        ctx.fillStyle = modeColor;
                        const fillW = Math.max(0, Math.min(canvas.width - 40, (canvas.width - 40) * (this.progressPercentage / 100)));
                        if (ctx.roundRect) {
                            ctx.beginPath();
                            ctx.roundRect(20, 135, fillW, 8, 4);
                            ctx.fill();
                        } else {
                            ctx.fillRect(20, 135, fillW, 8);
                        }

                        ctx.fillStyle = isDark ? '#cbd5e1' : '#475569';
                        ctx.font = 'bold 12px system-ui, sans-serif';
                        const taskText = this.selectedTaskTitle ? `🎯 ${this.selectedTaskTitle}` : (this.isRunning ? 'Sedang Berjalan...' : 'Dijeda');
                        ctx.fillText(taskText.length > 35 ? taskText.slice(0, 35) + '...' : taskText, canvas.width / 2, 172);

                        ctx.textAlign = 'start';
                    };

                    drawFrame();
                    try {
                        const stream = canvas.captureStream(10);
                        video.srcObject = stream;
                        await video.play();
                        await video.requestPictureInPicture();
                        this.canvasPipActive = true;
                        this.canvasPipInterval = setInterval(drawFrame, 500);

                        video.addEventListener('leavepictureinpicture', () => {
                            this.canvasPipActive = false;
                            if (this.canvasPipInterval) {
                                clearInterval(this.canvasPipInterval);
                                this.canvasPipInterval = null;
                            }
                        }, { once: true });
                    } catch (err) {
                        console.error('Canvas PiP error:', err);
                    }
                }

    };
}
