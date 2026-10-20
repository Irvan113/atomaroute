<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\Journal;
use App\Models\FinanceTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public $activeTab = 'journal'; // 'journal', 'graph', or 'finance'
    
    // Sidebar / Filtering State
    public $selectedTagFilter = 'all'; // 'all' or specific tag name
    public $searchQuery = ''; // Search query for notes/tags
    public $noteSortBy = 'latest_updated'; // 'latest_updated', 'created_newest', 'created_oldest', 'title_asc'

    // Form fields
    public $title = '';
    public $content = '';
    public $reference_url = '';
    public $mood = null;
    public $tags = [];
    public $newTag = '';
    public $photos = [];
    public $selectedLinkedNoteIds = []; // Manual note-to-note links selected by user

    public $showCreateModal = false;
    public $editingJournalId = null;

    // Finance State
    public $financeViewMode = 'monthly';
    public $financeMonth;
    public $financeYear;
    public $showFinanceModal = false;
    public $editingFinanceId = null;
    public $financeSearch = '';
    public $financeCategoryFilter = '';
    public $showBudgetModal = false;
    public $monthly_budget_input = '';

    // Finance form fields
    public $f_amount = '';
    public $f_type = 'expense';
    public $f_category = 'Food & Drinks';
    public $f_description = '';
    public $f_date = '';

    public $categories = [
        'income' => ['Salary', 'Freelance', 'Investment', 'Gift', 'Other Income'],
        'expense' => ['Food & Drinks', 'Transport', 'Shopping', 'Bills', 'Entertainment', 'Health', 'Education', 'Other Expense'],
        'savings' => ['Tabungan Darurat', 'Investasi / Reksadana', 'Tabungan Impian', 'Tabungan Masa Depan', 'Lainnya']
    ];

    public function mount()
    {
        $this->financeMonth = (int) Carbon::now()->format('m');
        $this->financeYear = (int) Carbon::now()->format('Y');
        $this->f_date = Carbon::now()->format('Y-m-d');
        if (auth()->check()) {
            $this->monthly_budget_input = auth()->user()->monthly_budget ? (string) (int) auth()->user()->monthly_budget : '';
        }
        $this->dispatch('journal-tab-changed', tab: $this->activeTab);
    }

    public function updatedActiveTab($value)
    {
        $this->dispatch('journal-tab-changed', tab: $value);
    }

    public function updatedFType($value)
    {
        $this->f_category = $this->categories[$value][0] ?? 'Food & Drinks';
    }

    public function resetFinanceFilters(): void
    {
        $this->financeSearch = '';
        $this->financeCategoryFilter = '';
    }

    // 1. User Tags List with Counts (Folders/Tags for Sidebar)
    #[Computed]
    public function allTagsWithCounts()
    {
        $journals = auth()->user()->journals()->get();
        $tagCounts = [];

        foreach ($journals as $j) {
            $rawTags = $j->tags;
            if (is_string($rawTags)) $rawTags = json_decode($rawTags, true) ?? [];
            if (!is_array($rawTags)) $rawTags = [];

            foreach ($rawTags as $t) {
                if (!is_string($t)) continue;
                $clean = ltrim(trim($t), '#');
                if ($clean === '') continue;
                $tagCounts[$clean] = ($tagCounts[$clean] ?? 0) + 1;
            }
        }

        ksort($tagCounts);
        return $tagCounts;
    }

    // 2. Filtered Journals Grouped by Date
    #[Computed]
    public function groupedJournals()
    {
        $query = auth()->user()->journals()
            ->withCount(['outgoingLinks', 'incomingLinks']);

        switch ($this->noteSortBy) {
            case 'created_newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'created_oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'latest_updated':
            default:
                $query->orderBy('updated_at', 'desc');
                break;
        }

        if ($this->selectedTagFilter !== 'all') {
            $tagToMatch = strtolower(ltrim($this->selectedTagFilter, '#'));
            $query->where(function($q) use ($tagToMatch) {
                $q->where('tags', 'LIKE', "%\"{$tagToMatch}\"%")
                  ->orWhere('tags', 'LIKE', "%{$tagToMatch}%");
            });
        }

        if (trim($this->searchQuery) !== '') {
            $search = trim($this->searchQuery);
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        $journals = $query->get()->each(function($j) {
            if (is_string($j->tags)) $j->tags = json_decode($j->tags, true) ?? [];
            if (!is_array($j->tags)) $j->tags = [];
            $j->tags = array_values(array_filter($j->tags, fn($t) => is_string($t)));
            $j->outgoing_links_count = (int)($j->outgoing_links_count ?? 0);
            $j->incoming_links_count = (int)($j->incoming_links_count ?? 0);
        });
            
        return $journals->groupBy(function($journal) {
            if ($this->noteSortBy === 'latest_updated') {
                return Carbon::parse($journal->updated_at)->format('Y-m-d');
            }
            return Carbon::parse($journal->created_at)->format('Y-m-d');
        });
    }

    // 3. Manual Connection Map & Spatial Tag Network
    #[Computed]
    public function connectionMapData()
    {
        $journals = auth()->user()->journals()->orderBy('created_at', 'desc')->get();
        $journalIds = $journals->pluck('id')->toArray();

        // Manual Links from DB
        $dbLinks = [];
        if (!empty($journalIds)) {
            $dbLinks = DB::table('journal_links')
                ->whereIn('source_journal_id', $journalIds)
                ->get();
        }

        $tagClusters = [];
        $noteList = [];

        foreach ($journals as $j) {
            $rawTags = $j->tags;
            if (is_string($rawTags)) $rawTags = json_decode($rawTags, true) ?? [];
            if (!is_array($rawTags)) $rawTags = [];

            $cleanTags = [];
            foreach ($rawTags as $t) {
                if (!is_string($t)) continue;
                $clean = ltrim(trim($t), '#');
                if ($clean !== '') $cleanTags[] = $clean;
            }

            $noteList[$j->id] = [
                'id' => $j->id,
                'title' => $j->title ?: ('Catatan #' . $j->id),
                'snippet' => Str::limit(strip_tags((string)$j->content), 70),
                'mood' => $j->mood ?? '📓',
                'tags' => $cleanTags,
                'date' => Carbon::parse($j->created_at)->format('d M Y'),
            ];

            foreach ($cleanTags as $tag) {
                $tagClusters[$tag][] = $j->id;
            }
        }

        // Direct Note-to-Note manual connections
        $manualConnections = [];
        foreach ($dbLinks as $link) {
            if (isset($noteList[$link->source_journal_id]) && isset($noteList[$link->target_journal_id])) {
                $manualConnections[] = [
                    'source' => $noteList[$link->source_journal_id],
                    'target' => $noteList[$link->target_journal_id],
                    'link_text' => $link->link_text ?? 'Koneksi Manual',
                ];
            }
        }

        return [
            'tagClusters' => $tagClusters,
            'noteList' => $noteList,
            'manualConnections' => $manualConnections,
        ];
    }

    // 4. Other Available Notes for Manual Link Checkbox Selector in Create Modal
    #[Computed]
    public function availableNotesForLinking()
    {
        return auth()->user()->journals()
            ->when($this->editingJournalId, fn($q) => $q->where('id', '!=', $this->editingJournalId))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function addTag()
    {
        $tag = ltrim(trim($this->newTag), '#');
        if ($tag !== '' && !in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->autoSaveJournal();
        }
        $this->newTag = '';
    }

    public function removeTag($index)
    {
        unset($this->tags[$index]);
        $this->tags = array_values($this->tags);
        $this->autoSaveJournal();
    }

    #[On('open-add-journal-modal')]
    public function openCreateModal()
    {
        if ($this->activeTab === 'finance') {
            $this->openCreateFinance('expense');
            return;
        }
        $this->reset(['editingJournalId', 'title', 'content', 'reference_url', 'mood', 'tags', 'newTag', 'photos', 'selectedLinkedNoteIds']);
        $this->showCreateModal = true;
    }

    #[On('open-add-finance-modal')]
    public function openCreateFinanceFromEvent()
    {
        $this->openCreateFinance('expense');
    }

    #[Computed]
    public function allNotesList()
    {
        return auth()->user()->journals()
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->select('id', 'title', 'content', 'tags', 'created_at', 'updated_at')
            ->get()
            ->map(function ($j) {
                $rawTags = $j->tags;
                if (is_string($rawTags)) $rawTags = json_decode($rawTags, true) ?? [];
                if (!is_array($rawTags)) $rawTags = [];
                $cleanText = trim(strip_tags($j->content ?: ''));
                return [
                    'id' => $j->id,
                    'title' => $j->title ?: 'Catatan Tanpa Judul',
                    'snippet' => Str::limit($cleanText, 90),
                    'tags' => array_values(array_filter($rawTags, fn($t) => is_string($t))),
                    'date' => $j->updated_at ? $j->updated_at->format('d M Y') : $j->created_at->format('d M Y'),
                ];
            });
    }

    public function getNotesListJson()
    {
        return $this->allNotesList->values()->toArray();
    }

    public function getNotePreview($id)
    {
        $journal = auth()->user()->journals()
            ->select('id', 'title', 'content', 'tags', 'created_at', 'updated_at')
            ->find($id);

        if (!$journal) {
            return [
                'id' => $id,
                'title' => 'Catatan Tidak Ditemukan',
                'snippet' => 'Catatan ini tidak ditemukan atau telah dihapus.',
                'content_html' => '<p class="text-gray-400 italic">Catatan ini tidak ditemukan atau telah dihapus.</p>',
                'tags' => [],
                'word_count' => 0,
                'date' => '',
            ];
        }

        $rawTags = $journal->tags;
        if (is_string($rawTags)) $rawTags = json_decode($rawTags, true) ?? [];
        if (!is_array($rawTags)) $rawTags = [];
        $rawContent = $journal->content ?: '';
        $cleanText = trim(strip_tags($rawContent));
        $wordCount = $cleanText ? count(preg_split('/\s+/', $cleanText)) : 0;

        $contentHtml = $this->sanitizeRichContent($rawContent ?: '<p class="text-gray-400 italic">Catatan ini belum memiliki isi konten.</p>');

        return [
            'id' => $journal->id,
            'title' => $journal->title ?: 'Catatan Tanpa Judul',
            'snippet' => Str::limit($cleanText, 120),
            'content_html' => $contentHtml,
            'tags' => array_values(array_filter($rawTags, fn($t) => is_string($t))),
            'word_count' => $wordCount,
            'date' => $journal->updated_at ? $journal->updated_at->format('d M Y, H:i') : $journal->created_at->format('d M Y, H:i'),
        ];
    }

    public function switchNote($targetId)
    {
        if ($this->editingJournalId) {
            $this->autoSaveJournal();
        }
        $this->editJournal($targetId);
    }

    public function createNewNoteFromSidebar()
    {
        if ($this->editingJournalId) {
            $this->autoSaveJournal();
        }
        $this->openCreateModal();
    }

    public function sanitizeRichContent($content)
    {
        if (empty($content)) return '';

        // 1. Remove obsolete font tags completely (preserve inner text)
        $content = preg_replace('/<\/?font\b[^>]*>/i', '', $content);

        // 2. Remove broken font tag fragments from pastes
        $content = preg_replace('/#?[0-9a-fA-F]{3,8}"\s+face="[^"]*">/i', '', $content);
        $content = preg_replace('/(?:color=)?"#[0-9a-fA-F]{3,8}"\s+face="[^"]*">/i', '', $content);

        // 3. Remove script and style tags
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);

        // 4. Remove dark-mode breaking inline styles (color: #..., font-family: ...)
        $content = preg_replace_callback('/style="([^"]*)"/i', function($match) {
            $style = $match[1];
            $cleanStyle = preg_replace('/(?:font-family|color|background-color)\s*:[^;]+;?/i', '', $style);
            $cleanStyle = trim($cleanStyle);
            return $cleanStyle ? 'style="' . $cleanStyle . '"' : '';
        }, $content);

        // 5. Clean emojis & chunky wrappers from journal-note-link to keep clean blue text
        $content = preg_replace_callback('/<a\b([^>]*class="[^"]*journal-note-link[^"]*"[^>]*)>(.*?)<\/a>/is', function($m) {
            $attrs = $m[1];
            $inner = $m[2];
            $cleanText = preg_replace('/<span[^>]*>[🔗\s]*<\/span>/u', '', $inner);
            $cleanText = preg_replace('/^[🔗\s]+/u', '', $cleanText);
            $cleanText = trim(strip_tags($cleanText));

            preg_match('/data-note-id="([^"]*)"/i', $attrs, $idMatch);
            preg_match('/data-note-title="([^"]*)"/i', $attrs, $titleMatch);
            $id = $idMatch[1] ?? '';
            $title = $titleMatch[1] ?? $cleanText;

            return '<a href="javascript:void(0)" data-note-id="' . htmlspecialchars($id, ENT_QUOTES) . '" data-note-title="' . htmlspecialchars($title, ENT_QUOTES) . '" tabindex="-1" class="journal-note-link text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-semibold underline decoration-blue-400/60 underline-offset-2 transition-colors cursor-pointer" contenteditable="false">' . htmlspecialchars($cleanText, ENT_QUOTES) . '</a>';
        }, $content);

        // 6. Security (Anti-XSS): Strip unsafe event handlers, iframe, object, embed, and javascript: links
        $content = preg_replace('/<\/?(?:iframe|object|embed|applet|meta|link)\b[^>]*>/i', '', $content);
        $content = preg_replace('/\s+on[a-z]+\s*=\s*(?:["\'][^"\']*["\']|[^\s>]+)/i', '', $content);
        $content = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="javascript:void(0)"', $content);

        return trim($content);
    }

    public function exportNotes()
    {
        $journals = auth()->user()->journals()->orderBy('created_at', 'desc')->get();
        $exportData = $journals->map(function($j) {
            return [
                'id' => $j->id,
                'title' => $j->title,
                'content_raw' => strip_tags(str_replace(['<br>', '</div>', '</p>', '</li>'], ["\n", "\n", "\n\n", "\n"], $j->content)),
                'content_html' => $j->content,
                'tags' => $j->tags,
                'created_at' => (string) $j->created_at,
                'updated_at' => (string) $j->updated_at,
            ];
        });
        
        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'atoma-notes-backup-' . date('Y-m-d') . '.json';
        return response()->streamDownload(function() use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function autoSaveJournal($customTitle = null, $customContent = null)
    {
        if ($customTitle !== null) {
            $this->title = $customTitle;
        }
        if ($customContent !== null) {
            $this->content = $customContent;
        }

        $this->content = $this->sanitizeRichContent($this->content);

        $cleanTitle = trim($this->title ?? '');
        $cleanContent = trim(strip_tags($this->content ?? ''));
        if (mb_strlen($cleanTitle) < 2 && mb_strlen($cleanContent) < 3) {
            return;
        }

        preg_match_all('/(?<!["\'=&#\w])#([a-zA-Z_][\w\-]*)\b/u', $this->content, $matches);
        $autoTags = $matches[1] ?? [];
        $combinedTags = array_values(array_unique(array_merge($this->tags, $autoTags)));

        if ($this->editingJournalId) {
            $journal = auth()->user()->journals()->find($this->editingJournalId);
            if ($journal) {
                $journal->update([
                    'title' => $cleanTitle ?: 'Catatan Tanpa Judul',
                    'content' => $this->content,
                    'reference_url' => $this->reference_url,
                    'mood' => $this->mood,
                    'tags' => $combinedTags,
                ]);
            }
        } else {
            $journal = auth()->user()->journals()->create([
                'title' => $cleanTitle ?: 'Catatan Baru',
                'content' => $this->content,
                'reference_url' => $this->reference_url,
                'mood' => $this->mood,
                'tags' => $combinedTags,
            ]);
            $this->editingJournalId = $journal->id;
        }

        if ($this->editingJournalId) {
            $this->syncNoteLinksFromContent($this->editingJournalId, $this->content);
        }

        $this->dispatch('journal-autosaved', time: now()->format('H:i'));
    }

    public function syncNoteLinksFromContent($journalId, $content)
    {
        if (!$journalId) return;

        $linkedTargets = [];

        // 1. Data-attribute note links from the rich editor (e.g. data-note-id="12")
        if (!empty($content)) {
            preg_match_all('/data-note-id="(\d+)"(?:[^>]*data-note-title="([^"]*)")?/i', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $targetId = (int)$m[1];
                if ($targetId > 0 && $targetId !== (int)$journalId) {
                    $title = !empty($m[2]) ? html_entity_decode($m[2], ENT_QUOTES, 'UTF-8') : 'Tautan Catatan';
                    $linkedTargets[$targetId] = $title;
                }
            }

            // 2. [[WikiLinks]] format support
            preg_match_all('/\[\[(.*?)\]\]/', $content, $wikiMatches);
            if (!empty($wikiMatches[1])) {
                foreach ($wikiMatches[1] as $targetTitle) {
                    $targetTitle = trim($targetTitle);
                    if ($targetTitle === '') continue;

                    $targetJournal = auth()->user()->journals()
                        ->where('id', '!=', $journalId)
                        ->where(function($q) use ($targetTitle) {
                            $q->where('title', 'LIKE', '%' . $targetTitle . '%')
                              ->orWhere('content', 'LIKE', '%' . $targetTitle . '%');
                        })
                        ->first();

                    if ($targetJournal) {
                        $linkedTargets[$targetJournal->id] = $targetTitle;
                    }
                }
            }
        }

        // 3. User manual checklist selections
        if (!empty($this->selectedLinkedNoteIds)) {
            foreach ($this->selectedLinkedNoteIds as $tId) {
                $tId = (int)$tId;
                if ($tId > 0 && $tId !== (int)$journalId && !isset($linkedTargets[$tId])) {
                    $linkedTargets[$tId] = 'Koneksi Manual';
                }
            }
        }

        // 4. Update journal_links table
        DB::table('journal_links')->where('source_journal_id', $journalId)->delete();
        foreach ($linkedTargets as $targetId => $linkText) {
            DB::table('journal_links')->insert([
                'source_journal_id' => $journalId,
                'target_journal_id' => $targetId,
                'link_text' => $linkText,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function editJournal($id)
    {
        $journal = auth()->user()->journals()->findOrFail($id);
        
        $this->editingJournalId = $journal->id;
        $this->title = $journal->title ?? '';
        $this->content = $journal->content ?? '';
        $this->reference_url = $journal->reference_url ?? '';
        $this->mood = $journal->mood ?? null;

        $rawTags = $journal->tags;
        if (is_string($rawTags)) $rawTags = json_decode($rawTags, true) ?? [];
        if (!is_array($rawTags)) $rawTags = [];
        $this->tags = array_values(array_filter($rawTags, fn($t) => is_string($t)));

        // Load existing manual note links
        $this->selectedLinkedNoteIds = DB::table('journal_links')
            ->where('source_journal_id', $journal->id)
            ->pluck('target_journal_id')
            ->map(fn($val) => (string)$val)
            ->toArray();

        $this->showCreateModal = true;
    }

    public function saveJournal()
    {
        $this->content = $this->sanitizeRichContent($this->content);

        $quality = \App\Services\ContentQualityService::validateText($this->content, 3, 10);
        if (!$quality['valid']) {
            $this->dispatch('toast', message: $quality['message'], type: 'error');
            return;
        }

        $imagePaths = [];
        if ($this->photos) {
            foreach ($this->photos as $photo) {
                $path = $photo->store('journals', 'public');
                $imagePaths[] = $path;
            }
        }

        preg_match_all('/(?<!["\'=&#\w])#([a-zA-Z_][\w\-]*)\b/u', $this->content, $matches);
        $autoTags = $matches[1] ?? [];
        $combinedTags = array_unique(array_merge($this->tags, $autoTags));

        if ($this->editingJournalId) {
            $journal = auth()->user()->journals()->findOrFail($this->editingJournalId);
            
            $existingImages = $journal->images ?? [];
            if (is_string($existingImages)) $existingImages = json_decode($existingImages, true) ?? [];
            $finalImages = array_merge($existingImages, $imagePaths);

            $journal->update([
                'title' => !empty($this->title) ? trim($this->title) : null,
                'content' => $this->content,
                'reference_url' => !empty($this->reference_url) ? trim($this->reference_url) : null,
                'mood' => $this->mood,
                'tags' => empty($combinedTags) ? null : array_values($combinedTags),
                'images' => empty($finalImages) ? null : array_values($finalImages),
            ]);

            // Sync links (data-attributes, wikilinks, manual checkboxes)
            $this->syncNoteLinksFromContent($journal->id, $this->content);

            $msg = 'Catatan berhasil diperbarui!';
        } else {
            $journal = auth()->user()->journals()->create([
                'title' => !empty($this->title) ? trim($this->title) : null,
                'content' => $this->content,
                'reference_url' => !empty($this->reference_url) ? trim($this->reference_url) : null,
                'mood' => $this->mood,
                'tags' => empty($combinedTags) ? null : array_values($combinedTags),
                'images' => empty($imagePaths) ? null : $imagePaths,
            ]);

            // Sync links (data-attributes, wikilinks, manual checkboxes)
            $this->syncNoteLinksFromContent($journal->id, $this->content);

            $awardedXp = \App\Services\GamificationService::awardJournalXp(auth()->user(), $journal);
            if ($awardedXp > 0) {
                $msg = "Catatan Jurnal tersimpan! +{$awardedXp} Pending XP (Settlement 00:00)";
                $this->dispatch('profile-updated');

                // Check if writing journal triggers today's dynamic hidden mission
                $hiddenReward = \App\Services\GamificationService::checkDailyHiddenMission(auth()->user());
                if ($hiddenReward) {
                    $this->dispatch('toast', message: $hiddenReward['message'], type: 'success');
                    $this->dispatch('play-sound', preset: 'fanfare');
                    $this->dispatch('trigger-confetti');
                }
            } else {
                $msg = 'Catatan Jurnal tersimpan!';
            }
        }

        $this->reset(['editingJournalId', 'title', 'content', 'reference_url', 'mood', 'tags', 'newTag', 'photos', 'selectedLinkedNoteIds', 'showCreateModal']);
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    #[On('delete-journal')]
    public function deleteJournal($id)
    {
        $journal = auth()->user()->journals()->findOrFail($id);
        $journal->delete();
        $this->dispatch('toast', message: 'Catatan berhasil dihapus', type: 'nudge');
    }

    // Finance Handlers
    public function openCreateFinance($type = 'expense')
    {
        $this->editingFinanceId = null;
        $this->f_type = in_array($type, ['income', 'expense', 'savings']) ? $type : 'expense';
        $this->f_category = $this->categories[$this->f_type][0] ?? 'Food & Drinks';
        $this->f_amount = '';
        $this->f_description = '';
        $this->f_date = Carbon::now()->format('Y-m-d');
        $this->showFinanceModal = true;
    }

    public function openEditFinance($id)
    {
        $t = auth()->user()->financeTransactions()->findOrFail($id);
        $this->editingFinanceId = $t->id;
        $this->f_type = $t->type;
        $this->f_category = $t->category;
        $this->f_amount = (string) (int) $t->amount;
        $this->f_description = $t->description ?? '';
        $this->f_date = Carbon::parse($t->date)->format('Y-m-d');
        $this->showFinanceModal = true;
    }

    public function openBudgetModal()
    {
        $this->monthly_budget_input = auth()->user()->monthly_budget ? (string) (int) auth()->user()->monthly_budget : '';
        $this->showBudgetModal = true;
    }

    public function saveBudget()
    {
        $this->validate([
            'monthly_budget_input' => 'nullable|numeric|min:0',
        ]);

        $val = ($this->monthly_budget_input === '' || $this->monthly_budget_input === null) ? null : (float) $this->monthly_budget_input;
        auth()->user()->update(['monthly_budget' => $val]);

        $this->showBudgetModal = false;
        $this->dispatch('toast', message: 'Target anggaran bulanan berhasil diperbarui!', type: 'success');
    }

    #[Computed]
    public function groupedFinances()
    {
        $query = auth()->user()->financeTransactions()
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->financeViewMode === 'monthly') {
            $startDate = Carbon::create($this->financeYear, $this->financeMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($this->financeViewMode === 'yearly') {
            $startDate = Carbon::create($this->financeYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if (!empty(trim($this->financeSearch))) {
            $search = trim($this->financeSearch);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if (!empty($this->financeCategoryFilter)) {
            $query->where('category', $this->financeCategoryFilter);
        }

        return $query->get()->groupBy(fn($t) => Carbon::parse($t->date)->format('Y-m-d'));
    }

    #[Computed]
    public function financeStats()
    {
        $query = auth()->user()->financeTransactions();

        if ($this->financeViewMode === 'monthly') {
            $startDate = Carbon::create($this->financeYear, $this->financeMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($this->financeViewMode === 'yearly') {
            $startDate = Carbon::create($this->financeYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        $transactions = $query->get();
        $income = (float) $transactions->where('type', 'income')->sum('amount');
        $expense = (float) $transactions->where('type', 'expense')->sum('amount');
        $savings = (float) $transactions->where('type', 'savings')->sum('amount');
        $balance = $income - $expense - $savings;

        // Category breakdown for expenses
        $expenseTransactions = $transactions->where('type', 'expense');
        $categoryBreakdown = [];
        if ($expense > 0) {
            $groupedExp = $expenseTransactions->groupBy('category');
            foreach ($groupedExp as $cat => $items) {
                $catSum = (float) $items->sum('amount');
                $pct = round(($catSum / $expense) * 100, 1);
                $categoryBreakdown[] = [
                    'category' => $cat,
                    'amount' => $catSum,
                    'percentage' => $pct,
                ];
            }
            usort($categoryBreakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);
        }

        // Budget calculations
        $userBudget = auth()->user()->monthly_budget ? (float) auth()->user()->monthly_budget : 0;
        $budgetPercentage = 0;
        $remainingBudget = 0;
        $isOverBudget = false;
        if ($userBudget > 0) {
            $budgetPercentage = round(($expense / $userBudget) * 100, 1);
            $remainingBudget = $userBudget - $expense;
            $isOverBudget = $expense > $userBudget;
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'savings' => $savings,
            'balance' => $balance,
            'category_breakdown' => $categoryBreakdown,
            'user_budget' => $userBudget,
            'budget_percentage' => $budgetPercentage,
            'remaining_budget' => $remainingBudget,
            'is_over_budget' => $isOverBudget,
        ];
    }

    public function saveFinance()
    {
        $this->validate([
            'f_amount' => 'required|numeric|min:1',
            'f_type' => 'required|in:income,expense,savings',
            'f_category' => 'required|string',
            'f_date' => 'required|date',
        ]);

        if ($this->editingFinanceId) {
            $t = auth()->user()->financeTransactions()->findOrFail($this->editingFinanceId);
            $t->update([
                'type' => $this->f_type,
                'amount' => $this->f_amount,
                'category' => $this->f_category,
                'description' => empty($this->f_description) ? null : $this->f_description,
                'date' => Carbon::parse($this->f_date),
            ]);
            $msg = 'Transaksi berhasil diperbarui!';
        } else {
            auth()->user()->financeTransactions()->create([
                'type' => $this->f_type,
                'amount' => $this->f_amount,
                'category' => $this->f_category,
                'description' => empty($this->f_description) ? null : $this->f_description,
                'date' => Carbon::parse($this->f_date),
            ]);
            $msg = 'Transaksi berhasil dicatat!';
        }

        $this->reset(['f_amount', 'f_description', 'showFinanceModal', 'editingFinanceId']);
        $this->f_date = Carbon::today()->format('Y-m-d');
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    #[On('delete-finance')]
    public function deleteFinance($id)
    {
        $t = auth()->user()->financeTransactions()->findOrFail($id);
        $t->delete();
        $this->dispatch('toast', message: 'Transaksi dihapus', type: 'nudge');
    }

    public function getCategoryMeta($category = '', $type = 'expense')
    {
        $c = strtolower(trim((string) $category));

        // Income categories
        if (str_contains($c, 'salary') || str_contains($c, 'gaji')) {
            return [
                'name' => $category,
                'bg' => 'bg-emerald-50 dark:bg-emerald-950/40',
                'color' => 'text-emerald-600 dark:text-emerald-400',
                'border' => 'border-emerald-200 dark:border-emerald-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'
            ];
        }
        if (str_contains($c, 'freelance') || str_contains($c, 'bisnis') || str_contains($c, 'business')) {
            return [
                'name' => $category,
                'bg' => 'bg-teal-50 dark:bg-teal-950/40',
                'color' => 'text-teal-600 dark:text-teal-400',
                'border' => 'border-teal-200 dark:border-teal-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'
            ];
        }
        if (str_contains($c, 'investment') || str_contains($c, 'investasi') || str_contains($c, 'reksadana') || str_contains($c, 'saham')) {
            return [
                'name' => $category,
                'bg' => 'bg-indigo-50 dark:bg-indigo-950/40',
                'color' => 'text-indigo-600 dark:text-indigo-400',
                'border' => 'border-indigo-200 dark:border-indigo-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>'
            ];
        }
        if (str_contains($c, 'gift') || str_contains($c, 'hadiah')) {
            return [
                'name' => $category,
                'bg' => 'bg-pink-50 dark:bg-pink-950/40',
                'color' => 'text-pink-600 dark:text-pink-400',
                'border' => 'border-pink-200 dark:border-pink-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V4.5a2.5 2.5 0 115 0V8h-5zm0 0V4.5a2.5 2.5 0 10-5 0V8h5zm-7 0h14a2 2 0 012 2v2H3v-2a2 2 0 012-2zm1 4v9h12v-9"/></svg>'
            ];
        }

        // Expense categories
        if (str_contains($c, 'food') || str_contains($c, 'drink') || str_contains($c, 'makan') || str_contains($c, 'minum')) {
            return [
                'name' => $category,
                'bg' => 'bg-amber-50 dark:bg-amber-950/40',
                'color' => 'text-amber-600 dark:text-amber-400',
                'border' => 'border-amber-200 dark:border-amber-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 14h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm14-4h2a2 2 0 012 2v0a2 2 0 01-2 2h-2M4 18h16"/></svg>'
            ];
        }
        if (str_contains($c, 'transport') || str_contains($c, 'kendaraan') || str_contains($c, 'bensin') || str_contains($c, 'ojol')) {
            return [
                'name' => $category,
                'bg' => 'bg-blue-50 dark:bg-blue-950/40',
                'color' => 'text-blue-600 dark:text-blue-400',
                'border' => 'border-blue-200 dark:border-blue-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zm0 0h1a2 2 0 002-2v-3a2 2 0 00-.586-1.414l-2.828-2.828A2 2 0 0017.172 7H6.828a2 2 0 00-1.414.586L2.586 10.586A2 2 0 002 12v3a2 2 0 002 2h1m10 0H9m-4-6l1.5-3h11l1.5 3"/></svg>'
            ];
        }
        if (str_contains($c, 'shopping') || str_contains($c, 'belanja')) {
            return [
                'name' => $category,
                'bg' => 'bg-rose-50 dark:bg-rose-950/40',
                'color' => 'text-rose-600 dark:text-rose-400',
                'border' => 'border-rose-200 dark:border-rose-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'
            ];
        }
        if (str_contains($c, 'bills') || str_contains($c, 'tagihan') || str_contains($c, 'listrik') || str_contains($c, 'pulsa') || str_contains($c, 'internet')) {
            return [
                'name' => $category,
                'bg' => 'bg-cyan-50 dark:bg-cyan-950/40',
                'color' => 'text-cyan-600 dark:text-cyan-400',
                'border' => 'border-cyan-200 dark:border-cyan-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'
            ];
        }
        if (str_contains($c, 'entertainment') || str_contains($c, 'hiburan') || str_contains($c, 'game') || str_contains($c, 'hobi')) {
            return [
                'name' => $category,
                'bg' => 'bg-purple-50 dark:bg-purple-950/40',
                'color' => 'text-purple-600 dark:text-purple-400',
                'border' => 'border-purple-200 dark:border-purple-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            ];
        }
        if (str_contains($c, 'health') || str_contains($c, 'kesehatan') || str_contains($c, 'obat') || str_contains($c, 'medis')) {
            return [
                'name' => $category,
                'bg' => 'bg-red-50 dark:bg-red-950/40',
                'color' => 'text-red-600 dark:text-red-400',
                'border' => 'border-red-200 dark:border-red-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>'
            ];
        }
        if (str_contains($c, 'education') || str_contains($c, 'pendidikan') || str_contains($c, 'buku') || str_contains($c, 'kursus')) {
            return [
                'name' => $category,
                'bg' => 'bg-sky-50 dark:bg-sky-950/40',
                'color' => 'text-sky-600 dark:text-sky-400',
                'border' => 'border-sky-200 dark:border-sky-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5"/></svg>'
            ];
        }

        // Savings categories
        if (str_contains($c, 'darurat') || str_contains($c, 'emergency')) {
            return [
                'name' => $category,
                'bg' => 'bg-emerald-50 dark:bg-emerald-950/40',
                'color' => 'text-emerald-600 dark:text-emerald-400',
                'border' => 'border-emerald-200 dark:border-emerald-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>'
            ];
        }
        if (str_contains($c, 'impian') || str_contains($c, 'dream')) {
            return [
                'name' => $category,
                'bg' => 'bg-amber-50 dark:bg-amber-950/40',
                'color' => 'text-amber-600 dark:text-amber-400',
                'border' => 'border-amber-200 dark:border-amber-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>'
            ];
        }
        if (str_contains($c, 'tabungan') || str_contains($c, 'savings') || $type === 'savings') {
            return [
                'name' => $category,
                'bg' => 'bg-violet-50 dark:bg-violet-950/40',
                'color' => 'text-violet-600 dark:text-violet-400',
                'border' => 'border-violet-200 dark:border-violet-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>'
            ];
        }

        // Default fallbacks based on type
        if ($type === 'income') {
            return [
                'name' => $category,
                'bg' => 'bg-emerald-50 dark:bg-emerald-950/40',
                'color' => 'text-emerald-600 dark:text-emerald-400',
                'border' => 'border-emerald-200 dark:border-emerald-800',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-6-6h12"/></svg>'
            ];
        }

        return [
            'name' => $category,
            'bg' => 'bg-gray-100 dark:bg-gray-800',
            'color' => 'text-gray-600 dark:text-gray-400',
            'border' => 'border-gray-200 dark:border-gray-700',
            'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>'
        ];
    }

    public function extractFlashcardsFromNote($journalId)
    {
        $journal = auth()->user()->journals()->findOrFail($journalId);
        $content = strip_tags($journal->content);
        
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $extracted = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 6) continue;
            
            $q = null;
            $a = null;
            
            if (str_contains($line, '?')) {
                $parts = explode('?', $line, 2);
                $q = trim($parts[0]) . '?';
                $a = trim($parts[1] ?? 'Lihat catatan lengkap.');
            } elseif (str_contains($line, ':')) {
                $parts = explode(':', $line, 2);
                if (strlen($parts[0]) <= 50 && strlen($parts[1]) >= 5) {
                    $q = "Apa maksud / fungsi dari " . trim($parts[0]) . "?";
                    $a = trim($parts[1]);
                }
            } elseif (str_contains($line, ' adalah ') || str_contains($line, ' yaitu ')) {
                $sep = str_contains($line, ' adalah ') ? ' adalah ' : ' yaitu ';
                $parts = explode($sep, $line, 2);
                if (strlen($parts[0]) <= 50 && strlen($parts[1]) >= 5) {
                    $q = "Apa itu " . trim($parts[0]) . "?";
                    $a = trim($parts[0]) . $sep . trim($parts[1]);
                }
            }
            
            if ($q && $a) {
                auth()->user()->flashcards()->create([
                    'journal_id' => $journal->id,
                    'front' => $q,
                    'back' => $a,
                    'deck' => $journal->title ?: 'Notes',
                    'repetitions' => 0,
                    'interval_days' => 1,
                    'ease_factor' => 2.5,
                    'next_review_at' => Carbon::now(),
                ]);
                $extracted++;
            }
        }
        
        if ($extracted === 0) {
            auth()->user()->flashcards()->create([
                'journal_id' => $journal->id,
                'front' => "Apa poin inti dan ringkasan dari: {$journal->title}?",
                'back' => mb_substr($content, 0, 300) . (strlen($content) > 300 ? '...' : ''),
                'deck' => $journal->title ?: 'Notes',
                'repetitions' => 0,
                'interval_days' => 1,
                'ease_factor' => 2.5,
                'next_review_at' => Carbon::now(),
            ]);
            $extracted = 1;
        }
        
        $this->dispatch('toast', message: "✨ Berhasil membuat {$extracted} Flashcard dari catatan ini!", type: 'success');
        $this->dispatch('trigger-confetti');
    }

    public function convertChecklistToTasks($journalId = null)
    {
        $content = '';
        if ($journalId) {
            $journal = auth()->user()->journals()->find($journalId);
            if ($journal) $content = $journal->content;
        } else {
            $content = $this->content;
        }

        if (empty($content)) {
            $this->dispatch('toast', message: 'Catatan masih kosong!', type: 'warning');
            return;
        }

        $tasksCreated = 0;
        $userTz = auth()->check() ? (auth()->user()->timezone ?? config('app.timezone', 'Asia/Jakarta')) : config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::now($userTz)->toDateString();

        // 1. Match HTML checklist items where checkbox is not checked
        preg_match_all('/<div[^>]*journal-checklist-item[^>]*>.*?<input[^>]*>(.*?)<\/div>/is', $content, $items);

        $extractedTasks = [];
        if (!empty($items[0])) {
            foreach ($items[0] as $itemHtml) {
                // If checkbox is already checked, skip it
                if (preg_match('/<input[^>]*checked/i', $itemHtml)) {
                    continue;
                }
                $text = trim(strip_tags($itemHtml));
                if (!empty($text) && !in_array($text, ['Item tugas baru...', 'Tulis tugas baru...'])) {
                    $extractedTasks[] = $text;
                }
            }
        }

        // 2. Also match markdown checklists: - [ ] Task title
        preg_match_all('/(?:^|\n)\s*-\s*\[\s*\]\s*(.+)/', $content, $mdMatches);
        if (!empty($mdMatches[1])) {
            foreach ($mdMatches[1] as $taskTitle) {
                $clean = trim(strip_tags($taskTitle));
                if (!empty($clean)) {
                    $extractedTasks[] = $clean;
                }
            }
        }

        $extractedTasks = array_values(array_unique($extractedTasks));

        foreach ($extractedTasks as $title) {
            \App\Models\Task::create([
                'user_id' => auth()->id(),
                'title' => $title,
                'date' => $today,
                'priority' => 'medium',
                'category' => 'Note',
                'is_completed' => false,
            ]);
            $tasksCreated++;
        }

        if ($tasksCreated > 0) {
            $this->dispatch('toast', message: "🎯 Berhasil membuat {$tasksCreated} Tugas Harian dari checklist!", type: 'success');
            $this->dispatch('trigger-confetti');
        } else {
            $this->dispatch('toast', message: "Tidak ada checklist aktif (belum selesai) yang ditemukan.", type: 'warning');
        }
    }


    public function formatContent($content)
    {
        if (empty($content)) return '';

        // Clean broken font tag leftovers from past AI pastes (e.g. #101010" face="...")
        $content = preg_replace('/#?[0-9a-fA-F]{3,8}"\s+face="[^"]*">/i', '', $content);
        $content = preg_replace('/(?:color=)?"#[0-9a-fA-F]{3,8}"\s+face="[^"]*">/i', '', $content);
        $content = preg_replace('/<\/?font\b[^>]*>/i', '', $content);

        // If content contains rich HTML tags from Visual Editor (Image 2 style)
        if (preg_match('/<(h[1-6]|table|tr|td|th|ul|ol|li|input|strong|em|p|div|br|span|mark|blockquote|pre|code|hr)/i', $content)) {
            // Clean script tags
            $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
            $clean = preg_replace('/on[a-z]+\s*=\s*"[^"]*"/i', '', $clean);
            $clean = preg_replace('/on[a-z]+\s*=\s*\'[^\']*\'/i', '', $clean);

            // Format WikiLinks
            $clean = preg_replace_callback('/\[\[(.*?)\]\]/', function($m) {
                return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-black bg-atoma-ice text-atoma-blue dark:bg-atoma-blue/30 dark:text-atoma-cyan border border-atoma-aqua/60 dark:border-atoma-blue/40 shadow-2xs"><svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>' . e(trim($m[1])) . '</span>';
            }, $clean);

            // Format #Tags (Must be a true hashtag, not hex color like #101010 or inside an HTML attribute)
            $clean = preg_replace_callback('/(?<!["\'=&#\w])#([a-zA-Z_][\w\-]*)\b/u', function($m) {
                return '<span class="inline-flex items-center text-xs font-extrabold text-atoma-blue dark:text-atoma-cyan font-mono">#' . e($m[1]) . '</span>';
            }, $clean);

            return $clean;
        }

        // Fallback Markdown Parser
        $html = e($content);

        // Headings (# Heading, ## Heading)
        $html = preg_replace('/^\s*#\s+(.*?)$/m', '<h3 class="text-lg font-black text-gray-900 dark:text-white mt-2 mb-1">$1</h3>', $html);
        $html = preg_replace('/^\s*##\s+(.*?)$/m', '<h4 class="text-base font-bold text-gray-900 dark:text-white mt-2 mb-1">$1</h4>', $html);

        // Markdown Table Renderer
        $html = preg_replace_callback('/((?:\|[^\n]+\|\r?\n)+)/m', function($match) {
            $lines = array_filter(explode("\n", trim($match[0])));
            if (count($lines) < 2) return $match[0];

            $tableHtml = '<div class="overflow-x-auto my-3"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden text-xs">';
            $isHeader = true;

            foreach ($lines as $line) {
                if (str_contains($line, '|---|') || str_contains($line, '|---') || str_contains($line, '| ---')) {
                    $isHeader = false;
                    continue;
                }
                $cells = array_values(array_filter(array_map('trim', explode('|', $line)), fn($c) => $c !== ''));
                if (empty($cells)) continue;

                if ($isHeader) {
                    $tableHtml .= '<thead class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-black"><tr>';
                    foreach ($cells as $cell) {
                        $tableHtml .= '<th class="px-3 py-2 text-left border-b border-gray-200 dark:border-gray-700">' . $cell . '</th>';
                    }
                    $tableHtml .= '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900/60">';
                    $isHeader = false;
                } else {
                    $tableHtml .= '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">';
                    foreach ($cells as $cell) {
                        $tableHtml .= '<td class="px-3 py-2 text-gray-700 dark:text-gray-300 font-medium">' . $cell . '</td>';
                    }
                    $tableHtml .= '</tr>';
                }
            }
            $tableHtml .= '</tbody></table></div>';
            return $tableHtml;
        }, $html);

        // Checklists
        $html = preg_replace('/^\s*-\s*\[\s*\]\s*(.*?)$/m', '<div class="flex items-center my-1 text-slate-700 dark:text-slate-300 font-medium"><input type="checkbox" disabled class="mr-2 rounded border-gray-300 dark:border-gray-600 text-atoma-blue focus:ring-0"><span class="flex-1">$1</span></div>', $html);
        $html = preg_replace('/^\s*-\s*\[[xX]\]\s*(.*?)$/m', '<div class="flex items-center my-1 text-slate-400 dark:text-slate-500"><input type="checkbox" checked disabled class="mr-2 rounded border-gray-300 dark:border-gray-600 text-atoma-blue focus:ring-0"><span class="flex-1 line-through">$1</span></div>', $html);

        // Bullets
        $html = preg_replace('/^\s*-\s+([^\[\n].*?)$/m', '<div class="flex items-center gap-2 my-0.5 text-gray-700 dark:text-gray-300"><span class="text-atoma-blue font-bold">•</span><span>$1</span></div>', $html);

        // Bold
        $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong class="font-black text-gray-900 dark:text-white">$1</strong>', $html);

        // Italic
        $html = preg_replace('/\*([^\*]+)\*/s', '<em class="italic">$1</em>', $html);

        // WikiLinks
        $html = preg_replace_callback('/\[\[(.*?)\]\]/s', function($m) {
            return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-black bg-atoma-ice text-atoma-blue dark:bg-atoma-blue/30 dark:text-atoma-cyan border border-atoma-aqua/60 dark:border-atoma-blue/40 shadow-2xs"><svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>' . e(trim($m[1])) . '</span>';
        }, $html);

        // #Tags
        $html = preg_replace_callback('/(?<!["\'=&#\w])#([a-zA-Z_][\w\-]*)\b/u', function($m) {
            return '<span class="inline-flex items-center text-xs font-extrabold text-atoma-blue dark:text-atoma-cyan font-mono">#' . e($m[1]) . '</span>';
        }, $html);

        return nl2br($html);
    }
}; ?>

<div class="relative pb-24"
     x-data="{
         currentTab: @entangle('activeTab').live
     }"
     x-init="
         try {
             const p = new URLSearchParams(window.location.search);
             if (p.get('action') === 'new' || p.get('new') === '1') {
                 $wire.openCreateModal();
             }
         } catch(e) {}
         window.__atomaJournalTab = currentTab;
         window.dispatchEvent(new CustomEvent('journal-tab-changed', { detail: { tab: currentTab } }));
         $watch('currentTab', val => {
             window.__atomaJournalTab = val;
             window.dispatchEvent(new CustomEvent('journal-tab-changed', { detail: { tab: val } }));
         });
     ">
    <style>
        .journal-card {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .spatial-tag-card {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 27, 75, 0.9));
            box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.2);
        }
        
        /* WYSIWYG Editor & Note Card Table Styling (Horizontal Scroll & Column Protection) */
        .journal-table-wrap {
            width: 100% !important;
            overflow-x: auto !important;
            margin-top: 1.25rem !important;
            margin-bottom: 1.25rem !important;
            border-radius: 0.875rem !important;
            border: 1px solid rgba(229, 231, 235, 1) !important;
            background: transparent !important;
            -webkit-overflow-scrolling: touch;
        }
        .dark .journal-table-wrap {
            border: 1px solid rgba(55, 65, 81, 1) !important;
        }
        .journal-table-wrap table {
            margin: 0 !important;
            border: none !important;
            min-width: 100% !important;
            width: max-content !important;
        }
        #journal-visual-editor-container table, 
        .journal-card table,
        [contenteditable="true"] table {
            min-width: 100% !important;
            border-collapse: collapse !important;
            clear: both !important;
            float: none !important;
            display: table !important;
            border-radius: 0.875rem !important;
            border: 1px solid rgba(229, 231, 235, 1) !important;
        }
        .dark #journal-visual-editor-container table, 
        .dark .journal-card table,
        .dark [contenteditable="true"] table {
            border: 1px solid rgba(55, 65, 81, 1) !important;
        }
        #journal-visual-editor-container th, 
        .journal-card th,
        [contenteditable="true"] th {
            background-color: rgba(243, 244, 246, 0.8) !important;
            padding: 0.75rem 1rem !important;
            text-align: left !important;
            font-weight: 800 !important;
            border: 1px solid rgba(229, 231, 235, 1) !important;
            min-width: 120px !important;
            vertical-align: top !important;
            word-break: break-word !important;
        }
        .dark #journal-visual-editor-container th, 
        .dark .journal-card th,
        .dark [contenteditable="true"] th {
            background-color: rgba(31, 41, 55, 0.8) !important;
            border: 1px solid rgba(55, 65, 81, 1) !important;
            color: #ffffff !important;
        }
        #journal-visual-editor-container td, 
        .journal-card td,
        [contenteditable="true"] td {
            padding: 0.75rem 1rem !important;
            border: 1px solid rgba(229, 231, 235, 1) !important;
            min-width: 120px !important;
            vertical-align: top !important;
            word-break: break-word !important;
        }
        .dark #journal-visual-editor-container td, 
        .dark .journal-card td,
        .dark [contenteditable="true"] td {
            border: 1px solid rgba(55, 65, 81, 1) !important;
        }

        /* Clean Checklist Style with Auto-Strikethrough */
        .journal-checklist-item input[type="checkbox"] {
            width: 1.25rem !important;
            height: 1.25rem !important;
            border-radius: 9999px !important;
            cursor: pointer !important;
        }
        .journal-checklist-item input[type="checkbox"]:checked ~ span,
        .journal-checklist-item input[type="checkbox"][checked] ~ span {
            text-decoration: line-through !important;
            opacity: 0.55 !important;
        }

        /* Unordered & Ordered Lists (Poin & Nomor) */
        #journal-visual-editor-container ul,
        .journal-card ul,
        [contenteditable="true"] ul {
            list-style-type: disc !important;
            padding-left: 1.75rem !important;
            margin-top: 0.5rem !important;
            margin-bottom: 0.5rem !important;
        }
        #journal-visual-editor-container ol,
        .journal-card ol,
        [contenteditable="true"] ol {
            list-style-type: decimal !important;
            padding-left: 1.75rem !important;
            margin-top: 0.5rem !important;
            margin-bottom: 0.5rem !important;
        }
        #journal-visual-editor-container li,
        .journal-card li,
        [contenteditable="true"] li {
            display: list-item !important;
            margin-top: 0.25rem !important;
            margin-bottom: 0.25rem !important;
            padding-left: 0.25rem !important;
        }
        [contenteditable="true"] ul ul,
        .journal-card ul ul {
            list-style-type: circle !important;
        }
        [contenteditable="true"] ol ol,
        .journal-card ol ol {
            list-style-type: lower-alpha !important;
        }

        /* Scoped Heading Styles for Editor and Cards */
        [contenteditable="true"] h1,
        .journal-card h1 {
            font-size: 1.75rem !important;
            font-weight: 900 !important;
            line-height: 1.25 !important;
            margin-top: 1.25rem !important;
            margin-bottom: 0.5rem !important;
        }
        [contenteditable="true"] h2,
        .journal-card h2 {
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            line-height: 1.3 !important;
            margin-top: 1rem !important;
            margin-bottom: 0.375rem !important;
        }
        [contenteditable="true"] h3,
        .journal-card h3 {
            font-size: 1.15rem !important;
            font-weight: 700 !important;
            line-height: 1.35 !important;
            margin-top: 0.75rem !important;
            margin-bottom: 0.25rem !important;
        }

        /* Callout Cards */
        .journal-callout {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: 1rem;
            margin: 0.75rem 0;
            border-width: 1px;
            clear: both;
        }
        .journal-callout.callout-idea {
            background-color: rgba(254, 243, 199, 0.5);
            border-color: rgba(245, 158, 11, 0.3);
        }
        .dark .journal-callout.callout-idea {
            background-color: rgba(120, 53, 15, 0.2);
            border-color: rgba(245, 158, 11, 0.3);
        }
        .journal-callout.callout-warning {
            background-color: rgba(254, 226, 226, 0.5);
            border-color: rgba(239, 68, 68, 0.3);
        }
        .dark .journal-callout.callout-warning {
            background-color: rgba(127, 29, 29, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
        }
        .journal-callout.callout-target {
            background-color: rgba(224, 231, 255, 0.5);
            border-color: rgba(99, 102, 241, 0.3);
        }
        .dark .journal-callout.callout-target {
            background-color: rgba(49, 46, 129, 0.2);
            border-color: rgba(99, 102, 241, 0.3);
        }
        .journal-callout.callout-note {
            background-color: rgba(243, 244, 246, 0.6);
            border-color: rgba(209, 213, 219, 0.6);
        }
        .dark .journal-callout.callout-note {
            background-color: rgba(31, 41, 55, 0.5);
            border-color: rgba(75, 85, 99, 0.4);
        }

        /* Blockquote */
        [contenteditable="true"] blockquote,
        .journal-card blockquote {
            border-left: 4px solid #6366f1 !important;
            padding-left: 0.875rem !important;
            margin: 0.75rem 0 !important;
            font-style: italic !important;
            opacity: 0.9 !important;
        }

        /* Code Block */
        [contenteditable="true"] pre,
        .journal-card pre {
            background-color: rgba(243, 244, 246, 0.8) !important;
            border-radius: 0.75rem !important;
            padding: 0.75rem 1rem !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
            font-size: 0.85rem !important;
            margin: 0.75rem 0 !important;
            overflow-x: auto !important;
            border: 1px solid rgba(229, 231, 235, 0.8) !important;
        }
        .dark [contenteditable="true"] pre,
        .dark .journal-card pre {
            background-color: rgba(17, 24, 39, 0.7) !important;
            border-color: rgba(55, 65, 81, 0.8) !important;
        }

        /* Divider hr */
        [contenteditable="true"] hr,
        .journal-card hr {
            border: 0 !important;
            border-top: 2px dashed rgba(209, 213, 219, 0.8) !important;
            margin: 1.25rem 0 !important;
        }
        .dark [contenteditable="true"] hr,
        .dark .journal-card hr {
            border-top-color: rgba(75, 85, 99, 0.8) !important;
        }

        /* Highlight mark */
        mark.journal-highlight {
            background-color: rgba(254, 240, 138, 0.7) !important;
            color: inherit !important;
            padding: 0.1rem 0.35rem !important;
            border-radius: 0.35rem !important;
        }
        .dark mark.journal-highlight {
            background-color: rgba(161, 98, 7, 0.5) !important;
            color: #fef08a !important;
        }

        /* Enable smooth text selection on all devices */
        #journal-visual-editor-container,
        [contenteditable="true"],
        [contenteditable="true"] * {
            -webkit-user-select: text !important;
            user-select: text !important;
            -webkit-touch-callout: default !important;
        }

        /* Prevent toolbar tap from stealing text selection */
        .toolbar-btn {
            -webkit-user-select: none !important;
            user-select: none !important;
            -webkit-touch-callout: none !important;
        }

        /* Word-like Tab Indentation Space */
        .journal-tab-space {
            display: inline-block !important;
            width: 2.5rem !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            pointer-events: none !important;
            vertical-align: baseline !important;
            white-space: pre !important;
        }
    </style>

    <!-- Top Navigation Header -->
    <div class="sticky top-0 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-md z-20 border-b border-gray-200/60 dark:border-gray-800 transition-all mb-6 -mx-3 sm:-mx-6 lg:-mx-8 px-3 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto py-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div class="flex items-center justify-between w-full sm:w-auto">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 tracking-tight flex items-center gap-2.5">
                        @if($activeTab === 'journal')
                            <img src="/images/icons/open-book.png" alt="Note" class="w-7 h-7 sm:w-8 sm:h-8 object-contain inline-block shrink-0">
                            <span>{{ __('Note') }}</span>
                        @else
                            <img src="/images/icons/roi-finance.png" alt="Keuangan" class="w-7 h-7 sm:w-8 sm:h-8 object-contain inline-block shrink-0">
                            <span>{{ __('Keuangan') }}</span>
                        @endif
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                        {{ $activeTab === 'journal' ? __('Catatan ide, konsep, dan jurnal harian') : __('Pencatatan pemasukan & pengeluaran harian') }}
                    </p>
                </div>
                
                <!-- Action Button for Mobile -->
                <div class="sm:hidden">
                    @if($activeTab === 'journal')
                        <button wire:click="openCreateModal" class="bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl py-2 px-3.5 flex items-center shadow-md shadow-atoma-blue/30 font-bold text-xs cursor-pointer active:scale-95 transition-all">
                            <span class="text-sm font-black mr-1">+</span> {{ __('Catatan') }}
                        </button>
                    @else
                        <button wire:click="openCreateFinance('expense')" class="bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl py-2 px-3.5 flex items-center shadow-md font-bold text-xs cursor-pointer active:scale-95 transition-all">
                            <span class="text-sm font-black mr-1">+</span> {{ __('Transaksi') }}
                        </button>
                    @endif
                </div>
            </div>
            
            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <!-- Segmented Control Tabs -->
                <div class="flex bg-gray-200/70 dark:bg-gray-800/70 p-1 rounded-xl flex-1 sm:flex-initial shadow-inner">
                    <button wire:click="$set('activeTab', 'journal')" @click="window.__atomaJournalTab = 'journal'; window.dispatchEvent(new CustomEvent('journal-tab-changed', { detail: { tab: 'journal' } }))" class="flex-1 sm:flex-none flex items-center justify-center gap-1.5 sm:px-4 py-1.5 rounded-lg font-bold text-xs transition-all cursor-pointer {{ $activeTab === 'journal' ? 'bg-white dark:bg-gray-700 shadow-sm text-atoma-blue dark:text-atoma-cyan font-black' : 'text-gray-500 hover:text-atoma-blue dark:hover:text-atoma-cyan' }}">
                        <img src="/images/icons/open-book.png" alt="Note" class="w-4 h-4 object-contain inline-block shrink-0">
                        <span>{{ __('Note') }}</span>
                    </button>
                    <button wire:click="$set('activeTab', 'finance')" @click="window.__atomaJournalTab = 'finance'; window.dispatchEvent(new CustomEvent('journal-tab-changed', { detail: { tab: 'finance' } }))" class="flex-1 sm:flex-none flex items-center justify-center gap-1.5 sm:px-4 py-1.5 rounded-lg font-bold text-xs transition-all cursor-pointer {{ $activeTab === 'finance' ? 'bg-white dark:bg-gray-700 shadow-sm text-atoma-blue dark:text-atoma-cyan font-black' : 'text-gray-500 hover:text-atoma-blue dark:hover:text-atoma-cyan' }}">
                        <img src="/images/icons/roi-finance.png" alt="Keuangan" class="w-4 h-4 object-contain inline-block shrink-0">
                        <span>{{ __('Keuangan') }}</span>
                    </button>
                    <a href="{{ route('flashcards') }}" wire:navigate class="flex-1 sm:flex-none sm:px-4 py-1.5 rounded-lg font-bold text-xs transition-all cursor-pointer text-gray-500 hover:text-atoma-blue dark:hover:text-atoma-cyan hover:bg-white/50 dark:hover:bg-gray-700/50 flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>{{ __('Flashcard') }}</span>
                    </a>
                </div>

                <!-- Desktop Action Button -->
                @if($activeTab === 'journal')
                    <button wire:click="openCreateModal" class="hidden sm:flex bg-atoma-blue hover:bg-[#3455dc] text-white rounded-xl h-9 px-4 items-center justify-center shadow-md shadow-atoma-blue/30 transition-all font-bold text-xs shrink-0 cursor-pointer active:scale-95">
                        <span class="text-sm font-black mr-1.5">+</span> {{ __('Tulis Catatan Baru') }}
                    </button>
                @else
                    <button wire:click="openCreateFinance('expense')" class="hidden sm:flex bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl h-9 px-4 items-center justify-center shadow-md transition-all font-bold text-xs shrink-0 cursor-pointer active:scale-95">
                        <span class="text-sm font-black mr-1.5">+</span> {{ __('Catat Keuangan') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if($activeTab === 'journal')
    <!-- Note Main Content -->
    <div x-data="{ viewMode: (localStorage.getItem('atoma_note_view') || 'grid'), expandedNotes: {} }" class="max-w-5xl mx-auto space-y-5">
        
        <!-- Search, Tag Dropdown & View Mode Switcher Toolbar -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Search Box -->
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" 
                       wire:model.live.debounce.400ms="searchQuery" 
                       placeholder="{{ __('Cari kata kunci atau tag...') }}" 
                       class="w-full pl-9 pr-8 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl text-xs font-semibold text-gray-900 dark:text-white focus:ring-2 focus:ring-atoma-blue shadow-xs placeholder-gray-400">
                @if(trim($searchQuery) !== '')
                    <button type="button" wire:click="$set('searchQuery', '')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 text-xs font-black cursor-pointer">✕</button>
                @endif
            </div>

            <!-- Custom Alpine Tag Filter Dropdown -->
            <div x-data="{ open: false }" class="relative shrink-0">
                <button type="button" 
                        @click="open = !open" 
                        class="flex items-center gap-1.5 px-3.5 py-2 rounded-2xl text-xs font-extrabold transition-all border shadow-xs cursor-pointer {{ $selectedTagFilter !== 'all' ? 'bg-atoma-ice dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-300 border-atoma-aqua/60 dark:border-indigo-500/40' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-atoma-aqua/60 dark:hover:border-indigo-500/40' }}">
                    <span class="text-atoma-blue dark:text-indigo-400 font-black">#</span>
                    <span class="truncate max-w-[100px] sm:max-w-xs">
                        {{ $selectedTagFilter === 'all' ? __('Semua Tag') . ' (' . auth()->user()->journals()->count() . ')' : $selectedTagFilter }}
                    </span>
                    <svg class="w-3.5 h-3.5 text-gray-400 ml-0.5 transition-transform duration-200" :class="open ? 'rotate-180 text-atoma-blue dark:text-indigo-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                </button>

                <!-- Dropdown Menu Popover -->
                <div x-show="open" 
                     @click.away="open = false" 
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     style="display: none;" 
                     class="absolute right-0 top-12 z-50 w-60 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 p-2 space-y-1 max-h-64 overflow-y-auto hide-scrollbar ring-1 ring-black/5 dark:ring-white/10">
                    
                    <div class="text-[10px] font-black uppercase text-gray-500 dark:text-gray-400 px-3 py-1.5 tracking-wider border-b border-gray-100 dark:border-gray-750 mb-1">
                        {{ __('Filter Berdasarkan Tag') }}
                    </div>

                    <!-- All Notes Option -->
                    @php $isAllSelected = $selectedTagFilter === 'all'; @endphp
                    <button type="button" 
                            @click="$wire.set('selectedTagFilter', 'all'); open = false;" 
                            class="w-full text-left px-3 py-2 rounded-xl text-xs font-black flex items-center justify-between transition-all cursor-pointer {{ $isAllSelected ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-md shadow-atoma-blue/30' : 'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-750' }}">
                        <span class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <span>{{ __('Semua Catatan') }}</span>
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $isAllSelected ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            {{ auth()->user()->journals()->count() }}
                        </span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-gray-750 my-1"></div>

                    <!-- Individual Tag Options -->
                    @forelse($this->allTagsWithCounts as $tName => $tCount)
                        @php $isTagSelected = $selectedTagFilter === $tName; @endphp
                        <button type="button" 
                                @click="$wire.set('selectedTagFilter', '{{ $tName }}'); open = false;" 
                                class="w-full text-left px-3 py-2 rounded-xl text-xs font-black flex items-center justify-between transition-all cursor-pointer {{ $isTagSelected ? 'bg-atoma-blue dark:bg-indigo-600 text-white shadow-md shadow-atoma-blue/30' : 'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-750' }}">
                            <span class="truncate mr-2 font-mono {{ $isTagSelected ? 'text-white' : 'text-atoma-blue dark:text-indigo-400' }}">#{{ $tName }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full shrink-0 {{ $isTagSelected ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                                {{ $tCount }}
                            </span>
                        </button>
                    @empty
                        <div class="px-3 py-2 text-center text-xs text-gray-400 font-medium">{{ __('Belum ada tag') }}</div>
                    @endforelse
                </div>
            </div>

            <!-- Custom Alpine Sort Dropdown -->
            <div x-data="{ openSort: false }" class="relative shrink-0">
                <button type="button" 
                        @click="openSort = !openSort" 
                        class="flex items-center gap-1.5 px-3 py-2 rounded-2xl text-xs font-bold transition-all border shadow-xs cursor-pointer bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-atoma-blue">
                    <svg class="w-3.5 h-3.5 text-atoma-blue dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                    <span class="hidden sm:inline text-gray-500 dark:text-gray-400">Urut:</span>
                    <span class="font-extrabold text-atoma-blue dark:text-indigo-300">
                        @if($noteSortBy === 'latest_updated') Terbaru Diperbarui
                        @elseif($noteSortBy === 'created_newest') Dibuat Terbaru
                        @elseif($noteSortBy === 'created_oldest') Dibuat Terlama
                        @elseif($noteSortBy === 'title_asc') Judul (A-Z)
                        @endif
                    </span>
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <!-- Sort Menu Popup -->
                <div x-show="openSort" 
                     @click.outside="openSort = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     style="display: none;"
                     class="absolute right-0 sm:left-0 top-12 z-50 w-52 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 p-2 space-y-1 ring-1 ring-black/5 dark:ring-white/10">
                    <div class="text-[10px] font-black uppercase text-gray-400 px-2.5 py-1 tracking-wider border-b border-gray-100 dark:border-gray-750 mb-1">
                        Urutkan Catatan
                    </div>
                    <button type="button" @click="$wire.set('noteSortBy', 'latest_updated'); openSort = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between cursor-pointer {{ $noteSortBy === 'latest_updated' ? 'bg-atoma-blue text-white shadow-xs' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span>🕒 Terakhir Diperbarui</span>
                        @if($noteSortBy === 'latest_updated')<span>✓</span>@endif
                    </button>
                    <button type="button" @click="$wire.set('noteSortBy', 'created_newest'); openSort = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between cursor-pointer {{ $noteSortBy === 'created_newest' ? 'bg-atoma-blue text-white shadow-xs' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span>📅 Tanggal Dibuat (Baru)</span>
                        @if($noteSortBy === 'created_newest')<span>✓</span>@endif
                    </button>
                    <button type="button" @click="$wire.set('noteSortBy', 'created_oldest'); openSort = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between cursor-pointer {{ $noteSortBy === 'created_oldest' ? 'bg-atoma-blue text-white shadow-xs' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span>⏳ Tanggal Dibuat (Lama)</span>
                        @if($noteSortBy === 'created_oldest')<span>✓</span>@endif
                    </button>
                    <button type="button" @click="$wire.set('noteSortBy', 'title_asc'); openSort = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between cursor-pointer {{ $noteSortBy === 'title_asc' ? 'bg-atoma-blue text-white shadow-xs' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span>🔤 Judul (A - Z)</span>
                        @if($noteSortBy === 'title_asc')<span>✓</span>@endif
                    </button>
                    <div class="border-t border-gray-100 dark:border-gray-750 my-1"></div>
                    <button type="button" wire:click="exportNotes" @click="openSort = false" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-bold text-gray-500 hover:text-atoma-blue dark:text-gray-400 dark:hover:text-indigo-400 flex items-center gap-1.5 cursor-pointer" title="Unduh backup data catatan (JSON)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Ekspor Cadangan Catatan</span>
                    </button>
                </div>
            </div>

            <!-- View Mode Switcher (Grid vs List) -->
            <div class="flex items-center bg-gray-100 dark:bg-gray-800 p-1 rounded-2xl border border-gray-200/80 dark:border-gray-700 shadow-2xs shrink-0">
                <button type="button" 
                        @click="viewMode = 'grid'; localStorage.setItem('atoma_note_view', 'grid')" 
                        :class="viewMode === 'grid' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-indigo-300 shadow-xs font-bold' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'" 
                        class="p-1.5 sm:px-2.5 sm:py-1 rounded-xl text-xs transition-all cursor-pointer flex items-center gap-1.5"
                        title="{{ __('Tampilan Grid / Kartu (2 Kolom)') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span class="hidden sm:inline text-[11px]">Grid</span>
                </button>
                <button type="button" 
                        @click="viewMode = 'list'; localStorage.setItem('atoma_note_view', 'list')" 
                        :class="viewMode === 'list' ? 'bg-white dark:bg-gray-700 text-atoma-blue dark:text-indigo-300 shadow-xs font-bold' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'" 
                        class="p-1.5 sm:px-2.5 sm:py-1 rounded-xl text-xs transition-all cursor-pointer flex items-center gap-1.5"
                        title="{{ __('Tampilan List / Aliran (1 Kolom)') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <span class="hidden sm:inline text-[11px]">List</span>
                </button>
            </div>
        </div>

        <!-- Feed Notes List -->
        <div class="space-y-6">
            @forelse($this->groupedJournals as $date => $journals)
                @php
                    $carbonDate = \Carbon\Carbon::parse($date);
                    $isToday = $carbonDate->isToday();
                @endphp
                <div>
                    <!-- Date Header with Indicator -->
                    <div class="sticky top-[68px] z-10 flex items-center justify-between mb-3.5 bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-sm py-2 border-b border-gray-200/60 dark:border-gray-800/80">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-atoma-blue dark:bg-indigo-500 shrink-0"></span>
                            <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white">
                                {{ $isToday ? __('Hari Ini') : $carbonDate->translatedFormat('l') }}
                            </h3>
                            <span class="text-gray-400 dark:text-gray-500 font-medium text-xs">
                                {{ $carbonDate->translatedFormat('d F Y') }}
                            </span>
                        </div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-lg bg-gray-200/60 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                            {{ $journals->count() }} {{ __('Catatan') }}
                        </span>
                    </div>

                    <!-- Entries: Grid or List Switch -->
                    <div :class="viewMode === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 gap-4 items-start' : 'space-y-3.5'">
                        @foreach($journals as $journal)
                            @php
                                $stripped = strip_tags($journal->content);
                                $wordCount = str_word_count($stripped);
                                $readTime = max(1, ceil($wordCount / 150));
                                $lineBreaks = substr_count($journal->content, '<p') 
                                    + substr_count($journal->content, '<li') 
                                    + substr_count($journal->content, '<br') 
                                    + substr_count($journal->content, '<blockquote') 
                                    + substr_count($journal->content, '<tr') 
                                    + substr_count($journal->content, "\n");
                                $isLong = strlen($stripped) > 130 || $lineBreaks >= 3;
                            @endphp
                            <div wire:click="editJournal({{ $journal->id }})" 
                                 x-data="{ 
                                     hasOverflow: {{ $isLong ? 'true' : 'false' }},
                                     checkOverflow() {
                                         if (this.$refs.contentEl) {
                                             this.hasOverflow = this.hasOverflow || (this.$refs.contentEl.scrollHeight > this.$refs.contentEl.clientHeight + 4);
                                         }
                                     }
                                 }"
                                 x-init="$nextTick(() => checkOverflow()); window.addEventListener('resize', () => checkOverflow())"
                                 class="journal-card bg-white dark:bg-gray-850 rounded-2xl shadow-xs hover:shadow-md border border-gray-100 dark:border-gray-750 hover:border-atoma-aqua/60 dark:hover:border-indigo-500/40 p-4 sm:p-5 transition-all relative group cursor-pointer flex flex-col justify-between h-full">
                                
                                <!-- Top Area: Title & Delete Action -->
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <h4 class="text-sm sm:text-base font-black text-gray-900 dark:text-white leading-snug group-hover:text-atoma-blue dark:group-hover:text-indigo-400 transition-colors">
                                            {{ $journal->title ?: 'Tanpa Judul' }}
                                        </h4>

                                        <!-- Delete Button -->
                                        <div class="flex items-center gap-1 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                            <button type="button" @click.stop="window.dispatchEvent(new CustomEvent('confirm-delete', { detail: { id: {{ $journal->id }}, action: 'delete-journal' } }))" 
                                                    title="Hapus Catatan" 
                                                    class="text-gray-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-950/60 rounded-lg p-1 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Content Preview with smart line clamp in grid mode -->
                                    <div x-ref="contentEl"
                                         class="text-gray-700 dark:text-gray-300 text-xs sm:text-sm leading-relaxed font-normal"
                                         :class="(viewMode === 'grid' && !expandedNotes[{{ $journal->id }}]) ? 'line-clamp-4' : ''">
                                        {!! $this->formatContent($journal->content) !!}
                                    </div>

                                    <button type="button" 
                                            x-show="viewMode === 'grid' && (hasOverflow || expandedNotes[{{ $journal->id }}])"
                                            @click.stop="expandedNotes[{{ $journal->id }}] = !expandedNotes[{{ $journal->id }}]"
                                            class="mt-1.5 text-[11px] font-bold text-atoma-blue dark:text-indigo-400 hover:underline inline-flex items-center gap-1 cursor-pointer">
                                        <span x-text="expandedNotes[{{ $journal->id }}] ? 'Sembunyikan' : 'Baca selengkapnya...'"></span>
                                    </button>

                                    <!-- Reference Link -->
                                    @if($journal->reference_url)
                                        <div @click.stop class="mt-2.5 flex items-center text-[11px] font-bold text-atoma-blue dark:text-indigo-300 bg-atoma-ice/80 dark:bg-indigo-950/50 p-2 rounded-xl border border-atoma-aqua/50 dark:border-indigo-500/30">
                                            <svg class="w-3 h-3 mr-1.5 shrink-0 text-atoma-blue dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                            <span class="mr-1 text-gray-400 font-medium">Link:</span>
                                            <a href="{{ $journal->reference_url }}" target="_blank" class="hover:underline truncate font-bold">
                                                {{ $journal->reference_url }}
                                            </a>
                                        </div>
                                    @endif

                                    <!-- Photos -->
                                    @if(!empty($journal->images))
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($journal->images as $img)
                                                <div class="relative overflow-hidden rounded-xl">
                                                    <img src="{{ Storage::url($img) }}" class="h-20 w-auto object-cover rounded-xl">
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <!-- Card Footer: Time, Tags, Reading Estimate & Extract Button -->
                                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[11px] font-semibold text-gray-400 flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            {{ \Carbon\Carbon::parse($journal->created_at)->format('H:i') }}
                                        </span>

                                        <span class="text-gray-300 dark:text-gray-600 text-xs">•</span>
                                        <span class="text-[10px] text-gray-400 font-medium">~{{ $readTime }} mnt baca</span>

                                        @php
                                            $tagList = $journal->tags ?? [];
                                            if (is_string($tagList)) $tagList = json_decode($tagList, true) ?? [];
                                            $tagList = array_filter($tagList, fn($t) => is_string($t));
                                        @endphp
                                        @foreach(array_slice($tagList, 0, 2) as $tag)
                                            <button type="button" @click.stop="$wire.set('selectedTagFilter', '{{ ltrim($tag, '#') }}')" class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-atoma-ice text-atoma-blue dark:bg-indigo-950/60 dark:text-indigo-300 border border-atoma-aqua/40 dark:border-indigo-500/30 hover:bg-atoma-aqua/20 transition-colors cursor-pointer">
                                                #{{ ltrim($tag, '#') }}
                                            </button>
                                        @endforeach
                                        @if(count($tagList) > 2)
                                            <span class="text-[10px] text-gray-400 font-bold">+{{ count($tagList) - 2 }}</span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1.5 ml-auto">
                                        @php
                                            $flashcardCount = $journal->flashcards ? $journal->flashcards->count() : 0;
                                        @endphp
                                        @if($flashcardCount > 0)
                                            <a href="{{ route('flashcards') }}?journal_id={{ $journal->id }}" @click.stop wire:navigate class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 hover:bg-indigo-100" title="{{ $flashcardCount }} Flashcard terkait">
                                                <span>🗂️ {{ $flashcardCount }}</span>
                                            </a>
                                        @endif

                                        <button type="button" 
                                                wire:click.stop="extractFlashcardsFromNote({{ $journal->id }})" 
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10px] font-bold text-purple-600 dark:text-purple-300 bg-purple-50 hover:bg-purple-600 hover:text-white dark:bg-purple-950/50 dark:hover:bg-purple-900 border border-purple-200 dark:border-purple-800/60 transition-all cursor-pointer shadow-2xs" 
                                                title="Ekstrak Flashcard SRS">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            <span class="hidden md:inline">Flashcard</span>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-center py-16 bg-white dark:bg-gray-800/40 rounded-3xl border border-dashed border-gray-200 dark:border-gray-700 p-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-indigo-50 dark:bg-indigo-900/20 mb-3 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Belum Ada Catatan</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-4 text-xs">Tulis catatan ide atau konsep harimu.</p>
                    <button type="button" wire:click="openCreateModal" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-full py-2.5 px-6 font-bold shadow-md text-xs cursor-pointer">
                        Tulis Catatan Pertama
                    </button>
                </div>
            @endforelse
        </div>
    </div>
    @else
    <!-- Finance Tracker (Phase A-C Comprehensive Tracker) -->
    <div class="space-y-8 max-w-5xl mx-auto">
        
        <!-- Month / Year Selector -->
        <div class="flex flex-col gap-4 mb-2">
            <div class="flex justify-between items-center bg-gray-100 dark:bg-gray-800 rounded-2xl p-1 shadow-inner max-w-sm mx-auto w-full">
                <button wire:click="$set('financeViewMode', 'monthly')" class="flex-1 py-2 rounded-xl text-sm font-bold transition-all {{ $financeViewMode === 'monthly' ? 'bg-white text-indigo-600 dark:bg-gray-700 shadow-sm dark:text-indigo-400 font-extrabold' : 'text-gray-500 hover:text-gray-700' }}">Bulanan</button>
                <button wire:click="$set('financeViewMode', 'yearly')" class="flex-1 py-2 rounded-xl text-sm font-bold transition-all {{ $financeViewMode === 'yearly' ? 'bg-white text-indigo-600 dark:bg-gray-700 shadow-sm dark:text-indigo-400 font-extrabold' : 'text-gray-500 hover:text-gray-700' }}">Tahunan</button>
            </div>

            <!-- Calendar Selector -->
            <div class="flex overflow-x-auto hide-scrollbar gap-2 pb-2 mx-auto max-w-full">
                @if($financeViewMode === 'monthly')
                    @foreach(range(1, 12) as $m)
                        <button wire:click="$set('financeMonth', {{ $m }})" class="px-5 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all border {{ $financeMonth === $m ? 'bg-indigo-600 text-white border-indigo-600 shadow-md font-extrabold' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-indigo-300' }}">
                            {{ \Carbon\Carbon::create()->month($m)->locale('id')->isoFormat('MMM') }}
                        </button>
                    @endforeach
                @else
                    @foreach(range(date('Y') - 2, date('Y') + 2) as $y)
                        <button wire:click="$set('financeYear', {{ $y }})" class="px-6 py-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all border {{ $financeYear === $y ? 'bg-indigo-600 text-white border-indigo-600 shadow-md font-extrabold' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-indigo-300' }}">
                            {{ $y }}
                        </button>
                    @endforeach
                @endif
            </div>
        </div>

        <!-- 4 Summary Cards (Saldo Kas, Pemasukan, Pengeluaran Murni, Tabungan) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Saldo Kas -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-gray-700/80 flex flex-col justify-between relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Saldo Kas</span>
                    <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                </div>
                <div>
                    <div class="text-xl sm:text-2xl font-black tracking-tight {{ $this->financeStats['balance'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-500' }}">
                        Rp {{ number_format($this->financeStats['balance'], 0, ',', '.') }}
                    </div>
                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 font-medium">Total dana kas tersedia</div>
                </div>
            </div>

            <!-- Pemasukan -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-gray-700/80 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        Pemasukan
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    </div>
                </div>
                <div>
                    <div class="text-xl sm:text-2xl font-black tracking-tight text-emerald-600 dark:text-emerald-400">
                        + Rp {{ number_format($this->financeStats['income'], 0, ',', '.') }}
                    </div>
                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 font-medium">Total pendapatan masuk</div>
                </div>
            </div>

            <!-- Pengeluaran Murni -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-gray-700/80 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1">
                        Pengeluaran Murni
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    </div>
                </div>
                <div>
                    <div class="text-xl sm:text-2xl font-black tracking-tight text-rose-600 dark:text-rose-400">
                        - Rp {{ number_format($this->financeStats['expense'], 0, ',', '.') }}
                    </div>
                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 font-medium">Di luar alokasi tabungan</div>
                </div>
            </div>

            <!-- Tabungan Disisihkan -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-gray-700/80 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400 flex items-center gap-1">
                        Tabungan Disisihkan
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                </div>
                <div>
                    <div class="text-xl sm:text-2xl font-black tracking-tight text-violet-600 dark:text-violet-400">
                        Rp {{ number_format($this->financeStats['savings'], 0, ',', '.') }}
                    </div>
                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 font-medium">Aset & tabungan tersimpan</div>
                </div>
            </div>
        </div>

        <!-- Row 2: Monthly Budget Tracker & Category Expense Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Monthly Budget Tracker Card -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-gray-900 dark:text-white text-sm">Target Anggaran Bulanan</h3>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Batas pengeluaran maksimal</p>
                            </div>
                        </div>
                        <button wire:click="openBudgetModal" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 py-1.5 px-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 transition-all">
                            Atur Anggaran
                        </button>
                    </div>

                    @if($this->financeStats['user_budget'] > 0)
                        <div class="space-y-3">
                            <div class="flex items-baseline justify-between">
                                <div>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">Terpakai: </span>
                                    <span class="text-base font-black text-gray-900 dark:text-white">Rp {{ number_format($this->financeStats['expense'], 0, ',', '.') }}</span>
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">/ Rp {{ number_format($this->financeStats['user_budget'], 0, ',', '.') }}</span>
                                </div>
                                <div>
                                    @if($this->financeStats['budget_percentage'] < 70)
                                        <span class="px-2.5 py-1 text-[11px] font-extrabold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Terkendali ({{ $this->financeStats['budget_percentage'] }}%)</span>
                                    @elseif($this->financeStats['budget_percentage'] <= 90)
                                        <span class="px-2.5 py-1 text-[11px] font-extrabold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">Waspada ({{ $this->financeStats['budget_percentage'] }}%)</span>
                                    @else
                                        <span class="px-2.5 py-1 text-[11px] font-extrabold rounded-full bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300">Overbudget ({{ $this->financeStats['budget_percentage'] }}%)</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="h-3 w-full bg-gray-100 dark:bg-gray-700/70 rounded-full overflow-hidden p-0.5">
                                <div class="h-full rounded-full transition-all duration-500 {{ $this->financeStats['budget_percentage'] < 70 ? 'bg-emerald-500' : ($this->financeStats['budget_percentage'] <= 90 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                     style="width: {{ min(100, $this->financeStats['budget_percentage']) }}%;"></div>
                            </div>

                            <div class="flex items-center justify-between text-xs pt-1">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">Status Limit:</span>
                                @if($this->financeStats['is_over_budget'])
                                    <span class="text-red-500 font-bold">Melebihi anggaran Rp {{ number_format(abs($this->financeStats['remaining_budget']), 0, ',', '.') }}</span>
                                @else
                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">Sisa anggaran Rp {{ number_format($this->financeStats['remaining_budget'], 0, ',', '.') }}</span>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="py-5 text-center bg-gray-50 dark:bg-gray-900/40 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700/60">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-3">Belum ada limit anggaran bulanan yang ditentukan.</p>
                            <button wire:click="openBudgetModal" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition-all">
                                <span class="font-black">+</span> Pasang Limit Anggaran
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Expense Category Breakdown Card -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-gray-900 dark:text-white text-sm">Distribusi Pengeluaran</h3>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Pengeluaran berdasarkan kategori</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-gray-400">
                            {{ count($this->financeStats['category_breakdown']) }} Kategori
                        </span>
                    </div>

                    @if(count($this->financeStats['category_breakdown']) > 0)
                        <!-- Segmented distribution progress bar -->
                        <div class="h-3 w-full bg-gray-100 dark:bg-gray-700/70 rounded-full overflow-hidden flex mb-4">
                            @php
                                $segmentColors = ['bg-rose-500', 'bg-amber-500', 'bg-blue-500', 'bg-indigo-500', 'bg-purple-500', 'bg-cyan-500', 'bg-emerald-500', 'bg-gray-400'];
                            @endphp
                            @foreach($this->financeStats['category_breakdown'] as $idx => $breakdown)
                                <div class="{{ $segmentColors[$idx % count($segmentColors)] }} h-full" 
                                     style="width: {{ $breakdown['percentage'] }}%;" 
                                     title="{{ $breakdown['category'] }}: {{ $breakdown['percentage'] }}%"></div>
                            @endforeach
                        </div>

                        <!-- Top Categories List -->
                        <div class="space-y-2.5 max-h-44 overflow-y-auto pr-1">
                            @foreach(array_slice($this->financeStats['category_breakdown'], 0, 4) as $item)
                                @php
                                    $itemMeta = $this->getCategoryMeta($item['category'], 'expense');
                                @endphp
                                <div class="flex items-center justify-between text-xs py-1">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-lg flex items-center justify-center {{ $itemMeta['bg'] }} {{ $itemMeta['color'] }}">
                                            {!! str_replace('w-5 h-5', 'w-3.5 h-3.5', $itemMeta['icon']) !!}
                                        </div>
                                        <span class="font-bold text-gray-800 dark:text-gray-200">{{ $item['category'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold text-gray-900 dark:text-white">Rp {{ number_format($item['amount'], 0, ',', '.') }}</span>
                                        <span class="text-[11px] font-extrabold px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 w-12 text-right">{{ $item['percentage'] }}%</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-5 text-center bg-gray-50 dark:bg-gray-900/40 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700/60">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Belum ada pengeluaran di periode ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 sm:p-4 shadow-sm border border-gray-100 dark:border-gray-700/80">
            <div class="flex items-center gap-2.5 w-full">
                <!-- Search Input -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text" 
                           wire:model.live.debounce.300ms="financeSearch" 
                           placeholder="Cari transaksi atau catatan..." 
                           class="block w-full pl-9 pr-8 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-medium text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    @if($financeSearch)
                        <button wire:click="$set('financeSearch', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                <!-- Category Filter -->
                <div class="w-36 sm:w-48 shrink-0">
                    <select wire:model.live="financeCategoryFilter" class="block w-full py-2 px-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-medium text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">Semua Kategori</option>
                        <optgroup label="Pemasukan">
                            @foreach($categories['income'] as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Pengeluaran">
                            @foreach($categories['expense'] as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Tabungan">
                            @foreach($categories['savings'] as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                @if($financeSearch || $financeCategoryFilter)
                    <button wire:click="resetFinanceFilters" class="text-xs font-bold text-red-500 hover:underline shrink-0 px-1">
                        Reset
                    </button>
                @endif
            </div>
        </div>

        <!-- Transactions List -->
        <div>
            @forelse($this->groupedFinances as $date => $transactions)
                @php
                    $carbonDate = \Carbon\Carbon::parse($date);
                    $isToday = $carbonDate->isToday();
                @endphp
                <div class="mb-8">
                    <!-- Date Header -->
                    <div class="sticky top-[88px] z-10 flex items-baseline space-x-2 mb-4 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-sm py-2">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $isToday ? 'Hari Ini' : $carbonDate->locale('id')->isoFormat('dddd') }}
                        </h3>
                        <span class="text-gray-500 dark:text-gray-400 font-medium text-xs">
                            {{ $carbonDate->locale('id')->isoFormat('D MMMM Y') }}
                        </span>
                    </div>

                    <!-- Entries -->
                    <div class="space-y-3">
                        @foreach($transactions as $t)
                            @php
                                $meta = $this->getCategoryMeta($t->category, $t->type);
                            @endphp
                            <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-gray-700/60 flex items-center justify-between group hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                                <div class="flex items-center space-x-4">
                                    <!-- Custom Category SVG Icon -->
                                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 {{ $meta['bg'] }} {{ $meta['color'] }} border {{ $meta['border'] }}">
                                        {!! $meta['icon'] !!}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $t->category }}</span>
                                            @if($t->type === 'savings')
                                                <span class="inline-flex items-center text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-md bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300">
                                                    Tabungan
                                                </span>
                                            @elseif($t->type === 'income')
                                                <span class="inline-flex items-center text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                                                    Pemasukan
                                                </span>
                                            @endif
                                        </div>
                                        @if($t->description)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $t->description }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-base sm:text-lg {{ $t->type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : ($t->type === 'savings' ? 'text-violet-600 dark:text-violet-400' : 'text-gray-900 dark:text-white') }}">
                                        @if($t->type === 'income')
                                            + Rp {{ number_format($t->amount, 0, ',', '.') }}
                                        @elseif($t->type === 'savings')
                                            Rp {{ number_format($t->amount, 0, ',', '.') }}
                                        @else
                                            - Rp {{ number_format($t->amount, 0, ',', '.') }}
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-end gap-2 mt-1">
                                        <button wire:click="openEditFinance({{ $t->id }})" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-bold opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                            Edit
                                        </button>
                                        <span class="text-gray-300 dark:text-gray-600 text-xs opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">•</span>
                                        <button @click="$dispatch('confirm-delete', { id: {{ $t->id }}, action: 'delete-finance' })" class="text-xs text-red-500 hover:underline font-bold opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-center py-16 bg-white dark:bg-gray-800/40 rounded-3xl border border-dashed border-gray-200 dark:border-gray-700 p-6">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    @if($financeSearch || $financeCategoryFilter)
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Tidak Ada Transaksi yang Cocok</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">Coba ubah kata kunci pencarian atau filter kategori kamu</p>
                        <button wire:click="resetFinanceFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-full py-2.5 px-6 font-bold text-xs shadow-md cursor-pointer">
                            Reset Filter
                        </button>
                    @else
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Belum Ada Transaksi</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">Mulai catat pemasukan, pengeluaran, dan tabungan harianmu</p>
                        <button wire:click="openCreateFinance('expense')" class="bg-emerald-600 hover:bg-emerald-500 text-white rounded-full py-2.5 px-6 font-bold text-xs shadow-md cursor-pointer">
                            Catat Transaksi
                        </button>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
    @endif

    <!-- Create / Edit Journal Notion Full-Page Editor Modal -->
    <div x-data="{ get show() { return $wire.showCreateModal } }" x-show="show" style="display: none;" class="relative z-50">
        <!-- Backdrop Overlay -->
        <div x-transition.opacity @click="$wire.showCreateModal = false" class="fixed inset-0 bg-gray-950/85 z-40"></div>
        
        <!-- Main Full-Page Modal Overlay -->
        <div x-transition 
             x-data="notionJournalEditor()"
             @keydown.window="handleWindowKeydown($event)"
             @keydown.escape.window="handleEscapeKey()"
             class="fixed inset-0 sm:inset-2 md:inset-3 lg:inset-4 xl:inset-6 z-50 pointer-events-auto bg-white dark:bg-gray-900 rounded-none sm:rounded-3xl shadow-2xl flex flex-col overflow-hidden border border-gray-200/60 dark:border-gray-800">
            
            <!-- Notion Top Navigation Bar (Desktop Responsive Header) -->
            <div x-show="!zenMode" class="flex justify-between items-center px-4 sm:px-6 py-2 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shrink-0 z-40">
                <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-xs font-semibold min-w-0 flex-1 mr-2">
                    <!-- Kembali ke Catatan (Unify Mental Model: Clear Back Navigation with Autosave) -->
                    <button type="button" 
                            @click="syncContent(); $wire.autoSaveJournal(); $wire.showCreateModal = false;" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all cursor-pointer active:scale-95 shrink-0"
                            title="Kembali ke Daftar Catatan (Otomatis Tersimpan)">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span class="font-extrabold tracking-tight">Catatan</span>
                    </button>

                    <!-- Toggle Left Notes Sidebar (Drawer) -->
                    <button type="button" 
                            @click="toggleLeftSidebar()" 
                            class="hidden md:inline-flex items-center justify-center p-1.5 rounded-xl border text-xs font-bold transition-all cursor-pointer select-none shrink-0"
                            :class="showLeftSidebar ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800' : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                            :title="showLeftSidebar ? 'Sembunyikan Panel Catatan' : 'Buka Panel Catatan'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    </button>

                    <!-- Sticky Header Title: Hidden at top of canvas, fades in smoothly when user scrolls down! -->
                    <div x-show="isScrolled" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-x-2"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-x-0"
                         x-transition:leave-end="opacity-0 -translate-x-2"
                         style="display: none;"
                         class="flex items-center gap-1.5 min-w-0">
                        <span class="text-gray-300 dark:text-gray-600 font-light">/</span>
                        <svg class="w-3.5 h-3.5 text-atoma-blue dark:text-atoma-cyan shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="font-bold text-gray-800 dark:text-gray-200 truncate max-w-[120px] sm:max-w-xs text-xs sm:text-sm">
                            {{ $title ? $title : 'Tanpa Judul' }}
                        </span>
                    </div>

                    <!-- Calm Silent AutoSave Indicator -->
                    <div class="ml-1 flex items-center text-[10px] sm:text-[11px] font-normal text-gray-400 dark:text-gray-500 select-none shrink-0">
                        <span x-show="autosaveStatus === 'saving'" style="display: none;" class="flex items-center gap-1 text-gray-400">
                            <svg class="animate-spin w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="hidden sm:inline">Menyimpan...</span>
                        </span>
                        <span x-show="autosaveStatus === 'saved'" style="display: none;" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            <span class="hidden sm:inline">Tersimpan</span>
                        </span>
                    </div>

                    <!-- Top Bar Tags Management (Clean, Compact Header Strip) -->
                    <div class="flex items-center gap-1.5 ml-1 sm:ml-2 pl-1.5 sm:pl-2 border-l border-gray-200 dark:border-gray-700 overflow-x-auto no-scrollbar shrink-0 max-w-[140px] sm:max-w-xs md:max-w-sm" x-data="{ addingTag: false }">
                        @foreach($tags as $index => $tag)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-lg text-[10px] font-bold bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/60 shadow-2xs group shrink-0">
                                #{{ is_string($tag) ? $tag : '' }}
                                <button type="button" wire:click="removeTag({{ $index }})" class="ml-1 text-indigo-400 hover:text-red-500 transition-colors cursor-pointer" title="Hapus tag">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </span>
                        @endforeach

                        <!-- Inline Add Tag Button / Input in Header -->
                        <template x-if="!addingTag">
                            <button type="button" 
                                    @click="addingTag = true; $nextTick(() => { if ($refs.headerTagInput) $refs.headerTagInput.focus() })"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded-lg text-[10px] font-bold text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all border border-dashed border-gray-300 dark:border-gray-700 cursor-pointer shrink-0"
                                    title="Tambah Tag">
                                <span class="mr-0.5 text-indigo-500 font-black">+</span> Tag
                            </button>
                        </template>
                        <template x-if="addingTag">
                            <div class="inline-flex items-center shrink-0">
                                <input x-ref="headerTagInput" 
                                       type="text" 
                                       wire:model="newTag" 
                                       @keydown.enter.prevent="$wire.addTag(); addingTag = false;" 
                                       @keydown.escape="addingTag = false"
                                       @blur="setTimeout(() => { if ($wire.newTag && $wire.newTag.trim()) { $wire.addTag(); } addingTag = false; }, 200)"
                                       placeholder="Tag..." 
                                       class="px-1.5 py-0.5 text-[10px] font-semibold bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-600 rounded-lg focus:ring-1 focus:ring-indigo-500 text-gray-900 dark:text-white placeholder-gray-400 w-16 sm:w-20 outline-none shadow-xs">
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Right Header Actions (Hick's Law Optimized: High-frequency buttons + [...] More Options) -->
                <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                    <!-- Font Style Switcher Dropdown (Sans / Serif / Mono) -->
                    <div class="relative" x-data="{ openFontMenu: false }">
                        <button type="button" 
                                @click="openFontMenu = !openFontMenu"
                                @click.away="openFontMenu = false"
                                class="px-2 py-1 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all flex items-center gap-1 cursor-pointer select-none"
                                title="Pilihan Tipografi / Font">
                            <span class="font-serif font-black text-sm leading-none">Aa</span>
                            <span class="hidden xl:inline text-[11px] capitalize" x-text="fontStyle"></span>
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="openFontMenu" 
                             x-transition 
                             style="display: none;" 
                             class="absolute right-0 mt-1.5 w-36 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 z-50 space-y-1">
                            <button type="button" @click="setFontStyle('sans'); openFontMenu = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700/60 font-sans cursor-pointer select-none" :class="fontStyle === 'sans' ? 'text-indigo-600 dark:text-indigo-400 font-black bg-indigo-50 dark:bg-indigo-950/40' : 'text-gray-700 dark:text-gray-300'">
                                <span>Default (Sans)</span>
                                <span x-show="fontStyle === 'sans'">✓</span>
                            </button>
                            <button type="button" @click="setFontStyle('serif'); openFontMenu = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700/60 font-serif cursor-pointer select-none" :class="fontStyle === 'serif' ? 'text-indigo-600 dark:text-indigo-400 font-black bg-indigo-50 dark:bg-indigo-950/40' : 'text-gray-700 dark:text-gray-300'">
                                <span>Buku (Serif)</span>
                                <span x-show="fontStyle === 'serif'">✓</span>
                            </button>
                            <button type="button" @click="setFontStyle('mono'); openFontMenu = false;" class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700/60 font-mono cursor-pointer select-none" :class="fontStyle === 'mono' ? 'text-indigo-600 dark:text-indigo-400 font-black bg-indigo-50 dark:bg-indigo-950/40' : 'text-gray-700 dark:text-gray-300'">
                                <span>Kode (Mono)</span>
                                <span x-show="fontStyle === 'mono'">✓</span>
                            </button>
                        </div>
                    </div>

                    <!-- Zen / Focus Mode Toggle -->
                    <button type="button" 
                            @click="toggleZenMode()" 
                            :title="zenMode ? 'Keluar Mode Zen (Esc)' : 'Mode Zen / Fokus Tanpa Distraksi'"
                            class="px-2 py-1 rounded-xl border text-xs font-bold flex items-center gap-1 transition-all cursor-pointer select-none"
                            :class="zenMode ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        <span class="hidden sm:inline text-[11px]" x-text="zenMode ? 'Keluar' : 'Zen'"></span>
                    </button>

                    <!-- Right Inspector (Daftar Isi & Info) Toggle -->
                    <button type="button" 
                            @click="toggleRightPanel()" 
                            :title="showRightPanel ? 'Sembunyikan Panel Samping' : 'Buka Daftar Isi & Info'"
                            class="hidden md:inline-flex items-center gap-1 px-2 py-1 rounded-xl border text-xs font-bold transition-all cursor-pointer select-none"
                            :class="showRightPanel ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800' : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        <span class="hidden lg:inline text-[11px]">Daftar Isi</span>
                    </button>

                    <!-- More Options Dropdown (...) for low-frequency actions (Hick's Law) -->
                    <div class="relative" x-data="{ openMoreMenu: false }">
                        <button type="button" 
                                @click="openMoreMenu = !openMoreMenu" 
                                @click.away="openMoreMenu = false"
                                class="p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all flex items-center justify-center cursor-pointer select-none"
                                title="Opsi Dokumen & Tampilan Lainnya">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                        </button>
                        <div x-show="openMoreMenu" 
                             x-transition 
                             style="display: none;" 
                             class="absolute right-0 mt-1.5 w-56 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 p-1.5 z-50 text-xs font-semibold space-y-0.5">
                            
                            <!-- Toggle Width -->
                            <button type="button" 
                                    @click="setLayoutWidth(layoutWidth === 'standard' ? 'full' : 'standard'); openMoreMenu = false;" 
                                    class="w-full text-left px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center justify-between cursor-pointer select-none">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    <span>Lebar Dokumen</span>
                                </span>
                                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 capitalize" x-text="layoutWidth === 'full' ? 'Penuh' : 'Standar'"></span>
                            </button>

                            <!-- Toggle Toolbar -->
                            <button type="button" 
                                    @click="showToolbar = !showToolbar; openMoreMenu = false;" 
                                    class="w-full text-left px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center justify-between cursor-pointer select-none">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                    <span>Bilah Alat (Toolbar)</span>
                                </span>
                                <span class="text-[11px] font-bold" :class="showToolbar ? 'text-emerald-500' : 'text-gray-400'" x-text="showToolbar ? 'Aktif' : 'Sembunyi'"></span>
                            </button>

                            <!-- Toggle Zen as Default Preference -->
                            <button type="button" 
                                    @click="toggleDefaultZenPreference(); openMoreMenu = false;" 
                                    class="w-full text-left px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center justify-between cursor-pointer select-none">
                                <span class="flex items-center gap-2">
                                    <span class="text-amber-500" x-text="defaultZenPreference ? '⭐' : '☆'"></span>
                                    <span>Buka Selalu Mode Zen</span>
                                </span>
                                <span class="text-[11px] font-bold" :class="defaultZenPreference ? 'text-amber-500' : 'text-gray-400'" x-text="defaultZenPreference ? 'Ya' : 'Tidak'"></span>
                            </button>

                            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                            <!-- Copy Clean Text -->
                            <button type="button" 
                                    @click="copyCleanText(); openMoreMenu = false;" 
                                    class="w-full text-left px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2 cursor-pointer select-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                <span>Salin Teks Catatan</span>
                            </button>

                            <!-- Print Note -->
                            <button type="button" 
                                    @click="window.print(); openMoreMenu = false;" 
                                    class="w-full text-left px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2 cursor-pointer select-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span>Cetak / Simpan PDF</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clean Fixed Desktop Toolbar on Top (ala MS Word / Notion) -->
            <div x-show="showToolbar && !zenMode" 
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="hidden sm:flex items-center gap-1 px-4 sm:px-6 py-1.5 bg-gray-50/90 dark:bg-gray-800/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-800 text-gray-700 dark:text-gray-300 shrink-0 overflow-visible z-30">
                
                <!-- Undo / Redo -->
                <div class="flex items-center gap-0.5">
                    <button type="button" @pointerdown.prevent @click="exec('undo')" class="p-1.5 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-all cursor-pointer select-none" title="Urungkan (Undo) - Ctrl+Z">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a5 5 0 015 5v2m-15-7l4-4m-4 4l4 4"/></svg>
                    </button>
                    <button type="button" @pointerdown.prevent @click="exec('redo')" class="p-1.5 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-all cursor-pointer select-none" title="Ulangi (Redo) - Ctrl+Y">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4"/></svg>
                    </button>
                </div>

                <div class="h-3.5 w-px bg-gray-200 dark:bg-gray-700 mx-0.5"></div>

                <!-- Aa Dropdown Menu (Format & Headings) -->
                <div class="relative">
                    <button type="button" @pointerdown.prevent @click="showAaMenu = !showAaMenu" @click.away="showAaMenu = false" class="flex items-center gap-1 px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 font-bold text-xs transition-all cursor-pointer select-none" title="Format Teks & Judul (/)">
                        <span class="text-sm font-serif font-black text-indigo-600 dark:text-indigo-400">Aa</span>
                        <span class="hidden xl:inline">Format</span>
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="showAaMenu" x-transition style="display: none;" class="absolute left-0 top-9 z-50 w-52 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 p-1.5 text-xs font-bold space-y-0.5">
                        <button type="button" @pointerdown.prevent @click="insertHeading('h2')" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-black text-sm flex items-center justify-between cursor-pointer select-none">
                            <span>Judul (H2)</span>
                            <span class="text-[10px] text-gray-400 font-mono">/h2</span>
                        </button>
                        <button type="button" @pointerdown.prevent @click="insertHeading('h3')" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs flex items-center justify-between cursor-pointer select-none">
                            <span>Subjudul (H3)</span>
                            <span class="text-[10px] text-gray-400 font-mono">/h3</span>
                        </button>
                        <button type="button" @pointerdown.prevent @click="insertHeading('p')" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium text-xs flex items-center justify-between cursor-pointer select-none">
                            <span>Paragraf Normal</span>
                            <span class="text-[10px] text-gray-400 font-mono">P</span>
                        </button>
                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                        <button type="button" @pointerdown.prevent @click="exec('bold'); showAaMenu = false" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-black flex items-center justify-between cursor-pointer select-none">
                            <span><b>B</b> Tebal</span>
                            <span class="text-[10px] text-gray-400 font-mono">⌘B / Ctrl+B</span>
                        </button>
                        <button type="button" @pointerdown.prevent @click="exec('italic'); showAaMenu = false" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 italic flex items-center justify-between cursor-pointer select-none">
                            <span><i>I</i> Miring</span>
                            <span class="text-[10px] text-gray-400 font-mono">⌘I / Ctrl+I</span>
                        </button>
                        <button type="button" @pointerdown.prevent @click="exec('strikeThrough'); showAaMenu = false" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 line-through flex items-center justify-between cursor-pointer select-none">
                            <span><s>S</s> Coretan</span>
                            <span class="text-[10px] text-gray-400 font-mono">⌘⇧X / Alt+⇧+5</span>
                        </button>
                        <button type="button" @pointerdown.prevent @click="toggleHighlight()" class="w-full text-left px-2.5 py-1.5 rounded-xl hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-700 dark:text-amber-300 font-bold flex items-center justify-between cursor-pointer select-none">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span>Stabilo Highlight</span>
                            </span>
                            <span class="text-[10px] text-amber-500 font-mono">⌘⇧H / Alt+H</span>
                        </button>
                    </div>
                </div>

                <!-- Quick Inline Format Buttons -->
                <button type="button" @pointerdown.prevent @click="exec('bold')" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 font-black text-xs cursor-pointer select-none" title="Huruf Tebal (⌘B / Ctrl+B)">B</button>
                <button type="button" @pointerdown.prevent @click="exec('italic')" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 italic font-serif text-xs cursor-pointer select-none" title="Huruf Miring (⌘I / Ctrl+I)">I</button>
                <button type="button" @pointerdown.prevent @click="exec('strikeThrough')" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 line-through text-xs cursor-pointer select-none" title="Coret Teks (⌘⇧X / Alt+Shift+5)">S</button>
                <button type="button" @pointerdown.prevent @click="toggleHighlight()" class="p-1.5 rounded-xl hover:bg-amber-100 dark:hover:bg-amber-950/40 text-amber-600 dark:text-amber-400 text-xs font-bold cursor-pointer select-none flex items-center justify-center" title="Stabilo Highlight (⌘⇧H / Alt+H)">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button type="button" @pointerdown.prevent @click="openNoteLinkModal()" class="px-2 py-1 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-xs font-bold transition-all cursor-pointer select-none flex items-center gap-1" title="Tautkan ke Catatan Lain (⌘⇧K / Ctrl+Shift+K)">
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span class="hidden xl:inline">Link</span>
                </button>

                <div class="h-3.5 w-px bg-gray-200 dark:bg-gray-700 mx-0.5"></div>

                <!-- Checklist Button -->
                <button type="button" @pointerdown.prevent @click="insertChecklist()" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400 cursor-pointer text-xs font-bold select-none" title="Daftar Centang Checklist (⌘⇧C / Ctrl+Shift+C)">
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="hidden xl:inline">Checklist</span>
                </button>

                <!-- Bullet List Button -->
                <button type="button" @pointerdown.prevent @click="exec('insertUnorderedList')" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1 cursor-pointer text-xs font-bold select-none" title="Daftar Poin Bullet (⌘⇧8 / Ctrl+Shift+8)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    <span class="hidden xl:inline">Poin</span>
                </button>

                <!-- Numbered List Button -->
                <button type="button" @pointerdown.prevent @click="exec('insertOrderedList')" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1 cursor-pointer text-xs font-bold select-none" title="Daftar Nomor 1, 2, 3 (⌘⇧7 / Ctrl+Shift+7)">
                    <span class="text-xs font-mono font-black text-indigo-500">1.</span>
                    <span class="hidden xl:inline">Nomor</span>
                </button>

                <div class="h-3.5 w-px bg-gray-200 dark:bg-gray-700 mx-0.5"></div>

                <!-- Quote Button -->
                <button type="button" @pointerdown.prevent @click="insertQuote()" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all flex items-center gap-1 cursor-pointer text-xs font-bold select-none" title="Kutipan Quote (⌘⇧Q / Ctrl+Shift+Q)">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    <span class="hidden xl:inline">Quote</span>
                </button>

                <!-- Table Grid Builder -->
                <div class="relative">
                    <button type="button" @pointerdown.prevent @click="showTableModal = !showTableModal" @click.away="showTableModal = false" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400 cursor-pointer text-xs font-bold select-none" title="Tabel Grid Dinamis (/table)">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M3 14h18M10 3v18M14 3v18M3 6a3 3 0 013-3H6a3 3 0 01-3-3V6z"></path></svg>
                        <span class="hidden xl:inline">Tabel</span>
                    </button>

                    <div x-show="showTableModal" x-transition style="display: none;" class="absolute left-0 top-9 z-50 w-60 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 p-3 space-y-2.5">
                        <div class="text-[11px] font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M3 14h18M10 3v18M14 3v18M3 6a3 3 0 013-3H6a3 3 0 01-3-3V6z"/></svg>
                            <span>Sisipkan Tabel Grid</span>
                        </div>
                        
                        <!-- Quick Presets -->
                        <div class="space-y-1">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Preset Cepat</span>
                            <div class="grid grid-cols-4 gap-1 text-[11px] font-bold">
                                <button type="button" @pointerdown.prevent="insertTable(2, 2)" class="py-1 px-1 bg-gray-100 hover:bg-indigo-50 dark:bg-gray-700/60 dark:hover:bg-indigo-950/50 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg transition-all text-center cursor-pointer">2×2</button>
                                <button type="button" @pointerdown.prevent="insertTable(3, 3)" class="py-1 px-1 bg-gray-100 hover:bg-indigo-50 dark:bg-gray-700/60 dark:hover:bg-indigo-950/50 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg transition-all text-center cursor-pointer">3×3</button>
                                <button type="button" @pointerdown.prevent="insertTable(4, 3)" class="py-1 px-1 bg-gray-100 hover:bg-indigo-50 dark:bg-gray-700/60 dark:hover:bg-indigo-950/50 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg transition-all text-center cursor-pointer">4×3</button>
                                <button type="button" @pointerdown.prevent="insertTable(5, 4)" class="py-1 px-1 bg-gray-100 hover:bg-indigo-50 dark:bg-gray-700/60 dark:hover:bg-indigo-950/50 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg transition-all text-center cursor-pointer">5×4</button>
                            </div>
                        </div>

                        <!-- Custom Inputs -->
                        <div class="grid grid-cols-2 gap-2 text-xs font-bold pt-1.5 border-t border-gray-100 dark:border-gray-700">
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-0.5">Kolom</label>
                                <input type="number" min="1" max="10" x-model="tableCols" class="w-full px-2 py-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-center font-black text-xs text-gray-800 dark:text-gray-200">
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-0.5">Baris</label>
                                <input type="number" min="1" max="20" x-model="tableRows" class="w-full px-2 py-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-center font-black text-xs text-gray-800 dark:text-gray-200">
                            </div>
                        </div>
                        <button type="button" @pointerdown.prevent @click="insertTable()" class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold shadow-sm transition-all cursor-pointer select-none">
                            + Buat Tabel
                        </button>
                    </div>
                </div>

                <!-- Divider Button -->
                <button type="button" @pointerdown.prevent @click="insertDivider()" class="px-2 py-1 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition-all text-xs font-black cursor-pointer select-none flex items-center justify-center" title="Garis Pemisah Divider (/divider)">
                    <span class="w-3.5 h-0.5 bg-gray-400 dark:bg-gray-500 inline-block rounded-full"></span>
                </button>

                <!-- Timestamp Button -->
                <button type="button" @pointerdown.prevent @click="insertTimestamp()" class="p-1.5 rounded-xl hover:bg-white dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-all text-xs font-bold cursor-pointer select-none flex items-center justify-center" title="Waktu Sekarang Timestamp (/time)">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </button>
            </div>

            <!-- 3-Panel Main Workspace Body (Desktop Modular Layout) -->
            <div class="flex-1 flex flex-row overflow-hidden relative min-h-0">

                <!-- 1. LEFT SIDEBAR: Quick Notes Switcher -->
                <div x-show="showLeftSidebar && !zenMode" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="-translate-x-full opacity-0"
                     x-transition:enter-end="translate-x-0 opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-x-0 opacity-100"
                     x-transition:leave-end="-translate-x-full opacity-0"
                     class="w-64 sm:w-72 md:w-80 shrink-0 border-r border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-850/70 flex flex-col overflow-hidden select-none z-10"
                     style="display: none;">
                    
                    <!-- Sidebar Header & New Note Button -->
                    <div class="p-3 border-b border-gray-100 dark:border-gray-800/80 flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-xs font-black text-gray-800 dark:text-gray-200">
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <span>Catatan Tersimpan</span>
                        </div>
                        <button type="button" 
                                wire:click="createNewNoteFromSidebar" 
                                @click="syncContent();"
                                class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 transition-all shadow-xs cursor-pointer"
                                title="Buat Catatan Baru">
                            <span>+ Baru</span>
                        </button>
                    </div>

                    <!-- Search Notes Filter Input -->
                    <div class="px-3 pt-2.5 pb-2">
                        <div class="relative">
                            <input type="text" 
                                   x-model="leftSearch" 
                                   placeholder="Cari catatan..." 
                                   class="w-full pl-8 pr-3 py-1.5 text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-indigo-500 text-gray-900 dark:text-white placeholder-gray-400 outline-none">
                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    <!-- Note Items List -->
                    <div class="flex-1 overflow-y-auto p-2 space-y-1 hide-scrollbar">
                        @foreach($this->allNotesList as $n)
                            <div x-show="!leftSearch || {{ json_encode(strtolower($n['title'] . ' ' . $n['snippet'])) }}.includes(leftSearch.toLowerCase())"
                                 @click="syncContent(); $wire.switchNote({{ $n['id'] }})"
                                 class="p-2.5 rounded-2xl transition-all cursor-pointer group {{ $editingJournalId == $n['id'] ? 'bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-800/80 shadow-xs ring-1 ring-indigo-500/20' : 'hover:bg-gray-100/80 dark:hover:bg-gray-800/50 border border-transparent' }}">
                                <div class="flex items-start justify-between gap-1">
                                    <h4 class="text-xs font-black truncate flex-1 {{ $editingJournalId == $n['id'] ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-800 dark:text-gray-200' }}">
                                        {{ $n['title'] }}
                                    </h4>
                                    <span class="text-[10px] text-gray-400 shrink-0 font-medium">{{ $n['date'] }}</span>
                                </div>
                                @if($n['snippet'])
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2 mt-0.5 leading-relaxed">
                                        {{ $n['snippet'] }}
                                    </p>
                                @endif
                                @if(!empty($n['tags']))
                                    <div class="flex items-center gap-1 mt-1.5 flex-wrap">
                                        @foreach(array_slice($n['tags'], 0, 2) as $t)
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded-md bg-gray-200/60 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                #{{ $t }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 2. CENTER CANVAS: Modern Seamless Document Workspace with Infinite Height -->
                <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-white dark:bg-gray-900">

                    <!-- Canvas Scroll Viewport -->
                    <div @scroll.passive="onCanvasScroll($event)" 
                         x-ref="canvasViewport"
                         @click="if ($event.target === $refs.canvasViewport || $event.target.id === 'canvas-doc-wrap') focusEditor()"
                         class="flex-1 overflow-y-auto overscroll-contain touch-pan-y py-4 sm:py-8 px-4 sm:px-8 md:px-12 bg-white dark:bg-gray-900 fast-scroll-viewport">
                        
                        <!-- Seamless Document Canvas Container (Unified Background, Infinite Scrolling, No Truncated Borders) -->
                        <div id="canvas-doc-wrap"
                             class="w-full flex flex-col min-h-full pb-60 bg-white dark:bg-gray-900"
                             :class="{
                                 'max-w-4xl mx-auto': layoutWidth === 'standard',
                                 'w-full max-w-none px-2 sm:px-4': layoutWidth === 'full'
                             }">
                            
                            <!-- Document Title Input & Subtle Meta Strip -->
                            <div class="shrink-0 mb-4 border-b border-gray-100 dark:border-gray-800/80 pb-3">
                                <input type="text" 
                                       x-ref="titleInput"
                                       wire:model="title" 
                                       @input="handleTitleInput($event)"
                                       @keydown.enter.prevent="focusEditor()"
                                       @keydown.tab.prevent="focusEditor()"
                                       placeholder="Judul Catatan..." 
                                       class="w-full text-2xl sm:text-4xl font-black text-gray-900 dark:text-white border-0 bg-transparent focus:ring-0 focus:outline-none placeholder-gray-300 dark:placeholder-gray-600 px-0 py-1 tracking-tight leading-tight select-text"
                                       :class="{
                                           'font-serif': fontStyle === 'serif',
                                           'font-mono': fontStyle === 'mono',
                                           'font-sans': fontStyle === 'sans'
                                       }">
                                <div class="flex items-center gap-2.5 text-[11px] text-gray-400 dark:text-gray-500 font-medium select-none pt-1">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span x-text="readingTimeText"></span>
                                    </span>
                                    <span>•</span>
                                    <span x-text="`${wordCount} kata`"></span>
                                    <span>•</span>
                                    <span x-text="`${charCount} karakter`"></span>
                                </div>
                            </div>

                            <!-- Visual Notion Formatting Toolbar & Full Height Canvas -->
                            <div class="flex flex-col flex-1 space-y-3 relative">
                            
                            <!-- Dynamic Table Controls Helper (Sticky Floating Toolbar: Stays visible while scrolling inside tables) -->
                            <div x-show="activeTable" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                                 id="table-controls-bar"
                                 style="display: none;"
                                 class="sticky top-2 z-40 mx-auto w-fit flex items-center gap-1 sm:gap-1.5 p-1.5 sm:p-2 bg-gray-900/95 dark:bg-gray-850/95 text-white backdrop-blur-md rounded-2xl shadow-2xl text-xs font-bold border border-gray-700/90 shrink-0 pointer-events-auto select-none">
                                <span class="text-indigo-400 text-[11px] px-1.5 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M3 14h18M10 3v18M14 3v18M3 6a3 3 0 013-3H6a3 3 0 01-3-3V6z"/></svg>
                                    <span class="hidden sm:inline">Tabel:</span>
                                </span>
                                <button type="button" @mousedown.prevent="addTableRow()" class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 rounded-xl text-indigo-300 hover:text-white transition-all cursor-pointer flex items-center gap-1 text-xs" title="Tambah Baris Baru (Bisa juga tekan Tab di sel paling akhir)">
                                    + Baris
                                </button>
                                <button type="button" @mousedown.prevent="addTableCol()" class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 rounded-xl text-indigo-300 hover:text-white transition-all cursor-pointer flex items-center gap-1 text-xs" title="Tambah Kolom Baru di sebelah kanan sel aktif">
                                    + Kolom
                                </button>
                                <div class="h-3.5 w-px bg-gray-700 mx-0.5"></div>
                                <button type="button" @mousedown.prevent="deleteTableRow()" class="px-2 py-1 bg-gray-800 hover:bg-rose-950/60 text-rose-300 rounded-xl transition-all cursor-pointer flex items-center gap-1 text-xs" title="Hapus Baris Ini">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <span class="hidden sm:inline">Hapus Baris</span>
                                </button>
                                <button type="button" @mousedown.prevent="deleteTableCol()" class="px-2 py-1 bg-gray-800 hover:bg-rose-950/60 text-rose-300 rounded-xl transition-all cursor-pointer flex items-center gap-1 text-xs" title="Hapus Kolom Aktif">
                                    <span class="hidden sm:inline">Hapus Kolom</span>
                                </button>
                                <button type="button" @mousedown.prevent="deleteTable()" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-xl transition-all cursor-pointer flex items-center gap-1 text-xs" title="Hapus Seluruh Tabel">
                                    Hapus Tabel
                                </button>
                                <button type="button" @mousedown.prevent="activeTable = null; activeCell = null" class="p-1 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white transition-all ml-1 cursor-pointer" title="Tutup menu tabel">
                                    ✕
                                </button>
                            </div>

                            <!-- Notion Borderless Canvas Editor (Protected with wire:ignore) -->
                            <div wire:ignore class="w-full flex-1 min-h-[400px] relative">
                                <div x-ref="editor"
                                     contenteditable="true"
                                     @input="handleInput()"
                                     @keydown="handleKeydown($event)"
                                     @keyup="handleKeyup($event); saveSelection()"
                                     @mouseup="saveSelection()"
                                     @click="handleEditorClick($event)"
                                     @mouseover="handleEditorHover($event)"
                                     @contextmenu="handleContextMenu($event)"
                                     @touchstart="handleTouchStart($event)"
                                     @touchmove="handleTouchMove($event)"
                                     @touchend="handleTouchEnd($event)"
                                     @blur="handleBlur()"
                                     @paste="handlePaste($event)"
                                     class="w-full min-h-[400px] outline-none text-base sm:text-lg text-gray-800 dark:text-gray-200 max-w-none focus:outline-none cursor-text py-2 select-text"
                                     :class="{
                                         'font-serif leading-loose': fontStyle === 'serif',
                                         'font-mono text-sm leading-relaxed': fontStyle === 'mono',
                                         'font-sans leading-relaxed': fontStyle === 'sans'
                                     }"
                                     style="-webkit-user-select: text; user-select: text; -webkit-touch-callout: default; touch-action: manipulation;"
                                     data-placeholder="Tuliskan catatanmu di sini..."></div>

                                <!-- Floating Context Menu (Right-Click on Desktop & Hold on Mobile) -->
                                <div x-show="showContextMenu" 
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     @click.outside="closeContextMenu()"
                                     @keydown.escape.window="closeContextMenu()"
                                     :style="`position: fixed; left: ${contextMenuX}px; top: ${contextMenuY}px; z-index: 9999;`"
                                     style="display: none;"
                                     class="w-72 sm:w-80 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200/90 dark:border-gray-700/90 p-2.5 space-y-2 select-none text-gray-800 dark:text-gray-100">

                                    <!-- Header bar -->
                                    <div class="px-2 py-0.5 text-[10px] uppercase tracking-wider font-black text-gray-400 dark:text-gray-500 flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                                        <span class="flex items-center gap-1.5 text-atoma-blue dark:text-atoma-cyan font-black">
                                            <span>✨</span>
                                            <span>{{ __('Alat & Format Cepat') }}</span>
                                        </span>
                                        <button type="button" @click="closeContextMenu()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-0.5 cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <!-- Quick Inline Formatting Row (Bold, Italic, Strikethrough, Underline, Code, Highlight) -->
                                    <div class="flex items-center gap-1 p-1 rounded-xl bg-gray-100/80 dark:bg-gray-800/80 border border-gray-200/70 dark:border-gray-700/60 overflow-x-auto no-scrollbar">
                                        <button type="button" @pointerdown.prevent @click="applyFormat('bold')" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs font-black transition-all text-center cursor-pointer active:scale-95" title="Tebal (Ctrl+B)">
                                            B
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="applyFormat('italic')" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs font-serif italic font-bold transition-all text-center cursor-pointer active:scale-95" title="Miring (Ctrl+I)">
                                            I
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="applyFormat('strikeThrough')" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs line-through font-bold transition-all text-center cursor-pointer active:scale-95" title="Coret">
                                            S
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="applyFormat('underline')" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs underline font-bold transition-all text-center cursor-pointer active:scale-95" title="Garis Bawah (Ctrl+U)">
                                            U
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="applyCode()" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs font-mono font-bold transition-all text-center cursor-pointer active:scale-95" title="Kode Singkat">
                                            &lt;/&gt;
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="applyHighlight()" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs transition-all text-center cursor-pointer active:scale-95" title="Sorotan Kuning">
                                            🖍️
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="closeContextMenu(); openNoteLinkModal();" class="flex-1 py-1 px-1.5 rounded-lg hover:bg-white dark:hover:bg-gray-700 text-xs text-blue-600 dark:text-blue-400 font-bold transition-all text-center cursor-pointer active:scale-95" title="Tautkan ke Catatan">
                                            🔗
                                        </button>
                                    </div>

                                    <!-- Quick Blocks Grid -->
                                    <div class="grid grid-cols-2 gap-1 pt-0.5 text-xs">
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('p')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">¶</span>
                                            <span class="font-bold truncate text-[11px]">Teks Normal</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('h1')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">H1</span>
                                            <span class="font-bold truncate text-[11px]">Judul Utama</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('h2')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">H2</span>
                                            <span class="font-bold truncate text-[11px]">Subjudul</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('project')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">🚀</span>
                                            <span class="font-bold truncate text-[11px]">Proyek</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('todo')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 font-black text-xs flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">☑</span>
                                            <span class="font-bold truncate text-[11px]">Checklist</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('bullet')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">•</span>
                                            <span class="font-bold truncate text-[11px]">Daftar Poin</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('num')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-[10px] flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">1.</span>
                                            <span class="font-bold truncate text-[11px]">Daftar Nomor</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('quote')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">”</span>
                                            <span class="font-bold truncate text-[11px]">Kutipan</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('table')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">⊞</span>
                                            <span class="font-bold truncate text-[11px]">Tabel Grid</span>
                                        </button>
                                        <button type="button" @pointerdown.prevent @click="insertBlockFromContext('divider')" class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-gray-800 text-left transition-colors cursor-pointer">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700/70 font-black text-xs flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">—</span>
                                            <span class="font-bold truncate text-[11px]">Pemisah</span>
                                        </button>
                                    </div>

                                    <!-- Quick Footer: Copy & Help -->
                                    <div class="pt-1.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[10px] text-gray-400">
                                        <button type="button" @pointerdown.prevent @click="copyCleanText(); closeContextMenu()" class="hover:text-atoma-blue flex items-center gap-1 cursor-pointer">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                            <span>Salin Catatan</span>
                                        </button>
                                        <span>Klik luar / Esc tutup</span>
                                    </div>
                                </div>

                                <!-- Slash Command Palette (Floating Dropdown with Auto-Scroll & Isolation) -->
                                <div x-show="showSlashMenu" 
                                     x-ref="slashDropdownList"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                     @wheel.stop
                                     @touchmove.stop
                                     :style="`position: absolute; top: ${slashMenuTop}px; left: ${slashMenuLeft}px; z-index: 60;`"
                                     style="display: none;"
                                     class="w-72 max-h-72 overflow-y-auto overscroll-contain bg-white dark:bg-gray-850 rounded-2xl shadow-2xl border border-gray-200/90 dark:border-gray-700/90 p-2 space-y-1 pointer-events-auto select-none">
                                    <div class="px-2.5 py-1 text-[10px] uppercase tracking-wider font-extrabold text-gray-400 dark:text-gray-500 flex items-center justify-between border-b border-gray-100 dark:border-gray-750 pb-1.5 mb-1 sticky top-0 bg-white/95 dark:bg-gray-850/95 backdrop-blur-xs z-10">
                                        <span class="flex items-center gap-1.5 text-atoma-blue dark:text-indigo-400">
                                            <span>⚡</span>
                                            <span>Perintah Cepat (Slash /)</span>
                                        </span>
                                        <span class="text-[9px] text-gray-400 font-mono">Esc = Batal</span>
                                    </div>
                                    <template x-for="(cmd, index) in filteredSlashCommands" :key="cmd.id">
                                        <button type="button" 
                                                :id="'slash-item-' + index"
                                                @mousedown.prevent="executeSlashCommand(cmd)" 
                                                @mouseenter="slashIndex = index"
                                                :class="slashIndex === index ? 'bg-indigo-50 dark:bg-indigo-950/60 text-atoma-blue dark:text-indigo-400 font-bold border-indigo-200/60 dark:border-indigo-800/60' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 border-transparent'"
                                                class="w-full text-left px-2.5 py-2 rounded-xl flex items-center gap-2 text-xs transition-colors cursor-pointer border">
                                            <span class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-750 flex items-center justify-center text-xs font-black text-atoma-blue dark:text-indigo-400 shrink-0" x-text="cmd.icon"></span>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-bold truncate text-xs" x-text="cmd.label"></div>
                                                <div class="text-[10px] text-gray-400 truncate" x-text="cmd.desc"></div>
                                            </div>
                                            <span class="text-[10px] font-mono text-gray-400 shrink-0" x-text="'/' + cmd.id"></span>
                                        </button>
                                    </template>
                                </div>

                                <!-- Contextual Floating Bubble Menu (Appears right above text selection) -->
                                <div x-show="showBubbleMenu" 
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                     :style="`position: fixed; left: ${bubblePos.x}px; top: ${bubblePos.y}px; z-index: 9999;`"
                                     style="display: none;"
                                     class="flex items-center gap-0.5 p-1 bg-gray-900/95 dark:bg-gray-800/95 text-white backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-700/80 select-none pointer-events-auto">
                                    
                                    <button type="button" @pointerdown.prevent @click="applyBubbleFormat('bold')" class="p-1.5 hover:bg-white/20 rounded-xl font-black text-xs min-w-[28px] text-center transition-colors cursor-pointer" title="Tebal (⌘B)">
                                        B
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleFormat('italic')" class="p-1.5 hover:bg-white/20 rounded-xl font-serif italic text-xs min-w-[28px] text-center transition-colors cursor-pointer" title="Miring (⌘I)">
                                        I
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleFormat('strikeThrough')" class="p-1.5 hover:bg-white/20 rounded-xl line-through text-xs min-w-[28px] text-center transition-colors cursor-pointer" title="Coret (⌘⇧X)">
                                        S
                                    </button>
                                    
                                    <div class="h-4 w-px bg-white/20 mx-0.5"></div>
                                    
                                    <button type="button" @pointerdown.prevent @click="applyBubbleHighlight()" class="p-1.5 hover:bg-white/20 rounded-xl text-xs transition-colors flex items-center justify-center text-amber-300 cursor-pointer" title="Stabilo (⌘⇧H)">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleNoteLink()" class="p-1.5 hover:bg-white/20 rounded-xl text-xs transition-colors flex items-center justify-center text-blue-300 cursor-pointer" title="Tautkan Catatan (⌘⇧K)">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    </button>
                                    
                                    <div class="h-4 w-px bg-white/20 mx-0.5"></div>
                                    
                                    <button type="button" @pointerdown.prevent @click="applyBubbleHeading('p')" class="px-2 py-1 hover:bg-white/20 rounded-xl font-bold text-[11px] text-gray-200 transition-colors cursor-pointer" title="Ubah ke Teks Normal (Paragraf)">
                                        ¶ P
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleHeading('h2')" class="px-2 py-1 hover:bg-white/20 rounded-xl font-black text-[11px] text-indigo-300 transition-colors cursor-pointer" title="Heading H2 (Klik lagi untuk batal / Normal)">
                                        H2
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleHeading('h3')" class="px-2 py-1 hover:bg-white/20 rounded-xl font-bold text-[11px] text-indigo-300 transition-colors cursor-pointer" title="Subjudul H3 (Klik lagi untuk batal / Normal)">
                                        H3
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleChecklist()" class="p-1.5 hover:bg-white/20 rounded-xl text-emerald-300 text-xs transition-colors cursor-pointer" title="Checklist">
                                        ☑
                                    </button>
                                    <button type="button" @pointerdown.prevent @click="applyBubbleQuote()" class="p-1.5 hover:bg-white/20 rounded-xl text-xs font-serif transition-colors cursor-pointer" title="Kutipan">
                                        ”
                                    </button>
                                </div>
                            </div>

                            <!-- Mobile Sticky Bottom Toolbar (Thumb-Friendly on Phones) -->
                            <div x-show="showToolbar && !zenMode" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="translate-y-full opacity-0"
                                 x-transition:enter-end="translate-y-0 opacity-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="translate-y-0 opacity-100"
                                 x-transition:leave-end="translate-y-full opacity-0"
                                 class="sm:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border-t border-gray-200 dark:border-gray-800 px-3 py-2 flex items-center gap-2 overflow-x-auto shadow-2xl">
                                
                                <!-- Undo / Redo -->
                                <button type="button" @pointerdown.prevent @click="exec('undo')" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 active:scale-95 shrink-0 select-none" title="Undo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a5 5 0 015 5v2m-15-7l4-4m-4 4l4 4"/></svg>
                                </button>
                                <button type="button" @pointerdown.prevent @click="exec('redo')" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 active:scale-95 shrink-0 select-none" title="Redo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4"/></svg>
                                </button>

                                <div class="h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0"></div>

                                <!-- Quick Formats -->
                                <button type="button" @pointerdown.prevent @click="insertHeading('h2')" class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 font-black text-xs text-gray-800 dark:text-gray-200 shrink-0 select-none">H2</button>
                                <button type="button" @pointerdown.prevent @click="insertHeading('h3')" class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 font-bold text-xs text-gray-800 dark:text-gray-200 shrink-0 select-none">H3</button>
                                <button type="button" @pointerdown.prevent @click="exec('bold')" class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 font-black text-xs text-gray-800 dark:text-gray-200 shrink-0 select-none">B</button>
                                <button type="button" @pointerdown.prevent @click="exec('italic')" class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 italic font-serif text-xs text-gray-800 dark:text-gray-200 shrink-0 select-none">I</button>
                                <button type="button" @pointerdown.prevent @click="exec('strikeThrough')" class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 line-through text-xs text-gray-800 dark:text-gray-200 shrink-0 select-none">S</button>
                                <button type="button" @pointerdown.prevent @click="toggleHighlight()" class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 text-xs font-bold shrink-0 select-none flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button type="button" @pointerdown.prevent @click="openNoteLinkModal()" class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-xs font-bold shrink-0 select-none flex items-center justify-center" title="Tautkan Catatan">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </button>

                                <div class="h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0"></div>

                                <!-- Lists & Blocks -->
                                <button type="button" @pointerdown.prevent @click="insertChecklist()" class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-xs font-bold flex items-center gap-1 shrink-0 select-none">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Checklist</span>
                                </button>
                                <button type="button" @pointerdown.prevent @click="exec('insertUnorderedList')" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 shrink-0 select-none" title="Bullet List">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                                </button>
                                <button type="button" @pointerdown.prevent @click="exec('insertOrderedList')" class="px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 font-mono font-bold text-xs text-indigo-600 dark:text-indigo-400 shrink-0 select-none" title="Numbered List">
                                    1. 2.
                                </button>
                                <button type="button" @pointerdown.prevent @click="insertQuote()" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs shrink-0 select-none flex items-center justify-center" title="Quote">
                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                </button>
                                <button type="button" @pointerdown.prevent @click="showTableModal = !showTableModal" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs shrink-0 select-none flex items-center justify-center" title="Table">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M3 14h18M10 3v18M14 3v18M3 6a3 3 0 013-3H6a3 3 0 01-3-3V6z"/></svg>
                                </button>
                                <button type="button" @pointerdown.prevent @click="insertTimestamp()" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs shrink-0 select-none flex items-center justify-center" title="Timestamp">
                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Floating Exit Button for Zen Mode & Default Indicator -->
                    <div x-show="zenMode" 
                         x-transition 
                         style="display: none;" 
                         class="absolute top-4 right-6 z-40 flex items-center gap-2">
                        <button type="button" 
                                @click="toggleDefaultZenPreference()" 
                                :class="defaultZenPreference ? 'bg-amber-500/20 text-amber-300 border-amber-400/40' : 'bg-white/10 text-white/70 border-white/10 hover:text-white'"
                                class="px-2.5 py-1.5 rounded-full backdrop-blur-md shadow-xl text-xs font-bold border flex items-center gap-1.5 transition-all cursor-pointer select-none"
                                :title="defaultZenPreference ? 'Mode Zen aktif otomatis sebagai default (klik untuk matikan default)' : 'Jadikan Mode Zen sebagai default otomatis'">
                            <span x-text="defaultZenPreference ? '⭐ Zen Default' : '☆ Jadikan Default'"></span>
                        </button>
                        <button type="button" 
                                @click="toggleZenMode()" 
                                class="px-3.5 py-1.5 rounded-full bg-gray-900/85 hover:bg-gray-900 text-white dark:bg-gray-800/95 dark:hover:bg-gray-700 backdrop-blur-md shadow-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer select-none">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Keluar Zen</span>
                            <kbd class="text-[10px] font-mono bg-white/20 px-1 py-0.5 rounded">Esc</kbd>
                        </button>
                    </div>
                </div>

                <!-- 3. RIGHT INSPECTOR PANEL: Dynamic Outline & Smart ATOMA Actions -->
                <div x-show="showRightPanel && !zenMode" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="translate-x-full opacity-0"
                     x-transition:enter-end="translate-x-0 opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-x-0 opacity-100"
                     x-transition:leave-end="translate-x-full opacity-0"
                     class="w-64 sm:w-72 md:w-80 shrink-0 border-l border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-850/50 flex flex-col overflow-y-auto select-none z-10 hide-scrollbar"
                     style="display: none;">
                     
                     <!-- Section 1: DAFTAR ISI (OUTLINE / BAB) -->
                     <div class="p-4 border-b border-gray-100 dark:border-gray-800/80">
                         <div class="flex items-center justify-between mb-3">
                             <div class="flex items-center gap-2 text-xs font-black text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                                 <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                 <span>Daftar Isi</span>
                             </div>
                             <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-200/70 dark:bg-gray-700 text-gray-600 dark:text-gray-300" x-text="outline.length"></span>
                         </div>

                         <div class="space-y-1">
                             <template x-for="item in outline" :key="item.id">
                                 <button type="button" 
                                         @click="scrollToHeading(item.id)" 
                                         class="w-full text-left px-2.5 py-1.5 rounded-xl text-xs transition-all cursor-pointer flex items-center gap-1.5 group"
                                         :class="{
                                             'font-black text-indigo-600 dark:text-indigo-400 bg-indigo-50/70 dark:bg-indigo-950/50': activeHeadingId === item.id,
                                             'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800': activeHeadingId !== item.id,
                                             'pl-2 font-bold': item.level === 1,
                                             'pl-4 text-[11px] font-semibold': item.level === 2,
                                             'pl-6 text-[11px] text-gray-500': item.level >= 3
                                         }">
                                     <span class="w-1.5 h-1.5 rounded-full shrink-0 group-hover:scale-125 transition-transform"
                                           :class="activeHeadingId === item.id ? 'bg-indigo-600 dark:bg-indigo-400' : 'bg-gray-300 dark:bg-gray-600'"></span>
                                     <span class="truncate" x-text="item.text"></span>
                                 </button>
                             </template>

                             <div x-show="outline.length === 0" class="py-4 text-center">
                                 <p class="text-[11px] text-gray-400 dark:text-gray-500 leading-relaxed">
                                     Belum ada judul bab atau heading.<br>
                                     Gunakan format <kbd class="px-1 py-0.5 text-[9px] bg-gray-200 dark:bg-gray-700 rounded font-mono">/h2</kbd> atau tulis <em>"Bab 1"</em> untuk membuat daftar isi otomatis.
                                 </p>
                             </div>
                         </div>
                     </div>

                     <!-- Section 2: AKSI PINTAR ATOMA -->
                     <div class="p-4 border-b border-gray-100 dark:border-gray-800/80">
                         <div class="flex items-center gap-2 text-xs font-black text-gray-800 dark:text-gray-200 uppercase tracking-wider mb-2.5">
                             <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                             <span>Aksi Catatan</span>
                         </div>

                         <div class="space-y-2">
                             @if($editingJournalId)
                                 <!-- Ekstrak Flashcard Button -->
                                 <button type="button" 
                                         wire:click="extractFlashcardsFromNote({{ $editingJournalId }})"
                                         class="w-full px-3 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100/80 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800/80 text-indigo-700 dark:text-indigo-300 text-xs font-bold flex items-center justify-between transition-all shadow-2xs cursor-pointer group">
                                     <div class="flex items-center gap-2">
                                         <svg class="w-4 h-4 text-indigo-500 shrink-0 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                         <div class="text-left">
                                             <div>Ekstrak Flashcard</div>
                                             <div class="text-[10px] text-indigo-500 dark:text-indigo-400 font-normal">Spaced Repetition</div>
                                         </div>
                                     </div>
                                     <svg class="w-4 h-4 text-indigo-400 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                 </button>
                             @endif

                             <!-- Salin Teks Bersih Button -->
                             <button type="button" 
                                     @click="copyCleanText()" 
                                     class="w-full px-3 py-2 rounded-xl bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-750 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold flex items-center justify-between transition-all shadow-2xs cursor-pointer group">
                                 <div class="flex items-center gap-2">
                                     <svg class="w-4 h-4 text-gray-500 shrink-0 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                     <span>Salin Teks Dokumen</span>
                                 </div>
                                 <svg class="w-4 h-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                             </button>
                         </div>
                     </div>

                     <!-- Section 3: INFORMASI DOKUMEN -->
                     <div class="p-4">
                         <div class="flex items-center gap-2 text-xs font-black text-gray-800 dark:text-gray-200 uppercase tracking-wider mb-2.5">
                             <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                             <span>Informasi</span>
                         </div>

                         <div class="space-y-2 text-xs text-gray-500 dark:text-gray-400">
                             <div class="flex justify-between items-center py-1 border-b border-gray-100 dark:border-gray-800/60">
                                 <span>Status:</span>
                                 <span class="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                     <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                     Tersimpan Otomatis
                                 </span>
                             </div>
                             @if($editingJournalId)
                                 <div class="flex justify-between items-center py-1 border-b border-gray-100 dark:border-gray-800/60">
                                     <span>ID Catatan:</span>
                                     <span class="font-mono text-gray-600 dark:text-gray-300">#{{ $editingJournalId }}</span>
                                 </div>
                             @endif
                         </div>
                     </div>
                </div>
            </div>

            <!-- 1. Note Link Picker Modal -->
            <div x-show="showNoteLinkModal" 
                 x-transition.opacity
                 style="display: none;"
                 class="fixed inset-0 z-[60] bg-gray-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
                <div @click.outside="showNoteLinkModal = false"
                     @keydown.escape.stop="showNoteLinkModal = false"
                     class="bg-white dark:bg-gray-850 rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-md overflow-hidden flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-150">
                    
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold">
                                🔗
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-gray-900 dark:text-white">Hubungkan ke Catatan</h3>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">Pilih catatan yang ingin ditautkan</p>
                            </div>
                        </div>
                        <button type="button" @click="showNoteLinkModal = false" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="p-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                        <div class="relative">
                            <input x-ref="noteLinkSearchInput" 
                                   type="text" 
                                   x-model="noteLinkSearch" 
                                   placeholder="Ketik judul catatan..." 
                                   class="w-full pl-8 pr-3 py-2 text-xs bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-white placeholder-gray-400 outline-none">
                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    <!-- Notes List -->
                    <div class="flex-1 overflow-y-auto p-2 space-y-1 max-h-72">
                        <template x-for="n in filteredNotesForLinking" :key="n.id">
                            <div @click="insertNoteLink(n.id, n.title)" 
                                 class="p-2.5 rounded-2xl hover:bg-blue-50/70 dark:hover:bg-blue-950/40 border border-transparent hover:border-blue-200 dark:hover:border-blue-800/60 transition-all cursor-pointer group flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-blue-500 text-xs">📄</span>
                                        <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200 group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate" x-text="n.title"></h4>
                                    </div>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 line-clamp-1 mt-0.5" x-text="n.snippet || 'Tidak ada teks pratinjau'"></p>
                                </div>
                                <span class="text-[10px] text-gray-400 shrink-0 font-medium" x-text="n.date"></span>
                            </div>
                        </template>
                        <div x-show="filteredNotesForLinking.length === 0" class="p-6 text-center text-xs text-gray-400">
                            Tidak ada catatan yang cocok
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Connected Note Preview Popup (Mini Note Viewport with Block Architecture & Dragging) -->
            <div x-show="showNotePreviewPopup" 
                 x-ref="miniNotePopup"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                 @click.outside="showNotePreviewPopup = false"
                 @keydown.escape.stop="showNotePreviewPopup = false"
                 @click.stop
                 :style="`position: fixed; left: ${activeNotePreview.x}px; top: ${activeNotePreview.y}px; z-index: 9999;`"
                 style="display: none;"
                 class="w-[360px] sm:w-[480px] max-w-[calc(100vw-24px)] max-h-[calc(100vh-32px)] flex flex-col bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-800 ring-1 ring-black/10 dark:ring-white/10 text-gray-800 dark:text-gray-100 overflow-hidden">
                
                <!-- Mini Note Header (Draggable Handle) -->
                <div @mousedown="startDragMiniNote($event)"
                     @touchstart.passive="startDragMiniNote($event)"
                     class="px-4 py-3 sm:px-4.5 sm:py-3.5 border-b border-gray-100 dark:border-gray-800 bg-gray-50/90 dark:bg-gray-800/60 shrink-0 cursor-grab active:cursor-grabbing select-none group">
                    
                    <!-- Drag Grip Pill Visual Indicator -->
                    <div class="w-10 h-1 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto -mt-1 mb-2 opacity-60 group-hover:opacity-100 group-active:bg-blue-500 transition-all"></div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold shrink-0 border border-blue-200/60 dark:border-blue-800/60">
                                📝
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300">
                                        Mini Note
                                    </span>
                                    <span class="text-[9px] text-gray-400 dark:text-gray-500 font-medium px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 hidden sm:inline-flex items-center gap-1">
                                        <span>⠿</span> Drag
                                    </span>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium truncate" x-show="activeNotePreview.date" x-text="'Diperbarui ' + activeNotePreview.date"></span>
                                </div>
                                <h4 class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight" x-text="activeNotePreview.title"></h4>
                            </div>
                        </div>
                        <button type="button" 
                                @click="showNotePreviewPopup = false" 
                                title="Tutup (Esc)"
                                class="w-7 h-7 rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200/60 dark:hover:bg-gray-800 transition-colors cursor-pointer shrink-0">
                            ✕
                        </button>
                    </div>

                    <!-- Tags list if any -->
                    <template x-if="activeNotePreview.tags && activeNotePreview.tags.length > 0">
                        <div class="flex flex-wrap items-center gap-1 mt-2 pt-2 border-t border-gray-200/60 dark:border-gray-800/60">
                            <template x-for="tag in activeNotePreview.tags" :key="tag">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300" x-text="'#' + tag"></span>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Mini Note Scrollable Canvas -->
                <div x-ref="miniNoteScrollContainer"
                     class="p-4 sm:p-5 overflow-y-auto overscroll-contain flex-1 max-h-[min(380px,calc(100vh-170px))] bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 select-text touch-pan-y focus:outline-none fast-scroll-viewport">
                    
                    <!-- Loading Shimmer State -->
                    <div x-show="activeNotePreview.isLoading" class="space-y-3 py-1.5 animate-pulse">
                        <div class="h-3.5 bg-gray-200 dark:bg-gray-800 rounded-lg w-3/4"></div>
                        <div class="h-3 bg-gray-200/80 dark:bg-gray-800/80 rounded-lg w-full"></div>
                        <div class="h-3 bg-gray-200/80 dark:bg-gray-800/80 rounded-lg w-5/6"></div>
                        <div class="h-3 bg-gray-200/70 dark:bg-gray-800/70 rounded-lg w-2/3"></div>
                        <div class="pt-2 space-y-2">
                            <div class="h-3 bg-gray-200/80 dark:bg-gray-800/80 rounded-lg w-4/5"></div>
                            <div class="h-3 bg-gray-200/60 dark:bg-gray-800/60 rounded-lg w-1/2"></div>
                        </div>
                    </div>

                    <!-- Rendered Rich Note Content (Full Content Immediately Ready) -->
                    <div x-show="!activeNotePreview.isLoading"
                         class="prose prose-sm dark:prose-invert max-w-none text-xs sm:text-sm leading-relaxed space-y-2 [&_p]:my-1.5 [&_h1]:text-base [&_h1]:font-black [&_h2]:text-sm [&_h2]:font-bold [&_h3]:text-xs [&_h3]:font-bold [&_ul]:my-1.5 [&_ul]:list-disc [&_ul]:pl-4 [&_ol]:my-1.5 [&_ol]:list-decimal [&_ol]:pl-4 [&_li]:my-0.5 [&_blockquote]:border-l-2 [&_blockquote]:border-blue-500 [&_blockquote]:pl-3 [&_blockquote]:italic [&_table]:text-[11px] [&_table]:my-2 [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-gray-200 dark:[&_th]:border-gray-700 [&_th]:p-1.5 [&_th]:bg-gray-50 dark:[&_th]:bg-gray-800 [&_td]:border [&_td]:border-gray-200 dark:[&_td]:border-gray-800 [&_td]:p-1.5"
                         x-html="activeNotePreview.content_html || activeNotePreview.snippet || '<p class=\'text-gray-400 italic\'>Belum ada isi catatan.</p>'">
                    </div>
                </div>

                <!-- Mini Note Footer / Actions -->
                <div class="px-4 py-2.5 sm:px-4.5 sm:py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-800/40 flex items-center justify-between gap-2 shrink-0">
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="unlinkNote(activeNotePreview)" 
                                class="px-2.5 py-1.5 text-xs font-bold text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-all cursor-pointer flex items-center gap-1">
                            <span>⛓️‍💥</span>
                            <span>Lepas Tautan</span>
                        </button>
                        <span class="text-[11px] text-gray-400 hidden sm:inline" x-show="activeNotePreview.word_count" x-text="activeNotePreview.word_count + ' kata'"></span>
                    </div>
                    <button type="button" 
                            @click="openConnectedNote(activeNotePreview.id)" 
                            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 active:scale-95 text-white rounded-xl text-xs font-bold shadow-md hover:shadow-blue-500/25 transition-all cursor-pointer flex items-center gap-1.5">
                        <span>Buka Catatan</span>
                        <span>↗</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create / Edit Finance Modal -->
    <div x-show="$wire.showFinanceModal" style="display: none;" class="relative z-50">
        <div x-show="$wire.showFinanceModal" x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div x-show="$wire.showFinanceModal" 
                     x-transition 
                     class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-t-[2rem] sm:rounded-[2rem] shadow-2xl flex flex-col relative overflow-hidden">
                    
                    <!-- Header -->
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 dark:border-gray-700/50">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                            <img src="/images/icons/roi-finance.png" alt="Keuangan" class="w-6 h-6 object-contain inline-block shrink-0">
                            <span>{{ $editingFinanceId ? 'Edit Transaksi Keuangan' : 'Catat Transaksi Keuangan' }}</span>
                        </h3>
                        <button @click="$wire.showFinanceModal = false; $wire.editingFinanceId = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveFinance" class="p-6">
                        <!-- Type Selector (Income / Expense / Savings) -->
                        <div class="flex bg-gray-100 dark:bg-gray-900 rounded-2xl p-1 mb-6 relative">
                            <button type="button" wire:click="$set('f_type', 'income')" class="flex-1 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all z-10 {{ $f_type === 'income' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Pemasukan</button>
                            <button type="button" wire:click="$set('f_type', 'expense')" class="flex-1 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all z-10 {{ $f_type === 'expense' ? 'bg-white dark:bg-gray-800 text-rose-600 dark:text-rose-400 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Pengeluaran</button>
                            <button type="button" wire:click="$set('f_type', 'savings')" class="flex-1 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all z-10 {{ $f_type === 'savings' ? 'bg-white dark:bg-gray-800 text-violet-600 dark:text-violet-400 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Tabungan</button>
                        </div>

                        <!-- Date Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Tanggal</label>
                            <input type="date" wire:model="f_date" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            @error('f_date') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Amount Input -->
                        <div class="mb-6" x-data="{
                            displayValue: '',
                            init() {
                                this.sync();
                                this.$watch('$wire.f_amount', () => this.sync());
                            },
                            sync() {
                                let val = $wire.f_amount ? String($wire.f_amount).replace(/\D/g, '') : '';
                                this.displayValue = val ? val.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
                            },
                            updateValue(e) {
                                let raw = e.target.value.replace(/\D/g, '');
                                $wire.f_amount = raw;
                                this.displayValue = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
                            }
                        }">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Jumlah</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500 font-black text-lg">Rp</span>
                                <input type="text" inputmode="numeric" x-model="displayValue" @input="updateValue" class="block w-full pl-14 pr-4 py-3.5 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl text-2xl font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="0">
                            </div>
                            @error('f_amount') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Category -->
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Kategori</label>
                            <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto pr-1">
                                @foreach($categories[$f_type] as $cat)
                                    @php
                                        $isActiveCat = $f_category === $cat;
                                        $catActiveClass = $f_type === 'income' 
                                            ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-950/50 dark:border-emerald-700 dark:text-emerald-300' 
                                            : ($f_type === 'savings' 
                                                ? 'bg-violet-50 border-violet-300 text-violet-700 dark:bg-violet-950/50 dark:border-violet-700 dark:text-violet-300' 
                                                : 'bg-rose-50 border-rose-300 text-rose-700 dark:bg-rose-950/50 dark:border-rose-700 dark:text-rose-300');
                                        $catMeta = $this->getCategoryMeta($cat, $f_type);
                                    @endphp
                                    <button type="button" wire:click="$set('f_category', '{{ $cat }}')" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 {{ $isActiveCat ? $catActiveClass . ' shadow-xs' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400' }}">
                                        <span class="w-4 h-4 flex items-center justify-center shrink-0">{!! str_replace('w-5 h-5', 'w-3.5 h-3.5', $catMeta['icon']) !!}</span>
                                        <span>{{ $cat }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @error('f_category') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Description -->
                        <div class="mb-8">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Catatan (Opsional)</label>
                            <input type="text" wire:model="f_description" class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Misal: Sarapan pagi, gaji bulanan, tabungan haji">
                        </div>

                        <div class="flex items-center gap-3">
                            @if($editingFinanceId)
                                <button type="button" @click="$wire.showFinanceModal = false; $wire.editingFinanceId = null" class="flex-1 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all">
                                    Batal
                                </button>
                            @endif
                            <button type="submit" class="flex-1 py-3.5 rounded-xl text-white font-bold text-sm sm:text-base shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all {{ $f_type === 'income' ? 'bg-emerald-600 hover:bg-emerald-500' : ($f_type === 'savings' ? 'bg-violet-600 hover:bg-violet-500' : 'bg-rose-600 hover:bg-rose-500') }}">
                                {{ $editingFinanceId ? 'Simpan Perubahan' : 'Simpan Transaksi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Budget Modal -->
    <div x-show="$wire.showBudgetModal" style="display: none;" class="relative z-50">
        <div x-show="$wire.showBudgetModal" x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div x-show="$wire.showBudgetModal" 
                     x-transition 
                     class="w-full max-w-md bg-white dark:bg-gray-800 rounded-t-[2rem] sm:rounded-[2rem] shadow-2xl flex flex-col relative overflow-hidden">
                    
                    <!-- Header -->
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 dark:border-gray-700/50">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Atur Anggaran Bulanan</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Batas maksimal pengeluaran</p>
                            </div>
                        </div>
                        <button @click="$wire.showBudgetModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveBudget" class="p-6">
                        <div class="mb-6" x-data="{
                            displayValue: '',
                            init() {
                                this.sync();
                                this.$watch('$wire.monthly_budget_input', () => this.sync());
                            },
                            sync() {
                                let val = $wire.monthly_budget_input ? String($wire.monthly_budget_input).replace(/\D/g, '') : '';
                                this.displayValue = val ? val.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
                            },
                            updateValue(e) {
                                let raw = e.target.value.replace(/\D/g, '');
                                $wire.monthly_budget_input = raw;
                                this.displayValue = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
                            }
                        }">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Batas Pengeluaran Bulanan</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500 font-black text-lg">Rp</span>
                                <input type="text" inputmode="numeric" x-model="displayValue" @input="updateValue" class="block w-full pl-14 pr-4 py-3.5 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl text-2xl font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="0">
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2 font-medium">
                                Kosongkan atau isi 0 jika tidak ingin menggunakan limit anggaran bulanan.
                            </p>
                            @error('monthly_budget_input') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="button" @click="$wire.showBudgetModal = false" class="flex-1 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-bold text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all">
                                Batal
                            </button>
                            <button type="submit" class="flex-1 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg hover:shadow-xl transition-all">
                                Simpan Anggaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        window.notionJournalEditor = function() {
            return {
                showToolbar: true,
                showAaMenu: false,
                showTableModal: false,
                showSlashMenu: false,
                slashFilter: '',
                slashIndex: 0,
                slashMenuTop: 40,
                slashMenuLeft: 16,
                typingSnapshotTimer: null,
                tableRows: 2,
                tableCols: 2,
                activeTable: null,
                activeCell: null,
                selectedMood: @entangle('mood'),
                autosaveStatus: 'idle',
                autosaveTimer: null,
                lastSavedTime: '',
                savedRange: null,

                // Note Linking & Popups
                notesList: @js($this->allNotesList),
                noteCache: {},
                prefetchingNotes: {},
                showNoteLinkModal: false,
                noteLinkSearch: '',
                selectedNoteText: '',
                showNotePreviewPopup: false,
                activeNotePreview: { 
                    id: null, 
                    title: '', 
                    snippet: '', 
                    content_html: '', 
                    blocks: [],
                    renderedBlocksHtml: '',
                    renderedCount: 0,
                    totalBlocks: 0,
                    isHydrating: false,
                    tags: [], 
                    word_count: 0, 
                    date: '', 
                    isLoading: false, 
                    x: 0, 
                    y: 0, 
                    linkEl: null 
                },
                isDraggingMiniNote: false,
                dragStartX: 0,
                dragStartY: 0,
                initialMiniNoteX: 0,
                initialMiniNoteY: 0,

                // History & Undo/Redo Snapshots
                undoStack: [],
                redoStack: [],
                isUndoRedoAction: false,

                // Desktop Layout Enhancements
                showLeftSidebar: window.innerWidth >= 1280 ? (localStorage.getItem('atoma_note_left_sidebar') !== 'false') : false,
                showRightPanel: window.innerWidth >= 1024 ? (localStorage.getItem('atoma_note_right_panel') !== 'false') : false,
                layoutWidth: localStorage.getItem('atoma_note_width') || 'standard',
                fontStyle: localStorage.getItem('atoma_note_font') || 'sans',
                zenMode: localStorage.getItem('atoma_note_default_zen') === 'true',
                defaultZenPreference: localStorage.getItem('atoma_note_default_zen') === 'true',

                // Sticky Header Title Scroll Tracking & Document Stats
                isScrolled: false,
                wordCount: 0,
                charCount: 0,
                readingTimeText: '1 mnt baca',

                // Contextual Floating Bubble Menu (Fitts's Law)
                showBubbleMenu: false,
                bubblePos: { x: 0, y: 0 },

                onCanvasScroll(e) {
                    let scrolled = e.target.scrollTop > 50;
                    if (this.isScrolled !== scrolled) {
                        this.isScrolled = scrolled;
                    }
                    if (this.showBubbleMenu) {
                        this.showBubbleMenu = false;
                    }
                },

                checkBubbleMenu() {
                    let sel = window.getSelection();
                    if (!sel || sel.isCollapsed || !this.$refs.editor || !this.$refs.editor.contains(sel.anchorNode)) {
                        this.showBubbleMenu = false;
                        return;
                    }
                    let text = sel.toString().trim();
                    if (!text) {
                        this.showBubbleMenu = false;
                        return;
                    }
                    let range = sel.getRangeAt(0);
                    let rect = range.getBoundingClientRect();
                    if (!rect || rect.width === 0 || rect.height === 0) {
                        this.showBubbleMenu = false;
                        return;
                    }

                    const menuWidth = 310;
                    const menuHeight = 44;
                    let x = rect.left + (rect.width / 2) - (menuWidth / 2);
                    let y = rect.top - menuHeight - 10;

                    if (x < 10) x = 10;
                    if (x + menuWidth > window.innerWidth - 10) x = window.innerWidth - menuWidth - 10;
                    if (y < 65) {
                        y = rect.bottom + 10;
                    }

                    this.bubblePos = { x: Math.round(x), y: Math.round(y) };
                    this.showBubbleMenu = true;
                },

                applyBubbleFormat(cmd, arg = null) {
                    this.restoreSelection();
                    this.exec(cmd, arg);
                    this.checkBubbleMenu();
                    this.handleInput();
                },

                applyBubbleHighlight() {
                    this.restoreSelection();
                    this.toggleHighlight();
                    this.checkBubbleMenu();
                    this.handleInput();
                },

                applyBubbleHeading(tag) {
                    this.restoreSelection();
                    this.insertHeading(tag);
                    this.checkBubbleMenu();
                    this.handleInput();
                },

                applyBubbleChecklist() {
                    this.restoreSelection();
                    this.insertChecklist();
                    this.showBubbleMenu = false;
                    this.handleInput();
                },

                applyBubbleQuote() {
                    this.restoreSelection();
                    this.insertQuote();
                    this.checkBubbleMenu();
                    this.handleInput();
                },

                get filteredNotesForLinking() {
                    let list = this.notesList || [];
                    let currentId = @js($editingJournalId);
                    let q = (this.noteLinkSearch || '').toLowerCase().trim();
                    return list.filter(n => {
                        if (currentId && String(n.id) === String(currentId)) return false;
                        if (!q) return true;
                        return (n.title || '').toLowerCase().includes(q) || (n.snippet || '').toLowerCase().includes(q);
                    });
                },

                async openNoteLinkModal() {
                    this.saveSelection();
                    let sel = window.getSelection();
                    this.selectedNoteText = (sel && !sel.isCollapsed) ? sel.toString().trim() : '';
                    this.noteLinkSearch = '';

                    // Flush autosave first so any current title/content is persisted immediately
                    if (this.autosaveTimer) {
                        clearTimeout(this.autosaveTimer);
                        this.autosaveTimer = null;
                    }
                    this.triggerAutosave();

                    // Refresh notesList from server so freshly created notes appear with their real title
                    try {
                        let freshNotes = await this.$wire.getNotesListJson();
                        if (Array.isArray(freshNotes)) {
                            this.notesList = freshNotes;
                        }
                    } catch (e) {
                        console.error('Error fetching fresh notes list:', e);
                    }

                    this.showNoteLinkModal = true;
                    this.showBubbleMenu = false;
                    this.$nextTick(() => {
                        if (this.$refs.noteLinkSearchInput) {
                            this.$refs.noteLinkSearchInput.focus();
                        }
                    });
                },

                insertNoteLink(id, title) {
                    this.saveSnapshot();
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let rawText = this.selectedNoteText || title;
                    let text = rawText.replace(/^[🔗\s]+/, '').trim();
                    let safeTitle = this.escapeHtml(title.replace(/^[🔗\s]+/, '').trim());
                    let safeText = this.escapeHtml(text);
                    let html = `<a href="javascript:void(0)" data-note-id="${id}" data-note-title="${safeTitle}" tabindex="-1" class="journal-note-link text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-semibold underline decoration-blue-400/60 underline-offset-2 transition-colors cursor-pointer" contenteditable="false">${safeText}</a>&nbsp;`;
                    
                    document.execCommand('insertHTML', false, html);
                    this.showNoteLinkModal = false;
                    this.saveSnapshot();
                    this.handleInput();
                    this.prefetchLinkedNotes();
                },

                applyBubbleNoteLink() {
                    this.showBubbleMenu = false;
                    this.openNoteLinkModal();
                },

                prefetchLinkedNotes() {
                    if (!this.$refs.editor) return;
                    let links = this.$refs.editor.querySelectorAll('.journal-note-link');
                    links.forEach(link => {
                        link.setAttribute('tabindex', '-1');
                        let id = link.getAttribute('data-note-id');
                        if (id && !this.noteCache[id]) {
                            this.fetchNotePreviewData(id);
                        }
                    });
                    this.$refs.editor.querySelectorAll('a').forEach(a => a.setAttribute('tabindex', '-1'));
                },

                async fetchNotePreviewData(noteId) {
                    if (this.noteCache[noteId]) return this.noteCache[noteId];
                    try {
                        let res = await fetch('/api/journal/preview/' + noteId, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            let data = await res.json();
                            this.noteCache[noteId] = data;
                            return data;
                        }
                    } catch (err) {}
                    try {
                        let data = await this.$wire.getNotePreview(noteId);
                        if (data) {
                            this.noteCache[noteId] = data;
                            return data;
                        }
                    } catch (e) {}
                    return null;
                },

                handleEditorHover(e) {
                    let linkEl = e.target.closest('.journal-note-link');
                    if (linkEl) {
                        let noteId = linkEl.getAttribute('data-note-id');
                        if (noteId && !this.noteCache[noteId] && !this.prefetchingNotes[noteId]) {
                            this.prefetchingNotes[noteId] = true;
                            this.fetchNotePreviewData(noteId).finally(() => {
                                delete this.prefetchingNotes[noteId];
                            });
                        }
                    }
                },

                // --- Notion-inspired Block Architecture & Virtual Windowing ---
                parseNoteBlocks(html) {
                    if (!html || !html.trim()) return [];
                    let temp = document.createElement('div');
                    temp.innerHTML = html;
                    let nodes = Array.from(temp.children);
                    if (nodes.length === 0) {
                        return [{ id: 'blk_0', html: html }];
                    }
                    return nodes.map((node, idx) => ({
                        id: 'blk_' + idx,
                        html: node.outerHTML
                    }));
                },

                initMiniNoteBlocks(contentHtml) {
                    let blocks = this.parseNoteBlocks(contentHtml);
                    this.activeNotePreview.blocks = blocks;
                    this.activeNotePreview.totalBlocks = blocks.length;
                    this.activeNotePreview.isHydrating = false;

                    // Snappy initial chunk: first 30 atomic blocks render in <1ms
                    let initialCount = Math.min(30, blocks.length);
                    let initialBatch = blocks.slice(0, initialCount);
                    this.activeNotePreview.renderedBlocksHtml = initialBatch.map(b => b.html).join('');
                    this.activeNotePreview.renderedCount = initialCount;
                },

                loadNextBlockChunk() {
                    if (!this.activeNotePreview.blocks || this.activeNotePreview.renderedCount >= this.activeNotePreview.totalBlocks || this.activeNotePreview.isHydrating) {
                        return;
                    }
                    this.activeNotePreview.isHydrating = true;
                    requestAnimationFrame(() => {
                        let currentCount = this.activeNotePreview.renderedCount;
                        let nextBatch = this.activeNotePreview.blocks.slice(currentCount, currentCount + 30);
                        this.activeNotePreview.renderedBlocksHtml += nextBatch.map(b => b.html).join('');
                        this.activeNotePreview.renderedCount += nextBatch.length;
                        this.activeNotePreview.isHydrating = false;
                    });
                },

                onMiniNoteScroll(e) {
                    let el = e.target;
                    let remaining = el.scrollHeight - el.scrollTop - el.clientHeight;
                    // Hydrate next chunk when user scrolls within 300px from bottom
                    if (remaining < 300 && this.activeNotePreview.renderedCount < this.activeNotePreview.totalBlocks) {
                        this.loadNextBlockChunk();
                    }
                },

                // --- Viewport Bounds Clamping (Prevents Cut-off on All Screens) ---
                clampMiniNotePosition() {
                    let popupEl = this.$refs.miniNotePopup;
                    let w = popupEl ? popupEl.offsetWidth : 480;
                    let h = popupEl ? popupEl.offsetHeight : 420;
                    let padding = 12;
                    let maxX = Math.max(padding, window.innerWidth - w - padding);
                    let maxY = Math.max(padding, window.innerHeight - h - padding);

                    this.activeNotePreview.x = Math.max(padding, Math.min(maxX, this.activeNotePreview.x));
                    this.activeNotePreview.y = Math.max(padding, Math.min(maxY, this.activeNotePreview.y));
                },

                // --- Draggable Mini Note Card Interaction ---
                startDragMiniNote(e) {
                    if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;

                    this.isDraggingMiniNote = true;
                    let clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    let clientY = e.touches ? e.touches[0].clientY : e.clientY;

                    this.dragStartX = clientX;
                    this.dragStartY = clientY;
                    this.initialMiniNoteX = this.activeNotePreview.x;
                    this.initialMiniNoteY = this.activeNotePreview.y;

                    this._onMiniNoteDragMove = this.onMiniNoteDragMove.bind(this);
                    this._onMiniNoteDragEnd = this.onMiniNoteDragEnd.bind(this);

                    window.addEventListener('mousemove', this._onMiniNoteDragMove);
                    window.addEventListener('mouseup', this._onMiniNoteDragEnd);
                    window.addEventListener('touchmove', this._onMiniNoteDragMove, { passive: false });
                    window.addEventListener('touchend', this._onMiniNoteDragEnd);

                    document.body.classList.add('select-none');
                },

                onMiniNoteDragMove(e) {
                    if (!this.isDraggingMiniNote) return;
                    if (e.cancelable) e.preventDefault();

                    let clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    let clientY = e.touches ? e.touches[0].clientY : e.clientY;

                    let deltaX = clientX - this.dragStartX;
                    let deltaY = clientY - this.dragStartY;

                    let popupEl = this.$refs.miniNotePopup;
                    let w = popupEl ? popupEl.offsetWidth : 480;
                    let h = popupEl ? popupEl.offsetHeight : 420;
                    let padding = 12;
                    let maxX = Math.max(padding, window.innerWidth - w - padding);
                    let maxY = Math.max(padding, window.innerHeight - h - padding);

                    let rawX = this.initialMiniNoteX + deltaX;
                    let rawY = this.initialMiniNoteY + deltaY;

                    this.activeNotePreview.x = Math.max(padding, Math.min(maxX, rawX));
                    this.activeNotePreview.y = Math.max(padding, Math.min(maxY, rawY));
                },

                onMiniNoteDragEnd() {
                    if (!this.isDraggingMiniNote) return;
                    this.isDraggingMiniNote = false;

                    window.removeEventListener('mousemove', this._onMiniNoteDragMove);
                    window.removeEventListener('mouseup', this._onMiniNoteDragEnd);
                    window.removeEventListener('touchmove', this._onMiniNoteDragMove);
                    window.removeEventListener('touchend', this._onMiniNoteDragEnd);

                    document.body.classList.remove('select-none');
                    this.clampMiniNotePosition();
                },

                handleEditorClick(e) {
                    let linkEl = e.target.closest('.journal-note-link');
                    if (linkEl) {
                        e.preventDefault();
                        e.stopPropagation();
                        let noteId = linkEl.getAttribute('data-note-id');
                        let noteTitle = linkEl.getAttribute('data-note-title');
                        let cached = this.noteCache[noteId];
                        let foundInList = (this.notesList || []).find(n => String(n.id) === String(noteId));
                        let rect = linkEl.getBoundingClientRect();
                        
                        let popupWidth = Math.min(480, window.innerWidth - 24);
                        let estimatedHeight = 420;
                        let padding = 12;

                        // Position horizontally: align with rect.left, clamped to window edges with 12px padding
                        let popupX = Math.max(padding, Math.min(window.innerWidth - popupWidth - padding, rect.left));

                        // Position vertically: check where it fits best without any cut-off
                        let popupY;
                        let spaceBelow = window.innerHeight - rect.bottom;
                        let spaceAbove = rect.top;

                        if (spaceBelow >= estimatedHeight + 16) {
                            // Fits comfortably below the link
                            popupY = rect.bottom + 8;
                        } else if (spaceAbove >= estimatedHeight + 16) {
                            // Fits comfortably above the link (prevents cut-off when link is in lower half)
                            popupY = rect.top - estimatedHeight - 8;
                        } else {
                            // Position in available space, strictly bounded within viewport
                            if (spaceBelow >= spaceAbove) {
                                popupY = rect.bottom + 8;
                            } else {
                                popupY = rect.top - estimatedHeight - 8;
                            }
                        }

                        // Strict clamping so popup top is never < 12 and bottom never exceeds viewport
                        let maxY = Math.max(padding, window.innerHeight - estimatedHeight - padding);
                        popupY = Math.max(padding, Math.min(maxY, popupY));

                        this.activeNotePreview = {
                            id: noteId,
                            title: (cached ? cached.title : null) || noteTitle || (foundInList ? foundInList.title : 'Catatan'),
                            snippet: cached ? cached.snippet : (foundInList ? foundInList.snippet : ''),
                            content_html: cached ? cached.content_html : '',
                            blocks: [],
                            renderedBlocksHtml: '',
                            renderedCount: 0,
                            totalBlocks: 0,
                            isHydrating: false,
                            tags: cached ? cached.tags : (foundInList ? foundInList.tags : []),
                            word_count: cached ? cached.word_count : 0,
                            date: cached ? cached.date : (foundInList ? foundInList.date : ''),
                            isLoading: !cached,
                            x: Math.round(popupX),
                            y: Math.round(popupY),
                            linkEl: linkEl
                        };

                        if (cached && cached.content_html) {
                            this.initMiniNoteBlocks(cached.content_html);
                        }

                        this.showNotePreviewPopup = true;
                        this.$nextTick(() => {
                            this.clampMiniNotePosition();
                        });

                        // Lazy load on-demand if not yet in cache
                        if (!cached) {
                            this.fetchNotePreviewData(noteId).then(data => {
                                if (data && this.activeNotePreview && String(this.activeNotePreview.id) === String(noteId)) {
                                    this.activeNotePreview.title = data.title;
                                    this.activeNotePreview.snippet = data.snippet;
                                    this.activeNotePreview.content_html = data.content_html;
                                    this.initMiniNoteBlocks(data.content_html || '');
                                    this.activeNotePreview.tags = data.tags || [];
                                    this.activeNotePreview.word_count = data.word_count || 0;
                                    this.activeNotePreview.date = data.date || '';
                                    this.activeNotePreview.isLoading = false;
                                    this.$nextTick(() => {
                                        this.clampMiniNotePosition();
                                    });
                                }
                            }).catch(() => {
                                if (this.activeNotePreview && String(this.activeNotePreview.id) === String(noteId)) {
                                    this.activeNotePreview.isLoading = false;
                                }
                            });
                        }
                        return;
                    } else {
                        this.showNotePreviewPopup = false;
                    }

                    this.checkTableContext(e);
                    this.saveSelection();
                    this.closeContextMenu();
                },

                openConnectedNote(targetId) {
                    this.showNotePreviewPopup = false;
                    this.syncContent();
                    this.$wire.switchNote(targetId);
                },

                unlinkNote(preview) {
                    if (preview && preview.linkEl) {
                        this.saveSnapshot();
                        let text = preview.linkEl.textContent.replace(/^[🔗\s]+/, '').trim();
                        let textNode = document.createTextNode(text);
                        preview.linkEl.replaceWith(textNode);
                        this.showNotePreviewPopup = false;
                        this.saveSnapshot();
                        this.handleInput();
                    }
                },

                handleEscapeKey() {
                    if (this.showNotePreviewPopup) {
                        this.showNotePreviewPopup = false;
                        return;
                    }
                    if (this.showNoteLinkModal) {
                        this.showNoteLinkModal = false;
                        return;
                    }
                    if (this.showTableModal) {
                        this.showTableModal = false;
                        return;
                    }
                    if (this.showSlashMenu) {
                        this.showSlashMenu = false;
                        return;
                    }
                    if (this.showBubbleMenu) {
                        this.showBubbleMenu = false;
                        return;
                    }
                    if (this.zenMode) {
                        this.toggleZenMode();
                        return;
                    }
                    // Exit note modal with autosave
                    this.syncContent();
                    this.$wire.autoSaveJournal();
                    this.$wire.showCreateModal = false;
                },

                updateStats() {
                    if (!this.$refs.editor) return;
                    let text = this.$refs.editor.textContent || '';
                    let words = text.trim() ? text.trim().split(/\s+/).filter(w => w.length > 0).length : 0;
                    let chars = text.length;
                    this.wordCount = words;
                    this.charCount = chars;
                    let mins = Math.max(1, Math.ceil(words / 200));
                    this.readingTimeText = `${mins} mnt baca`;
                },

                // Floating Context Menu (Right Click & Mobile Long-Press)
                showContextMenu: false,
                contextMenuX: 0,
                contextMenuY: 0,
                touchTimer: null,
                touchStartX: 0,
                touchStartY: 0,

                toggleDefaultZenPreference() {
                    this.defaultZenPreference = !this.defaultZenPreference;
                    localStorage.setItem('atoma_note_default_zen', this.defaultZenPreference);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { 
                            message: this.defaultZenPreference 
                                ? '🧘 Mode Zen disetel otomatis sebagai default saat membuka catatan.' 
                                : 'Mode Zen default dinonaktifkan.', 
                            type: 'info' 
                        }
                    }));
                },

                openContextMenu(e) {
                    this.saveSelection();
                    let clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 100);
                    let clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 100);

                    const menuWidth = 320;
                    const menuHeight = 250;
                    if (clientX + menuWidth > window.innerWidth - 10) {
                        clientX = Math.max(10, window.innerWidth - menuWidth - 15);
                    }
                    if (clientY + menuHeight > window.innerHeight - 10) {
                        clientY = Math.max(10, clientY - menuHeight - 10);
                    }

                    this.contextMenuX = Math.round(clientX);
                    this.contextMenuY = Math.round(clientY);
                    this.showContextMenu = true;
                    this.showSlashMenu = false;
                },

                closeContextMenu() {
                    this.showContextMenu = false;
                },

                handleContextMenu(e) {
                    e.preventDefault();
                    this.openContextMenu(e);
                },

                handleTouchStart(e) {
                    if (!e.touches || e.touches.length !== 1) return;
                    this.touchStartX = e.touches[0].clientX;
                    this.touchStartY = e.touches[0].clientY;
                    clearTimeout(this.touchTimer);
                    this.touchTimer = setTimeout(() => {
                        this.openContextMenu({
                            clientX: this.touchStartX,
                            clientY: this.touchStartY
                        });
                        if (navigator.vibrate) {
                            try { navigator.vibrate(40); } catch(err) {}
                        }
                    }, 450);
                },

                handleTouchMove(e) {
                    if (!this.touchTimer || !e.touches || !e.touches[0]) return;
                    let diffX = Math.abs(e.touches[0].clientX - this.touchStartX);
                    let diffY = Math.abs(e.touches[0].clientY - this.touchStartY);
                    if (diffX > 10 || diffY > 10) {
                        clearTimeout(this.touchTimer);
                        this.touchTimer = null;
                    }
                },

                handleTouchEnd() {
                    clearTimeout(this.touchTimer);
                    this.touchTimer = null;
                },

                applyFormat(cmd, arg = null) {
                    this.restoreSelection();
                    this.exec(cmd, arg);
                    this.closeContextMenu();
                    this.handleInput();
                },

                applyHighlight() {
                    this.restoreSelection();
                    this.toggleHighlight();
                    this.closeContextMenu();
                    this.handleInput();
                },

                applyCode() {
                    this.restoreSelection();
                    let sel = window.getSelection();
                    if (sel && !sel.isCollapsed) {
                        let text = sel.toString();
                        this.exec('insertHTML', `<code class="bg-gray-100 dark:bg-gray-800 text-pink-600 dark:text-pink-400 px-1.5 py-0.5 rounded font-mono text-xs">${text}</code>`);
                    } else {
                        this.exec('insertHTML', `<code class="bg-gray-100 dark:bg-gray-800 text-pink-600 dark:text-pink-400 px-1.5 py-0.5 rounded font-mono text-xs">kode</code>`);
                    }
                    this.closeContextMenu();
                    this.handleInput();
                },

                insertBlockFromContext(type) {
                    this.restoreSelection();
                    this.closeContextMenu();
                    switch(type) {
                        case 'p': this.insertHeading('p'); break;
                        case 'h1': this.insertHeading('h1'); break;
                        case 'h2': this.insertHeading('h2'); break;
                        case 'h3': this.insertHeading('h3'); break;
                        case 'project': this.insertProjectBlock(); break;
                        case 'todo': this.insertChecklist(); break;
                        case 'bullet': this.exec('insertUnorderedList'); break;
                        case 'num': this.exec('insertOrderedList'); break;
                        case 'quote': this.insertQuote(); break;
                        case 'table': this.insertTable(); break;
                        case 'divider': this.insertDivider(); break;
                        case 'time': this.insertTimestamp(); break;
                    }
                    this.handleInput();
                },

                outline: [],
                activeHeadingId: null,
                leftSearch: '',
                outlineTimer: null,

                toggleLeftSidebar() {
                    this.showLeftSidebar = !this.showLeftSidebar;
                    localStorage.setItem('atoma_note_left_sidebar', this.showLeftSidebar);
                },

                toggleRightPanel() {
                    this.showRightPanel = !this.showRightPanel;
                    localStorage.setItem('atoma_note_right_panel', this.showRightPanel);
                },

                setLayoutWidth(val) {
                    this.layoutWidth = val;
                    localStorage.setItem('atoma_note_width', val);
                },

                setFontStyle(val) {
                    this.fontStyle = val;
                    localStorage.setItem('atoma_note_font', val);
                },

                toggleZenMode() {
                    this.zenMode = !this.zenMode;
                    if (this.zenMode) {
                        this.showToolbar = false;
                    } else {
                        this.showToolbar = true;
                    }
                },

                extractOutline() {
                    if (!this.$refs.editor) return;
                    let nodes = this.$refs.editor.querySelectorAll('h1, h2, h3, h4');
                    let items = [];
                    let count = 0;
                    nodes.forEach((el) => {
                        let text = el.textContent ? el.textContent.trim() : '';
                        if (!text) return;

                        count++;
                        if (!el.id) {
                            el.id = 'heading-ref-' + count;
                        }
                        let level = 2;
                        if (el.tagName === 'H1') level = 1;
                        else if (el.tagName === 'H2') level = 2;
                        else if (el.tagName === 'H3') level = 3;
                        else if (el.tagName === 'H4') level = 4;

                        let cleanText = text.replace(/^#{1,4}\s+/, '').trim();
                        if (cleanText.length > 55) cleanText = cleanText.substring(0, 55) + '...';

                        items.push({
                            id: el.id,
                            text: cleanText,
                            level: level
                        });
                    });
                    this.outline = items;
                },

                scrollToHeading(id) {
                    let el = document.getElementById(id);
                    if (el) {
                        this.activeHeadingId = id;
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.classList.add('ring-2', 'ring-indigo-500/50', 'bg-indigo-50/70', 'dark:bg-indigo-950/50', 'rounded-lg', 'transition-all');
                        setTimeout(() => {
                            el.classList.remove('ring-2', 'ring-indigo-500/50', 'bg-indigo-50/70', 'dark:bg-indigo-950/50');
                        }, 1800);
                    }
                },

                copyCleanText() {
                    if (!this.$refs.editor) return;
                    let text = (this.$wire.title ? this.$wire.title + "\n\n" : '') + (this.$refs.editor.innerText || '');
                    navigator.clipboard.writeText(text).then(() => {
                        this.$dispatch('toast', { message: 'Teks catatan berhasil disalin ke clipboard!', type: 'success' });
                    });
                },

                saveSelection() {
                    let sel = window.getSelection();
                    if (sel && sel.rangeCount > 0 && this.$refs.editor && this.$refs.editor.contains(sel.anchorNode)) {
                        this.savedRange = sel.getRangeAt(0).cloneRange();
                    }
                },

                restoreSelection() {
                    let sel = window.getSelection();
                    if (sel && sel.rangeCount > 0 && this.$refs.editor && this.$refs.editor.contains(sel.anchorNode)) {
                        return;
                    }
                    if (this.savedRange) {
                        if (sel) {
                            sel.removeAllRanges();
                            sel.addRange(this.savedRange);
                        }
                    }
                },

                slashCommands: [
                    { id: 'p', label: 'Teks Normal', desc: 'Paragraf biasa', icon: '¶', action: 'normal', keywords: ['p', 'text', 'normal', 'paragraf', 'body'] },
                    { id: 'h1', label: 'Judul Utama', desc: 'Heading tingkat 1', icon: 'H1', action: 'heading1', keywords: ['h1', 'judul', 'title'] },
                    { id: 'h2', label: 'Judul Besar', desc: 'Heading tingkat 2', icon: 'H2', action: 'heading2', keywords: ['h2', 'subjudul', 'heading'] },
                    { id: 'h3', label: 'Subjudul', desc: 'Heading tingkat 3', icon: 'H3', action: 'heading3', keywords: ['h3', 'section', 'bagian'] },
                    { id: 'project', label: 'Proyek Manajemen', desc: 'Roadmap & milestone proyek', icon: '🚀', action: 'project', keywords: ['project', 'proyek', 'manajemen', 'sprint', 'roadmap'] },
                    { id: 'checklist', label: 'Checklist', desc: 'Daftar centang tugas', icon: '☑', action: 'checklist', keywords: ['checklist', 'todo', 'task', 'tugas', 'centang'] },
                    { id: 'todo', label: 'Todo / Tugas', desc: 'Daftar centang tugas', icon: '☑', action: 'checklist', keywords: ['todo', 'checklist'] },
                    { id: 'tabel', label: 'Tabel Grid', desc: 'Tabel baris & kolom', icon: '⊞', action: 'table', keywords: ['tabel', 'table', 'grid'] },
                    { id: 'table', label: 'Table Grid', desc: 'Tabel baris & kolom', icon: '⊞', action: 'table', keywords: ['table', 'tabel', 'grid'] },
                    { id: 'bullet', label: 'Daftar Poin', desc: 'Bullet list sederhana', icon: '•', action: 'bullet', keywords: ['bullet', 'poin', 'list'] },
                    { id: 'num', label: 'Daftar Nomor', desc: 'Urutan bernomor 1, 2, 3', icon: '1.', action: 'numbered', keywords: ['num', 'nomor', 'angka', 'ordered'] },
                    { id: 'quote', label: 'Kutipan', desc: 'Blok kutipan bergaris', icon: '”', action: 'quote', keywords: ['quote', 'kutipan', 'quotes'] },
                    { id: 'divider', label: 'Garis Pemisah', desc: 'Pemisah horizontal', icon: '—', action: 'divider', keywords: ['divider', 'garis', 'line', 'pemisah', 'hr'] },
                    { id: 'link', label: 'Tautan Catatan', desc: 'Hubungkan ke catatan lain', icon: '🔗', action: 'notelink', keywords: ['link', 'tautan', 'catatan', 'connect', 'hubungkan'] },
                    { id: 'drive', label: 'Google Drive / PDF', desc: 'Tautan Google Drive, Docs, Sheets, atau PDF', icon: '📁', action: 'drivelink', keywords: ['drive', 'google', 'pdf', 'docs', 'sheet', 'file', 'link'] },
                    { id: 'time', label: 'Waktu Sekarang', desc: 'Timestamp otomatis', icon: '⏱', action: 'timestamp', keywords: ['time', 'waktu', 'jam', 'tanggal', 'date', 'stamp'] }
                ],

                get filteredSlashCommands() {
                    if (!this.slashFilter) return this.slashCommands.filter((c, i, a) => a.findIndex(t => t.id === c.id) === i);
                    let q = this.slashFilter.toLowerCase();
                    return this.slashCommands.filter((c, i, a) => {
                        let isUnique = a.findIndex(t => t.action === c.action && t.id === c.id) === i;
                        if (!isUnique) return false;
                        let matchId = c.id.includes(q);
                        let matchLabel = c.label.toLowerCase().includes(q);
                        let matchDesc = c.desc.toLowerCase().includes(q);
                        let matchKeywords = c.keywords && c.keywords.some(k => k.includes(q));
                        return matchId || matchLabel || matchDesc || matchKeywords;
                    });
                },

                init() {
                    window.__syncActiveJournal = () => this.syncContent();

                    window.addEventListener('resize', () => {
                        if (this.showNotePreviewPopup) {
                            this.clampMiniNotePosition();
                        }
                    });
                    
                    document.addEventListener('selectionchange', () => {
                        if (!this.$wire.showCreateModal) return;
                        if (this.selRaf) cancelAnimationFrame(this.selRaf);
                        this.selRaf = requestAnimationFrame(() => {
                            this.saveSelection();
                            this.checkBubbleMenu();
                        });
                    });

                    window.addEventListener('journal-autosaved', (e) => {
                        this.autosaveStatus = 'saved';
                        this.noteCache = {};
                        this.lastSavedTime = (e.detail && e.detail.time) ? e.detail.time : new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        setTimeout(() => {
                            if (this.autosaveStatus === 'saved') {
                                this.autosaveStatus = 'idle';
                            }
                        }, 3500);
                    });

                    this.$watch('$wire.showCreateModal', value => {
                        if (value) {
                            this.isScrolled = false;
                            this.showBubbleMenu = false;
                            this.autosaveStatus = 'idle';
                            this.showSlashMenu = false;
                            this.showAaMenu = false;
                            this.showContextMenu = false;
                            this.showTableModal = false;
                            this.activeTable = null;
                            this.activeCell = null;
                            this.savedRange = null;
                            if (this.defaultZenPreference) {
                                this.zenMode = true;
                                this.showToolbar = false;
                            }
                            this.$nextTick(() => {
                                if (this.$refs.editor) {
                                    let content = this.$wire.content || '';
                                    if (this.$refs.editor.innerHTML !== content) {
                                        this.$refs.editor.innerHTML = content;
                                    }
                                    this.bindCheckboxes();
                                    this.extractOutline();
                                    this.updateStats();
                                    this.prefetchLinkedNotes();
                                }
                            });
                        }
                    });

                    this.$watch('$wire.editingJournalId', () => {
                        this.$nextTick(() => {
                            if (this.$refs.editor) {
                                let content = this.$wire.content || '';
                                if (this.$refs.editor.innerHTML !== content) {
                                    this.$refs.editor.innerHTML = content;
                                }
                                this.bindCheckboxes();
                                this.extractOutline();
                                this.updateStats();
                                this.prefetchLinkedNotes();
                            }
                        });
                    });

                    this.$nextTick(() => {
                        if (this.$refs.editor) {
                            let content = this.$wire.content || '';
                            if (!this.$refs.editor.innerHTML && content) {
                                this.$refs.editor.innerHTML = content;
                            }
                            this.bindCheckboxes();
                            this.extractOutline();
                            this.updateStats();
                            this.prefetchLinkedNotes();
                        }
                    });
                },

                bindCheckboxes() {
                    if (!this.$refs.editor) return;
                    const boxes = this.$refs.editor.querySelectorAll('.journal-checklist-item input[type="checkbox"]');
                    boxes.forEach(box => {
                        box.onchange = (e) => {
                            if (box.checked) {
                                box.setAttribute('checked', 'checked');
                            } else {
                                box.removeAttribute('checked');
                            }
                            this.handleInput();
                        };
                    });
                },

                handleTitleInput(e) {
                    if (e && e.target) {
                        this.$wire.set('title', e.target.value, false);
                    }
                    this.handleInput();
                },

                syncContent() {
                    if (this.$refs.titleInput) {
                        this.$wire.set('title', this.$refs.titleInput.value, false);
                    }
                    if (this.$refs.editor) {
                        const boxes = this.$refs.editor.querySelectorAll('.journal-checklist-item input[type="checkbox"]');
                        boxes.forEach(box => {
                            if (box.checked) {
                                box.setAttribute('checked', 'checked');
                            } else {
                                box.removeAttribute('checked');
                            }
                        });
                        this.$wire.set('content', this.$refs.editor.innerHTML, false);
                    }
                },

                handleInput() {
                    this.syncContent();
                    this.updateStats();

                    // Coalesced typing snapshot for seamless undo/redo
                    if (!this.isUndoRedoAction) {
                        if (this.typingSnapshotTimer) clearTimeout(this.typingSnapshotTimer);
                        this.typingSnapshotTimer = setTimeout(() => {
                            this.saveSnapshot();
                        }, 450);
                    }

                    if (this.autosaveTimer) {
                        clearTimeout(this.autosaveTimer);
                    }
                    this.autosaveTimer = setTimeout(() => {
                        this.autosaveStatus = 'saving';
                        this.triggerAutosave();
                    }, 800);

                    if (this.outlineTimer) clearTimeout(this.outlineTimer);
                    this.outlineTimer = setTimeout(() => {
                        this.extractOutline();
                    }, 300);
                },

                handleBlur() {
                    this.syncContent();
                    this.triggerAutosave();
                },

                triggerAutosave() {
                    this.syncContent();
                    let title = this.$refs.titleInput ? this.$refs.titleInput.value : (this.$wire.title || '');
                    let content = this.$refs.editor ? this.$refs.editor.innerHTML : '';
                    this.$wire.autoSaveJournal(title, content);
                },

                focusEditor() {
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                        let sel = window.getSelection();
                        if (sel) {
                            let range = document.createRange();
                            if (this.$refs.editor.childNodes.length === 0) {
                                let p = document.createElement('p');
                                p.innerHTML = '<br>';
                                this.$refs.editor.appendChild(p);
                                range.setStart(p, 0);
                            } else {
                                let target = this.$refs.editor.firstChild;
                                while (target && target.firstChild && target.nodeType !== 3) {
                                    target = target.firstChild;
                                }
                                if (target) {
                                    range.setStart(target, 0);
                                } else {
                                    range.setStart(this.$refs.editor, 0);
                                }
                            }
                            range.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(range);
                        }
                    }
                },

                saveSnapshot() {
                    if (this.isUndoRedoAction || !this.$refs.editor) return;
                    let html = this.$refs.editor.innerHTML;
                    if (this.undoStack.length === 0 || this.undoStack[this.undoStack.length - 1] !== html) {
                        this.undoStack.push(html);
                        if (this.undoStack.length > 80) this.undoStack.shift();
                        this.redoStack = [];
                    }
                },

                customUndo() {
                    if (!this.$refs.editor) return;
                    let currentHtml = this.$refs.editor.innerHTML;
                    
                    // Flush current text to top of undo stack if it has uncommitted edits
                    if (this.undoStack.length === 0 || this.undoStack[this.undoStack.length - 1] !== currentHtml) {
                        this.undoStack.push(currentHtml);
                    }

                    if (this.undoStack.length > 1) {
                        let top = this.undoStack.pop();
                        this.redoStack.push(top);
                        let prev = this.undoStack[this.undoStack.length - 1];
                        if (prev !== undefined) {
                            this.isUndoRedoAction = true;
                            this.$refs.editor.innerHTML = prev;
                            this.bindCheckboxes();
                            this.syncContent();
                            this.updateStats();
                            this.$nextTick(() => { this.isUndoRedoAction = false; });
                            return;
                        }
                    } else if (this.undoStack.length === 1 && this.undoStack[0] !== currentHtml) {
                        this.redoStack.push(currentHtml);
                        this.isUndoRedoAction = true;
                        this.$refs.editor.innerHTML = this.undoStack[0];
                        this.bindCheckboxes();
                        this.syncContent();
                        this.updateStats();
                        this.$nextTick(() => { this.isUndoRedoAction = false; });
                        return;
                    }
                    
                    document.execCommand('undo');
                    this.syncContent();
                },

                customRedo() {
                    if (!this.$refs.editor) return;
                    if (this.redoStack.length > 0) {
                        let next = this.redoStack.pop();
                        this.undoStack.push(next);
                        this.isUndoRedoAction = true;
                        this.$refs.editor.innerHTML = next;
                        this.bindCheckboxes();
                        this.syncContent();
                        this.updateStats();
                        this.$nextTick(() => { this.isUndoRedoAction = false; });
                        return;
                    }
                    document.execCommand('redo');
                    this.syncContent();
                },

                exec(cmd, arg = null) {
                    if (cmd === 'undo') {
                        this.customUndo();
                        return;
                    }
                    if (cmd === 'redo') {
                        this.customRedo();
                        return;
                    }
                    this.saveSnapshot();
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let sel = window.getSelection();
                    if (!sel || sel.rangeCount === 0 || !this.$refs.editor || !this.$refs.editor.contains(sel.anchorNode)) {
                        let range = document.createRange();
                        if (this.$refs.editor && this.$refs.editor.lastChild) {
                            range.selectNodeContents(this.$refs.editor);
                            range.collapse(false);
                        } else if (this.$refs.editor) {
                            let p = document.createElement('p');
                            p.innerHTML = '<br>';
                            this.$refs.editor.appendChild(p);
                            range.setStart(p, 0);
                            range.collapse(true);
                        }
                        if (sel) {
                            sel.removeAllRanges();
                            sel.addRange(range);
                        }
                    }
                    document.execCommand(cmd, false, arg);
                    this.saveSelection();
                    this.handleInput();
                },

                toggleHighlight() {
                    this.saveSnapshot();
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let selection = window.getSelection();
                    if (!selection || selection.rangeCount === 0) {
                        return;
                    }

                    // Clean up any accidentally nested marks from previous edits
                    if (this.$refs.editor) {
                        this.$refs.editor.querySelectorAll('mark mark').forEach(nested => {
                            while (nested.firstChild) {
                                nested.parentNode.insertBefore(nested.firstChild, nested);
                            }
                            nested.remove();
                        });
                    }

                    // If collapsed selection (caret only, no text highlighted)
                    if (selection.isCollapsed) {
                        let mark = selection.anchorNode ? (selection.anchorNode.nodeType === 1 ? selection.anchorNode.closest('mark') : selection.anchorNode.parentElement?.closest('mark')) : null;
                        if (mark) {
                            let parent = mark.parentNode;
                            while (mark.firstChild) {
                                parent.insertBefore(mark.firstChild, mark);
                            }
                            mark.remove();
                            if (parent) parent.normalize();
                            this.saveSelection();
                            this.handleInput();
                            return;
                        }
                        return;
                    }

                    let range = selection.getRangeAt(0);

                    // Find any marks that encompass or intersect the current selection
                    let marksToUnwrap = new Set();

                    // 1. Check if selection is inside a mark
                    let node = range.commonAncestorContainer;
                    let ancestorMark = node ? (node.nodeType === 1 ? node.closest('mark') : node.parentElement?.closest('mark')) : null;
                    if (ancestorMark) {
                        marksToUnwrap.add(ancestorMark);
                    }

                    let anchorMark = selection.anchorNode ? (selection.anchorNode.nodeType === 1 ? selection.anchorNode.closest('mark') : selection.anchorNode.parentElement?.closest('mark')) : null;
                    if (anchorMark) marksToUnwrap.add(anchorMark);

                    let focusMark = selection.focusNode ? (selection.focusNode.nodeType === 1 ? selection.focusNode.closest('mark') : selection.focusNode.parentElement?.closest('mark')) : null;
                    if (focusMark) marksToUnwrap.add(focusMark);

                    // 2. Check if the selection contains any marks inside it
                    let container = range.commonAncestorContainer.nodeType === 1 ? range.commonAncestorContainer : range.commonAncestorContainer.parentElement;
                    if (container) {
                        container.querySelectorAll('mark').forEach(m => {
                            try {
                                if (range.intersectsNode(m)) {
                                    marksToUnwrap.add(m);
                                }
                            } catch (e) {
                                marksToUnwrap.add(m);
                            }
                        });
                    }

                    if (marksToUnwrap.size > 0) {
                        // TOGGLE OFF: Remove all intersecting highlights cleanly
                        let firstUnwrappedNode = null;
                        let lastUnwrappedNode = null;

                        marksToUnwrap.forEach(mark => {
                            if (mark && mark.parentNode) {
                                let parent = mark.parentNode;
                                let frag = document.createDocumentFragment();
                                while (mark.firstChild) {
                                    let child = mark.firstChild;
                                    if (!firstUnwrappedNode) firstUnwrappedNode = child;
                                    lastUnwrappedNode = child;
                                    frag.appendChild(child);
                                }
                                parent.replaceChild(frag, mark);
                                parent.normalize();
                            }
                        });

                        // Re-select the text so user still has it selected
                        if (firstUnwrappedNode && lastUnwrappedNode) {
                            try {
                                let newRange = document.createRange();
                                newRange.setStartBefore(firstUnwrappedNode);
                                newRange.setEndAfter(lastUnwrappedNode);
                                selection.removeAllRanges();
                                selection.addRange(newRange);
                                this.savedRange = newRange.cloneRange();
                            } catch (e) {
                                this.saveSelection();
                            }
                        } else {
                            this.saveSelection();
                        }
                    } else {
                        // TOGGLE ON: Add highlight mark using insertHTML so native undo & snapshots work seamlessly
                        let clone = range.cloneContents();
                        let div = document.createElement('div');
                        div.appendChild(clone);
                        let selectedHtml = div.innerHTML;

                        if (selectedHtml) {
                            let highlightedHtml = `<mark class="journal-highlight">${selectedHtml}</mark>`;
                            let inserted = false;
                            try {
                                inserted = document.execCommand('insertHTML', false, highlightedHtml);
                            } catch (e) {
                                inserted = false;
                            }
                            if (!inserted) {
                                let mark = document.createElement('mark');
                                mark.className = 'journal-highlight';
                                mark.appendChild(range.extractContents());
                                range.insertNode(mark);

                                selection.removeAllRanges();
                                let newRange = document.createRange();
                                newRange.selectNodeContents(mark);
                                selection.addRange(newRange);
                                this.savedRange = newRange.cloneRange();
                            }
                        }
                    }

                    this.handleInput();
                    this.showAaMenu = false;
                },

                insertHeading(tag) {
                    this.saveSnapshot();
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let sel = window.getSelection();
                    let selectedText = sel ? sel.toString().trim() : '';

                    let node = sel ? sel.anchorNode : null;
                    let currentBlock = node ? (node.nodeType === 1 ? node.closest('h1, h2, h3, h4, p, blockquote, div') : node.parentElement?.closest('h1, h2, h3, h4, p, blockquote, div')) : null;

                    // Toggle: If user selects the SAME tag they are already on, toggle back to normal paragraph 'p'
                    let targetTag = tag;
                    if (currentBlock && currentBlock.tagName && currentBlock.tagName.toLowerCase() === tag.toLowerCase()) {
                        targetTag = 'p';
                    }

                    let success = false;
                    try {
                        success = document.execCommand('formatBlock', false, '<' + targetTag + '>');
                        if (!success) {
                            success = document.execCommand('formatBlock', false, targetTag);
                        }
                    } catch(e) {
                        success = false;
                    }

                    if (!success && currentBlock && currentBlock !== this.$refs.editor) {
                        let newEl = document.createElement(targetTag);
                        newEl.innerHTML = currentBlock.innerHTML || (targetTag === 'p' ? '<br>' : (selectedText || 'Teks'));
                        if (targetTag === 'h1') newEl.className = 'text-2xl sm:text-3xl font-extrabold my-3';
                        else if (targetTag === 'h2') newEl.className = 'text-xl font-bold my-2';
                        else if (targetTag === 'h3') newEl.className = 'text-lg font-semibold my-1.5';
                        else newEl.className = 'my-1';
                        currentBlock.parentNode.replaceChild(newEl, currentBlock);
                    } else if (!success && targetTag !== 'p') {
                        let titleText = selectedText || (targetTag === 'h2' ? 'Judul Bagian' : 'Subjudul');
                        let html = `<${targetTag} class="${targetTag === 'h2' ? 'text-xl font-bold my-2' : 'text-lg font-semibold my-1.5'}">${titleText}</${targetTag}><p><br></p>`;
                        this.exec('insertHTML', html);
                    }
                    this.showAaMenu = false;
                    this.handleInput();
                },

                insertProjectBlock() {
                    this.saveSnapshot();
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    const projectHtml = `
<div class="my-4 p-4 rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-800/60 journal-project-card select-text" contenteditable="true">
    <div class="flex items-center justify-between pb-2 mb-2 border-b border-indigo-200/60 dark:border-indigo-800/50">
        <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded-lg bg-indigo-600 text-white text-[10px] font-black tracking-wider uppercase">PROYEK</span>
            <strong class="text-sm font-bold text-gray-900 dark:text-gray-100">Nama Proyek</strong>
        </div>
        <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold">Fase 1: Rencana</span>
    </div>
    <div class="space-y-1.5 my-2">
        <div class="flex items-center gap-2 text-xs text-gray-800 dark:text-gray-200">
            <input type="checkbox" class="w-3.5 h-3.5 rounded text-indigo-600">
            <span class="flex-1">Tahap 1: Riset & Analisis Kebutuhan</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-800 dark:text-gray-200">
            <input type="checkbox" class="w-3.5 h-3.5 rounded text-indigo-600">
            <span class="flex-1">Tahap 2: Eksekusi Fitur Utama</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-800 dark:text-gray-200">
            <input type="checkbox" class="w-3.5 h-3.5 rounded text-indigo-600">
            <span class="flex-1">Tahap 3: Testing & Rilis</span>
        </div>
    </div>
</div>
<p><br></p>
`;
                    this.exec('insertHTML', projectHtml);
                    this.bindCheckboxes();
                    this.handleInput();
                },

                insertChecklist() {
                    this.restoreSelection();
                    let selection = window.getSelection();
                    let selectedText = '';
                    if (selection && selection.rangeCount > 0) {
                        selectedText = selection.toString().trim();
                    }
                    
                    let lines = selectedText ? selectedText.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0) : [];
                    if (lines.length === 0) {
                        lines = ['Item tugas baru...'];
                    }

                    let html = lines.map(line => `
                        <div class="flex items-center gap-2.5 my-1.5 journal-checklist-item" style="clear:both;">
                            <input type="checkbox" class="w-4 h-4 rounded text-indigo-600 border-gray-300 dark:border-gray-600 focus:ring-0 cursor-pointer shrink-0">
                            <span contenteditable="true" class="flex-1 text-gray-800 dark:text-gray-200 outline-none">${line}</span>
                        </div>
                    `).join('') + '<p><br></p>';

                    this.exec('insertHTML', html);
                    this.$nextTick(() => this.bindCheckboxes());
                },

                insertQuote() {
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let selection = window.getSelection();
                    let selectedText = '';
                    if (selection && selection.rangeCount > 0) {
                        selectedText = selection.toString().trim();
                    }
                    let quoteText = selectedText ? selectedText : 'Kutipan atau catatan penting di sini...';
                    let html = `<blockquote class="border-l-4 border-indigo-500 pl-3 my-2 italic text-gray-700 dark:text-gray-300 font-medium">${quoteText}</blockquote><p><br></p>`;
                    this.exec('insertHTML', html);
                },

                insertDivider() {
                    let html = `<hr class="my-4 border-t-2 border-dashed border-gray-200 dark:border-gray-700"><p><br></p>`;
                    this.exec('insertHTML', html);
                },

                insertTimestamp() {
                    const now = new Date();
                    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const stamp = ` [${dateStr}, ${timeStr}] `;
                    this.exec('insertText', stamp);
                },

                insertTable(cols = null, rows = null) {
                    this.restoreSelection();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let cCount = cols ? Math.max(1, parseInt(cols)) : Math.max(1, parseInt(this.tableCols) || 2);
                    let rCount = rows ? Math.max(1, parseInt(rows)) : Math.max(1, parseInt(this.tableRows) || 2);
                    let tbl = '<div class="w-full my-3 clear-both block journal-table-wrap overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800" style="clear:both; width:100%;"><table style="min-width:100%!important; width:100%; clear:both!important;" class="w-full border-collapse text-xs bg-white dark:bg-gray-900"><thead><tr class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-bold">';
                    for (let c = 1; c <= cCount; c++) {
                        tbl += '<th class="border border-gray-200 dark:border-gray-800 px-3 py-2 text-left font-black min-w-[120px]" contenteditable="true">Kolom ' + c + '</th>';
                    }
                    tbl += '</tr></thead><tbody>';
                    for (let r = 1; r <= rCount; r++) {
                        tbl += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">';
                        for (let c = 1; c <= cCount; c++) {
                            tbl += '<td class="border border-gray-200 dark:border-gray-800 px-3 py-2 text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-900 min-w-[120px]" contenteditable="true">Data ' + r + '-' + c + '</td>';
                        }
                        tbl += '</tr>';
                    }
                    tbl += '</tbody></table></div><p><br></p>';
                    this.exec('insertHTML', tbl);
                    this.showTableModal = false;
                    this.$nextTick(() => {
                        if (this.$refs.editor) {
                            let tables = this.$refs.editor.querySelectorAll('table');
                            let lastTable = tables[tables.length - 1];
                            if (lastTable) {
                                let firstCell = lastTable.querySelector('tbody td') || lastTable.querySelector('th');
                                if (firstCell) {
                                    this.focusCell(firstCell);
                                }
                            }
                        }
                    });
                },

                insertTag() {
                    let selection = window.getSelection();
                    let selectedText = '';
                    if (selection && selection.rangeCount > 0) {
                        selectedText = selection.toString().trim();
                    }
                    if (selectedText) {
                        this.exec('insertHTML', ' <span class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">#' + selectedText.replace(/^#+/, '') + '</span>&nbsp;');
                    } else {
                        this.exec('insertText', ' #');
                    }
                },

                // Dynamic Table Manipulation & Focus Helper
                focusCell(cell) {
                    if (!cell) return;
                    this.activeCell = cell;
                    this.activeTable = cell.closest('table');
                    try {
                        cell.focus();
                        let selection = window.getSelection();
                        let range = document.createRange();
                        range.selectNodeContents(cell);
                        range.collapse(false);
                        selection.removeAllRanges();
                        selection.addRange(range);
                        this.savedRange = range.cloneRange();
                    } catch(e) {}
                },

                checkTableContext(e = null) {
                    let cell = null;
                    if (e && e.target && typeof e.target.closest === 'function') {
                        cell = e.target.closest('td, th');
                    }
                    if (!cell) {
                        let sel = window.getSelection();
                        if (sel && sel.anchorNode) {
                            let node = sel.anchorNode;
                            cell = node.nodeType === 1 ? node.closest('td, th') : node.parentElement?.closest('td, th');
                        }
                    }
                    if (cell) {
                        this.activeCell = cell;
                        this.activeTable = cell.closest('table');
                    } else {
                        if (!e || !e.target || !e.target.closest || !e.target.closest('#table-controls-bar')) {
                            this.activeCell = null;
                            this.activeTable = null;
                        }
                    }
                },

                addTableRow() {
                    if (!this.activeTable) return;
                    this.saveSnapshot();
                    let tbody = this.activeTable.querySelector('tbody') || this.activeTable;
                    let colsCount = this.activeTable.querySelectorAll('tr:first-child th, tr:first-child td').length || 2;
                    
                    let tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-800/40';
                    for (let i = 0; i < colsCount; i++) {
                        let td = document.createElement('td');
                        td.className = 'border border-gray-200 dark:border-gray-800 px-3 py-2 text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-900 min-w-[120px]';
                        td.contentEditable = 'true';
                        td.innerHTML = '<br>';
                        tr.appendChild(td);
                    }
                    if (this.activeCell && this.activeCell.closest('tr')) {
                        this.activeCell.closest('tr').after(tr);
                    } else {
                        tbody.appendChild(tr);
                    }
                    this.handleInput();
                    let firstTd = tr.querySelector('td');
                    if (firstTd) {
                        this.focusCell(firstTd);
                    }
                },

                addTableCol() {
                    if (!this.activeTable) return;
                    this.saveSnapshot();
                    let allRows = this.activeTable.querySelectorAll('tr');
                    let colIndex = this.activeCell ? Array.from(this.activeCell.parentNode.children).indexOf(this.activeCell) : -1;
                    let newFocusCell = null;

                    allRows.forEach((tr, rIdx) => {
                        let isHead = rIdx === 0 && tr.querySelector('th');
                        let el = document.createElement(isHead ? 'th' : 'td');
                        el.className = isHead 
                            ? 'border border-gray-200 dark:border-gray-800 px-3 py-2 text-left font-black min-w-[120px]'
                            : 'border border-gray-200 dark:border-gray-800 px-3 py-2 text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-900 min-w-[120px]';
                        el.contentEditable = 'true';
                        el.innerHTML = isHead ? `Kolom ${tr.children.length + 1}` : '<br>';

                        let children = Array.from(tr.children);
                        if (colIndex >= 0 && colIndex < children.length) {
                            children[colIndex].after(el);
                        } else {
                            tr.appendChild(el);
                        }

                        if (this.activeCell && tr === this.activeCell.parentNode) {
                            newFocusCell = el;
                        } else if (!newFocusCell && isHead) {
                            newFocusCell = el;
                        }
                    });
                    this.handleInput();
                    if (newFocusCell) {
                        this.focusCell(newFocusCell);
                    }
                },

                deleteTableRow() {
                    if (!this.activeCell) return;
                    this.saveSnapshot();
                    let tr = this.activeCell.closest('tr');
                    if (tr) {
                        let tbody = tr.parentNode;
                        let nextTr = tr.nextElementSibling || tr.previousElementSibling;
                        let colIndex = Array.from(tr.children).indexOf(this.activeCell);
                        if (tbody.querySelectorAll('tr').length > 1) {
                            tr.remove();
                            if (nextTr) {
                                let targetCell = nextTr.children[colIndex] || nextTr.children[nextTr.children.length - 1];
                                if (targetCell) {
                                    this.focusCell(targetCell);
                                }
                            } else {
                                this.activeCell = null;
                            }
                        } else if (this.activeTable) {
                            this.deleteTable();
                        }
                        this.handleInput();
                    }
                },

                deleteTableCol() {
                    if (!this.activeCell || !this.activeTable) return;
                    this.saveSnapshot();
                    let colIndex = Array.from(this.activeCell.parentNode.children).indexOf(this.activeCell);
                    if (colIndex < 0) return;
                    let allRows = this.activeTable.querySelectorAll('tr');
                    let totalCols = allRows[0] ? allRows[0].children.length : 0;
                    if (totalCols <= 1) {
                        this.deleteTable();
                        return;
                    }
                    let targetRow = this.activeCell.parentNode;
                    allRows.forEach(tr => {
                        if (tr.children[colIndex]) {
                            tr.children[colIndex].remove();
                        }
                    });
                    let newColIndex = Math.min(colIndex, targetRow.children.length - 1);
                    let targetCell = targetRow.children[newColIndex];
                    if (targetCell) {
                        this.focusCell(targetCell);
                    } else {
                        this.activeCell = null;
                    }
                    this.handleInput();
                },

                deleteTable() {
                    if (!this.activeTable) return;
                    this.saveSnapshot();
                    let container = this.activeTable.closest('.journal-table-wrap') || this.activeTable.closest('.clear-both') || this.activeTable;
                    container.remove();
                    this.activeTable = null;
                    this.activeCell = null;
                    this.handleInput();
                },

                // Smart Keydown & Enter Handler for Checklists & Shortcuts (Mac ⌘ & Win Ctrl Compatible)
                handleKeydown(e) {
                    const isMod = e.ctrlKey || e.metaKey;
                    const isShift = e.shiftKey;
                    const isAlt = e.altKey;
                    const key = e.key ? e.key.toLowerCase() : '';
                    const code = e.code || '';

                    // 1. Save: ⌘S or Ctrl+S
                    if (isMod && !isShift && !isAlt && (key === 's' || code === 'KeyS')) {
                        e.preventDefault();
                        this.triggerAutosave();
                        return;
                    }

                    // 2. Bold: ⌘B or Ctrl+B
                    if (isMod && !isShift && !isAlt && (key === 'b' || code === 'KeyB')) {
                        e.preventDefault();
                        this.exec('bold');
                        return;
                    }

                    // 3. Italic: ⌘I or Ctrl+I
                    if (isMod && !isShift && !isAlt && (key === 'i' || code === 'KeyI')) {
                        e.preventDefault();
                        this.exec('italic');
                        return;
                    }

                    // 4. Underline: ⌘U or Ctrl+U
                    if (isMod && !isShift && !isAlt && (key === 'u' || code === 'KeyU')) {
                        e.preventDefault();
                        this.exec('underline');
                        return;
                    }

                    // 5. Strikethrough: ⌘⇧X or Ctrl+Shift+X or ⌘⇧5 or Alt+Shift+5
                    if ((isMod && isShift && (key === 'x' || key === '5' || key === '%' || code === 'KeyX' || code === 'Digit5')) ||
                        (isAlt && isShift && (key === '5' || key === '%' || code === 'Digit5'))) {
                        e.preventDefault();
                        this.exec('strikeThrough');
                        return;
                    }

                    // 6. Highlight (Stabilo): ⌘⇧H or Ctrl+Shift+H or Alt+H
                    if ((isMod && isShift && (key === 'h' || code === 'KeyH')) ||
                        (isAlt && !isShift && (key === 'h' || code === 'KeyH'))) {
                        e.preventDefault();
                        this.toggleHighlight();
                        return;
                    }

                    // 7. Checklist: ⌘⇧C or Ctrl+Shift+C or ⌘⇧9
                    if (isMod && isShift && (key === 'c' || key === '9' || key === '(' || code === 'KeyC' || code === 'Digit9')) {
                        e.preventDefault();
                        this.insertChecklist();
                        return;
                    }

                    // 8. Bullet List: ⌘⇧8 or Ctrl+Shift+8 (Shift+8 outputs '*' on QWERTY)
                    if (isMod && isShift && (key === '8' || key === '*' || code === 'Digit8')) {
                        e.preventDefault();
                        this.exec('insertUnorderedList');
                        return;
                    }

                    // 9. Numbered List: ⌘⇧7 or Ctrl+Shift+7 (Shift+7 outputs '&' on QWERTY)
                    if (isMod && isShift && (key === '7' || key === '&' || code === 'Digit7')) {
                        e.preventDefault();
                        this.exec('insertOrderedList');
                        return;
                    }

                    // 10. Quote: ⌘⇧Q or Ctrl+Shift+Q
                    if (isMod && isShift && (key === 'q' || code === 'KeyQ')) {
                        e.preventDefault();
                        this.insertQuote();
                        return;
                    }

                    // 11. Undo: ⌘Z or Ctrl+Z
                    if (isMod && !isShift && !isAlt && (key === 'z' || code === 'KeyZ')) {
                        e.preventDefault();
                        this.exec('undo');
                        return;
                    }

                    // 12. Redo: ⌘⇧Z or Ctrl+Shift+Z or ⌘Y or Ctrl+Y
                    if ((isMod && isShift && (key === 'z' || code === 'KeyZ')) ||
                        (isMod && !isShift && (key === 'y' || code === 'KeyY'))) {
                        e.preventDefault();
                        this.exec('redo');
                        return;
                    }

                    // 13. Smart Chunk-aware Select All: ⌘A or Ctrl+A (1st press selects active block/chunk, 2nd press selects all)
                    if (isMod && !isShift && !isAlt && (key === 'a' || code === 'KeyA')) {
                        let sel = window.getSelection();
                        if (sel && sel.rangeCount > 0 && this.$refs.editor && this.$refs.editor.contains(sel.anchorNode)) {
                            let node = sel.anchorNode;
                            let block = node.nodeType === 1 ? node.closest('p, h1, h2, h3, h4, blockquote, pre, .journal-checklist-item, .journal-project-card, table, tr, div') : node.parentElement?.closest('p, h1, h2, h3, h4, blockquote, pre, .journal-checklist-item, .journal-project-card, table, tr, div');

                            if (block && block !== this.$refs.editor && this.$refs.editor.contains(block)) {
                                let currentRange = sel.getRangeAt(0);
                                let blockRange = document.createRange();
                                blockRange.selectNodeContents(block);

                                let isFullySelected = (
                                    currentRange.startContainer === blockRange.startContainer &&
                                    currentRange.startOffset === blockRange.startOffset &&
                                    currentRange.endContainer === blockRange.endContainer &&
                                    currentRange.endOffset === blockRange.endOffset
                                );

                                if (!isFullySelected) {
                                    e.preventDefault();
                                    sel.removeAllRanges();
                                    sel.addRange(blockRange);
                                    return;
                                }
                            }
                        }
                    }

                    if (this.showSlashMenu) {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            if (this.filteredSlashCommands.length > 0) {
                                this.slashIndex = (this.slashIndex + 1) % this.filteredSlashCommands.length;
                                this.scrollToActiveSlashItem();
                            }
                            return;
                        }
                        if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (this.filteredSlashCommands.length > 0) {
                                this.slashIndex = (this.slashIndex - 1 + this.filteredSlashCommands.length) % this.filteredSlashCommands.length;
                                this.scrollToActiveSlashItem();
                            }
                            return;
                        }
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            if (this.filteredSlashCommands[this.slashIndex]) {
                                this.executeSlashCommand(this.filteredSlashCommands[this.slashIndex]);
                            }
                            return;
                        }
                        if (e.key === 'Escape') {
                            this.showSlashMenu = false;
                            return;
                        }
                    }

                    if (e.key === 'Escape' && this.zenMode) {
                        this.toggleZenMode();
                        return;
                    }

                    // Smart Backspace: Delete full Word-like Tab Space in 1 keystroke
                    if (key === 'backspace' || code === 'Backspace') {
                        let sel = window.getSelection();
                        if (sel && sel.rangeCount > 0 && sel.isCollapsed) {
                            let range = sel.getRangeAt(0);
                            let node = range.startContainer;
                            let offset = range.startOffset;

                            let prevEl = null;
                            if (node.nodeType === 3) {
                                let text = node.textContent;
                                if (offset === 0 || (offset === 1 && text.charCodeAt(0) === 8203)) {
                                    prevEl = node.previousSibling;
                                }
                            } else if (node.nodeType === 1 && offset > 0) {
                                prevEl = node.childNodes[offset - 1];
                            }

                            if (prevEl && prevEl.classList && prevEl.classList.contains('journal-tab-space')) {
                                e.preventDefault();
                                this.saveSnapshot();
                                if (node.nodeType === 3 && (node.textContent === '\u200B' || node.textContent === '')) {
                                    node.remove();
                                }
                                prevEl.remove();
                                this.handleInput();
                                return;
                            }
                        }
                    }

                    // Table Cell Keyboard Navigation: Tab / Shift+Tab & Smart Enter (Strictly inside td/th)
                    let sel = window.getSelection();
                    let currentTableCell = null;
                    if (sel && sel.anchorNode) {
                        let n = sel.anchorNode;
                        currentTableCell = n.nodeType === 1 ? n.closest('td, th') : n.parentElement?.closest('td, th');
                    }

                    if (currentTableCell) {
                        this.activeCell = currentTableCell;
                        this.activeTable = currentTableCell.closest('table');
                    } else {
                        this.activeCell = null;
                        this.activeTable = null;
                    }

                    if (currentTableCell && this.activeTable) {
                        // 1. Tab / Shift+Tab: Move between cells or auto-add row on last cell
                        if (e.key === 'Tab') {
                            e.preventDefault();
                            let allCells = Array.from(this.activeTable.querySelectorAll('th, td'));
                            let currentIndex = allCells.indexOf(tableCell);

                            if (isShift) {
                                // Previous cell
                                if (currentIndex > 0) {
                                    this.focusCell(allCells[currentIndex - 1]);
                                }
                            } else {
                                // Next cell or auto-add row if at last cell of the table
                                if (currentIndex < allCells.length - 1) {
                                    this.focusCell(allCells[currentIndex + 1]);
                                } else {
                                    this.addTableRow();
                                }
                            }
                            return;
                        }

                        // 2. Enter inside table cell
                        if (e.key === 'Enter') {
                            if (isMod) {
                                // Cmd/Ctrl + Enter: Exit table to paragraph below
                                e.preventDefault();
                                let wrap = this.activeTable.closest('.journal-table-wrap') || this.activeTable.closest('.clear-both') || this.activeTable;
                                let nextP = wrap.nextElementSibling;
                                if (!nextP || nextP.tagName.toLowerCase() !== 'p') {
                                    nextP = document.createElement('p');
                                    nextP.innerHTML = '<br>';
                                    wrap.after(nextP);
                                }
                                let sel = window.getSelection();
                                let range = document.createRange();
                                range.selectNodeContents(nextP);
                                range.collapse(false);
                                sel.removeAllRanges();
                                sel.addRange(range);
                                this.activeCell = null;
                                this.activeTable = null;
                                this.handleInput();
                                return;
                            } else if (!isShift) {
                                // Soft line break <br> inside cell without breaking table layout
                                e.preventDefault();
                                let sel = window.getSelection();
                                if (sel && sel.rangeCount > 0) {
                                    let range = sel.getRangeAt(0);
                                    range.deleteContents();
                                    let br = document.createElement('br');
                                    range.insertNode(br);
                                    let space = document.createTextNode('\u200B');
                                    br.after(space);
                                    range.setStartAfter(space);
                                    range.collapse(true);
                                    sel.removeAllRanges();
                                    sel.addRange(range);
                                    this.handleInput();
                                }
                                return;
                            }
                        }
                    } else if (e.key === 'Tab') {
                        // Dedicated Microsoft Word-like Tab indentation engine
                        e.preventDefault();
                        this.handleTabKey(isShift, e);
                        return;
                    }

                    if (e.key === 'Enter') {
                        let selection = window.getSelection();
                        if (selection && selection.rangeCount > 0) {
                            let node = selection.anchorNode;
                            let checkItem = node ? (node.nodeType === 1 ? node.closest('.journal-checklist-item') : node.parentElement?.closest('.journal-checklist-item')) : null;
                            if (checkItem) {
                                let span = checkItem.querySelector('span[contenteditable="true"]');
                                let text = span ? span.textContent.trim() : '';
                                if (text === '') {
                                    // Empty checklist item -> convert to regular paragraph
                                    e.preventDefault();
                                    let p = document.createElement('p');
                                    p.innerHTML = '<br>';
                                    checkItem.parentNode.replaceChild(p, checkItem);
                                    let range = document.createRange();
                                    range.setStart(p, 0);
                                    range.collapse(true);
                                    selection.removeAllRanges();
                                    selection.addRange(range);
                                    this.handleInput();
                                    return;
                                } else {
                                    // Non-empty checklist item -> insert new checklist item below
                                    e.preventDefault();
                                    let newItem = document.createElement('div');
                                    newItem.className = 'flex items-center gap-2.5 my-1.5 journal-checklist-item';
                                    newItem.style.clear = 'both';
                                    newItem.innerHTML = `
                                        <input type="checkbox" class="w-4 h-4 rounded text-indigo-600 border-gray-300 dark:border-gray-600 focus:ring-0 cursor-pointer shrink-0">
                                        <span contenteditable="true" class="flex-1 text-gray-800 dark:text-gray-200 outline-none"><br></span>
                                    `;
                                    checkItem.after(newItem);
                                    let newSpan = newItem.querySelector('span');
                                    let range = document.createRange();
                                    range.setStart(newSpan, 0);
                                    range.collapse(true);
                                    selection.removeAllRanges();
                                    selection.addRange(range);
                                    this.bindCheckboxes();
                                    this.handleInput();
                                    return;
                                }
                            }
                        }
                    }
                },

                // Dedicated Microsoft Word-style Tab & Indentation Engine
                handleTabKey(isShift = false, e = null) {
                    if (e && typeof e.preventDefault === 'function') {
                        e.preventDefault();
                    }

                    let sel = window.getSelection();
                    if (!sel || sel.rangeCount === 0 || !this.$refs.editor || !this.$refs.editor.contains(sel.anchorNode)) {
                        this.focusEditor();
                        sel = window.getSelection();
                    }
                    if (!sel || sel.rangeCount === 0) return;

                    let node = sel.anchorNode;

                    // 1. Table navigation check (if strictly inside td/th)
                    let currentCell = node ? (node.nodeType === 1 ? node.closest('td, th') : node.parentElement?.closest('td, th')) : null;
                    if (currentCell) {
                        let table = currentCell.closest('table');
                        if (table) {
                            let allCells = Array.from(table.querySelectorAll('th, td'));
                            let currentIndex = allCells.indexOf(currentCell);
                            if (isShift) {
                                if (currentIndex > 0) this.focusCell(allCells[currentIndex - 1]);
                            } else {
                                if (currentIndex < allCells.length - 1) {
                                    this.focusCell(allCells[currentIndex + 1]);
                                } else {
                                    this.activeTable = table;
                                    this.activeCell = currentCell;
                                    this.addTableRow();
                                }
                            }
                            return;
                        }
                    }

                    // 2. Unordered / Ordered List Item
                    let inList = node ? (node.nodeType === 1 ? node.closest('li') : node.parentElement?.closest('li')) : null;
                    if (inList) {
                        if (isShift) {
                            document.execCommand('outdent', false, null);
                        } else {
                            document.execCommand('indent', false, null);
                        }
                        this.handleInput();
                        return;
                    }

                    // 3. Code block (<pre> or <code>)
                    let inCode = node ? (node.nodeType === 1 ? node.closest('pre, code') : node.parentElement?.closest('pre, code')) : null;
                    if (inCode) {
                        this.saveSnapshot();
                        let range = sel.getRangeAt(0);
                        range.deleteContents();
                        let tabSpaces = document.createTextNode('    ');
                        range.insertNode(tabSpaces);
                        range.setStartAfter(tabSpaces);
                        range.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(range);
                        this.handleInput();
                        return;
                    }

                    // 4. Checklist Item Indentation / Subtasks
                    let checkItem = node ? (node.nodeType === 1 ? node.closest('.journal-checklist-item') : node.parentElement?.closest('.journal-checklist-item')) : null;
                    if (checkItem) {
                        let currentMargin = parseInt(checkItem.style.marginLeft || '0', 10);
                        if (isShift) {
                            if (currentMargin >= 24) {
                                this.saveSnapshot();
                                checkItem.style.marginLeft = Math.max(0, currentMargin - 24) + 'px';
                                this.handleInput();
                                return;
                            }
                        } else {
                            let span = checkItem.querySelector('span[contenteditable="true"]');
                            let offset = sel.anchorOffset;
                            if (offset === 0 || (span && span.contains(node) && node.textContent.trim() === '')) {
                                this.saveSnapshot();
                                if (currentMargin < 96) {
                                    checkItem.style.marginLeft = (currentMargin + 24) + 'px';
                                    this.handleInput();
                                    return;
                                }
                            }
                        }
                    }

                    // 5. Shift+Tab in Regular Text (Outdent)
                    if (isShift) {
                        this.saveSnapshot();
                        let range = sel.getRangeAt(0);
                        if (range.collapsed) {
                            let preNode = range.startContainer;
                            let offset = range.startOffset;
                            if (preNode.nodeType === 3) {
                                let text = preNode.textContent;
                                let before = text.substring(0, offset);
                                let tabMatch = before.match(/(\u2003\u2003|\u2003|\u00a0{2,4}|\t|\s{2,4})$/);
                                if (tabMatch) {
                                    let len = tabMatch[0].length;
                                    preNode.textContent = before.substring(0, offset - len) + text.substring(offset);
                                    let newRange = document.createRange();
                                    newRange.setStart(preNode, offset - len);
                                    newRange.collapse(true);
                                    sel.removeAllRanges();
                                    sel.addRange(newRange);
                                    this.handleInput();
                                    return;
                                }
                            } else if (preNode.nodeType === 1 && offset > 0) {
                                let prevChild = preNode.childNodes[offset - 1];
                                if (prevChild && prevChild.classList && prevChild.classList.contains('journal-tab-space')) {
                                    prevChild.remove();
                                    this.handleInput();
                                    return;
                                }
                            }
                        }
                        document.execCommand('outdent', false, null);
                        this.handleInput();
                        return;
                    }

                    // 6. Regular Text: Insert Microsoft Word-like Tab Space
                    this.saveSnapshot();
                    let range = sel.getRangeAt(0);
                    range.deleteContents();

                    let tabSpan = document.createElement('span');
                    tabSpan.className = 'journal-tab-space select-none';
                    tabSpan.style.display = 'inline-block';
                    tabSpan.style.width = '2.5rem';
                    tabSpan.setAttribute('contenteditable', 'false');
                    tabSpan.setAttribute('tabindex', '-1');
                    tabSpan.innerHTML = '&emsp;&emsp;';

                    range.insertNode(tabSpan);

                    let textAfter = document.createTextNode('\u200B');
                    tabSpan.after(textAfter);

                    let newRange = document.createRange();
                    newRange.setStart(textAfter, 1);
                    newRange.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(newRange);

                    this.handleInput();
                },

                // Window-level keydown handler for shortcuts when focus is inside modal
                handleWindowKeydown(e) {
                    if (!this.$wire.showCreateModal) return;

                    const isMod = e.ctrlKey || e.metaKey;
                    const isShift = e.shiftKey;
                    const isAlt = e.altKey;
                    const key = e.key ? e.key.toLowerCase() : '';
                    const code = e.code || '';

                    // Global Save: ⌘S / Ctrl+S works anywhere in modal
                    if (isMod && !isShift && !isAlt && (key === 's' || code === 'KeyS')) {
                        e.preventDefault();
                        this.triggerAutosave();
                        return;
                    }

                    // Window-level Tab trap to prevent jumping to links or out of editor
                    if (e.key === 'Tab') {
                        const activeEl = document.activeElement;
                        const tag = activeEl ? activeEl.tagName.toLowerCase() : '';
                        if (tag === 'input' && activeEl !== this.$refs.titleInput) {
                            return;
                        }
                        e.preventDefault();
                        this.handleTabKey(isShift, e);
                        return;
                    }

                    // Ignore formatting shortcuts if user is typing in an input or textarea (search, tag, or title)
                    const activeEl = document.activeElement;
                    const tag = activeEl ? activeEl.tagName.toLowerCase() : '';
                    if (tag === 'input' || tag === 'textarea') {
                        return;
                    }

                    // If user is already focused inside editor, handleKeydown takes care of it
                    if (this.$refs.editor && this.$refs.editor.contains(activeEl)) {
                        return;
                    }

                    // If focus was lost to toolbar button or blank modal container, forward formatting shortcuts
                    if (isMod || isAlt) {
                        this.handleKeydown(e);
                    }
                },

                // Slash command trigger
                handleKeyup(e) {
                    this.checkTableContext(e);

                    if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key) && this.showSlashMenu) {
                        return;
                    }

                    let selection = window.getSelection();
                    if (selection && selection.rangeCount > 0) {
                        let node = selection.anchorNode;
                        let text = node ? (node.nodeType === 3 ? node.textContent : node.innerText || '') : '';
                        let cursorOffset = selection.anchorOffset;
                        let textBeforeCursor = text.slice(0, cursorOffset);

                        let slashMatch = textBeforeCursor.match(/\/([\w]*)$/);
                        if (slashMatch) {
                            this.showSlashMenu = true;
                            this.slashFilter = slashMatch[1];
                            this.slashIndex = 0;
                            this.updateSlashMenuPosition();
                            this.scrollToActiveSlashItem();
                        } else {
                            this.showSlashMenu = false;
                        }
                    }
                },

                updateSlashMenuPosition() {
                    let sel = window.getSelection();
                    if (sel && sel.rangeCount > 0 && this.$refs.editor) {
                        let range = sel.getRangeAt(0).cloneRange();
                        let rect = range.getBoundingClientRect();
                        let editorRect = this.$refs.editor.getBoundingClientRect();
                        if (rect && rect.top > 0) {
                            let relativeTop = rect.bottom - editorRect.top + 6;
                            let relativeLeft = Math.max(8, rect.left - editorRect.left);
                            if (relativeLeft + 290 > editorRect.width) {
                                relativeLeft = Math.max(8, editorRect.width - 300);
                            }
                            this.slashMenuTop = Math.max(8, Math.round(relativeTop));
                            this.slashMenuLeft = Math.max(8, Math.round(relativeLeft));
                        }
                    }
                },

                scrollToActiveSlashItem() {
                    this.$nextTick(() => {
                        const list = this.$refs.slashDropdownList;
                        const item = document.getElementById('slash-item-' + this.slashIndex);
                        if (list && item) {
                            const listRect = list.getBoundingClientRect();
                            const itemRect = item.getBoundingClientRect();
                            if (itemRect.bottom > listRect.bottom) {
                                list.scrollTop += (itemRect.bottom - listRect.bottom) + 8;
                            } else if (itemRect.top < listRect.top) {
                                list.scrollTop -= (listRect.top - itemRect.top) + 8;
                            }
                        }
                    });
                },

                executeSlashCommand(cmd) {
                    // Remove the slash text
                    let selection = window.getSelection();
                    if (selection && selection.rangeCount > 0) {
                        let node = selection.anchorNode;
                        if (node && node.nodeType === 3) {
                            let cursorOffset = selection.anchorOffset;
                            let text = node.textContent;
                            let lastSlash = text.lastIndexOf('/', cursorOffset);
                            if (lastSlash !== -1) {
                                node.textContent = text.slice(0, lastSlash) + text.slice(cursorOffset);
                                let range = document.createRange();
                                range.setStart(node, lastSlash);
                                range.collapse(true);
                                selection.removeAllRanges();
                                selection.addRange(range);
                            }
                        }
                    }

                    this.showSlashMenu = false;

                    switch(cmd.action) {
                        case 'normal': this.insertHeading('p'); break;
                        case 'heading1': this.insertHeading('h1'); break;
                        case 'heading2': this.insertHeading('h2'); break;
                        case 'heading3': this.insertHeading('h3'); break;
                        case 'project': this.insertProjectBlock(); break;
                        case 'checklist': this.insertChecklist(); break;
                        case 'bullet': this.exec('insertUnorderedList'); break;
                        case 'numbered': this.exec('insertOrderedList'); break;
                        case 'quote': this.insertQuote(); break;
                        case 'table': this.insertTable(); break;
                        case 'divider': this.insertDivider(); break;
                        case 'notelink': this.openNoteLinkModal(); break;
                        case 'drivelink': this.promptInsertDriveLink(); break;
                        case 'timestamp': this.insertTimestamp(); break;
                    }
                },

                promptInsertDriveLink() {
                    let url = prompt('Masukkan URL Google Drive, Google Docs, atau Link File PDF:');
                    if (!url) return;
                    url = url.trim();
                    if (!url.startsWith('http://') && !url.startsWith('https://')) {
                        url = 'https://' + url;
                    }
                    let title = prompt('Nama / Judul Dokumen (opsional):') || '';
                    this.insertDriveLinkCard(url, title.trim());
                },

                insertDriveLinkCard(url, customTitle = '') {
                    this.saveSnapshot();
                    if (this.$refs.editor) {
                        this.$refs.editor.focus();
                    }
                    let isDocs = /docs\.google\.com/i.test(url);
                    let isSheets = /sheets\.google\.com/i.test(url);
                    let isDrive = /drive\.google\.com/i.test(url);
                    let isPdf = /\.pdf(?:\?[^\s]*)?$/i.test(url);

                    let icon = isPdf ? '📄' : (isSheets ? '📊' : (isDocs ? '📝' : '📁'));
                    let badgeText = isPdf ? 'PDF Document' : (isSheets ? 'Google Sheets' : (isDocs ? 'Google Docs' : 'Google Drive'));
                    let safeUrl = this.escapeHtml(url);
                    let displayTitle = customTitle || (isPdf ? 'Dokumen PDF' : badgeText);
                    let safeTitle = this.escapeHtml(displayTitle);

                    let cardHtml = `<div class="journal-external-link-card my-3 p-3.5 rounded-2xl bg-blue-50/70 dark:bg-gray-800/90 border border-blue-200/80 dark:border-blue-900/50 flex items-center justify-between gap-3 select-none not-prose" contenteditable="false">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shrink-0 shadow-xs border border-gray-100 dark:border-gray-600">
                                ${icon}
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs sm:text-sm font-black text-gray-900 dark:text-white truncate">${safeTitle}</div>
                                <div class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold truncate flex items-center gap-1">
                                    <span>${badgeText}</span>
                                    <span class="opacity-40">•</span>
                                    <span class="text-gray-400 dark:text-gray-500 truncate max-w-[200px]">${safeUrl}</span>
                                </div>
                            </div>
                        </div>
                        <a href="${safeUrl}" target="_blank" rel="noopener noreferrer" tabindex="-1" class="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shrink-0 transition-all flex items-center gap-1 shadow-sm cursor-pointer">
                            <span>Buka</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div><p><br></p>`;

                    document.execCommand('insertHTML', false, cardHtml);
                    this.saveSnapshot();
                    this.handleInput();
                },

                escapeHtml(str) {
                    let div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                },

                handlePaste(e) {
                    e.preventDefault();
                    let html = e.clipboardData ? e.clipboardData.getData('text/html') : '';
                    let text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';

                    if (text && /^(https?:\/\/(?:drive|docs|sheets|slides)\.google\.com\/[^\s]+|https?:\/\/[^\s]+\.pdf(?:\?[^\s]*)?)$/i.test(text.trim())) {
                        this.insertDriveLinkCard(text.trim());
                        return;
                    }

                    if (html) {
                        try {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');

                            // 1. Remove script, style, meta, link, object, embed
                            doc.querySelectorAll('script, style, meta, link, object, embed').forEach(el => el.remove());

                            // 2. Unwrap all <font> tags (preserve inner content)
                            doc.querySelectorAll('font').forEach(font => {
                                const parent = font.parentNode;
                                if (parent) {
                                    while (font.firstChild) {
                                        parent.insertBefore(font.firstChild, font);
                                    }
                                    parent.removeChild(font);
                                }
                            });

                            // 3. Strip destructive attributes (face, color, size, class, id) and inline styles breaking theme
                            doc.querySelectorAll('*').forEach(el => {
                                el.removeAttribute('face');
                                el.removeAttribute('color');
                                el.removeAttribute('size');
                                el.removeAttribute('class');
                                el.removeAttribute('id');

                                if (el.hasAttribute('style')) {
                                    let style = el.getAttribute('style');
                                    style = style.replace(/(?:font-family|font-size|line-height|background|color)\s*:[^;]+;?/gi, '').trim();
                                    if (style) {
                                        el.setAttribute('style', style);
                                    } else {
                                        el.removeAttribute('style');
                                    }
                                }
                            });

                            let cleaned = doc.body.innerHTML.trim();
                            // Clean broken font remnants (e.g. #101010" face="...")
                            cleaned = cleaned.replace(/#?[0-9a-fA-F]{3,8}"\s+face="[^"]*">/gi, '');
                            cleaned = cleaned.replace(/(?:color=)?"#[0-9a-fA-F]{3,8}"\s+face="[^"]*">/gi, '');

                            if (cleaned) {
                                document.execCommand('insertHTML', false, cleaned);
                                this.handleInput();
                                return;
                            }
                        } catch (err) {
                            console.error('Error cleaning pasted HTML:', err);
                        }
                    }

                    // Fallback: If plain text, convert markdown headings/lists to clean HTML blocks
                    if (text) {
                        let lines = text.split(/\r?\n/);
                        let formatted = lines.map(line => {
                            let trimmed = line.trim();
                            if (!trimmed) return '<p><br></p>';
                            if (trimmed.startsWith('### ')) return `<h3>${this.escapeHtml(trimmed.slice(4))}</h3>`;
                            if (trimmed.startsWith('## ')) return `<h2>${this.escapeHtml(trimmed.slice(3))}</h2>`;
                            if (trimmed.startsWith('# ')) return `<h2>${this.escapeHtml(trimmed.slice(2))}</h2>`;
                            if (/^-\s+/.test(trimmed)) return `<ul><li>${this.escapeHtml(trimmed.replace(/^-\s+/, ''))}</li></ul>`;
                            if (/^\d+\.\s+/.test(trimmed)) return `<ol><li>${this.escapeHtml(trimmed.replace(/^\d+\.\s+/, ''))}</li></ol>`;
                            return `<p>${this.escapeHtml(line)}</p>`;
                        }).join('');

                        document.execCommand('insertHTML', false, formatted);
                        this.handleInput();
                    }
                }
            };
        };
        if (window.Alpine) {
            window.Alpine.data('notionJournalEditor', window.notionJournalEditor);
        } else {
            document.addEventListener('alpine:init', () => {
                Alpine.data('notionJournalEditor', window.notionJournalEditor);
            });
        }
    </script>
</div>
