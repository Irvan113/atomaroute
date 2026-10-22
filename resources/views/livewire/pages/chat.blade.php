<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Message;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component {
    public $friend_id;
    public $friend;
    public $messageContent = '';
    
    public function mount($friend_id)
    {
        $this->friend_id = $friend_id;
        $this->friend = User::findOrFail($friend_id);
        
        // Mark all messages from friend to auth user as read
        Message::where('sender_id', $this->friend_id)
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
    
    public function sendMessage()
    {
        $this->validate([
            'messageContent' => 'required|string|max:1000'
        ]);
        
        Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $this->friend_id,
            'content' => $this->messageContent,
        ]);
        
        $this->reset('messageContent');
        $this->dispatch('message-sent');
    }
    
    #[Computed]
    public function chatMessages()
    {
        $userId = auth()->id();
        return Message::where(function($q) use ($userId) {
                $q->where('sender_id', $userId)->where('receiver_id', $this->friend_id);
            })
            ->orWhere(function($q) use ($userId) {
                $q->where('sender_id', $this->friend_id)->where('receiver_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }
}; ?>

<div class="h-[calc(100vh-4rem)] md:h-screen flex flex-col max-w-4xl mx-auto w-full bg-white dark:bg-gray-900 border-x border-gray-200 dark:border-gray-800" wire:poll.5s.visible>
    <!-- Header -->
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-white/80 dark:bg-gray-900/80 backdrop-blur-md sticky top-0 z-10">
        <div class="flex items-center space-x-3">
            <a href="{{ route('social') }}" wire:navigate class="p-2 -ml-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white rounded-full transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <img src="{{ $friend->avatar_url }}" alt="Avatar" class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700">
            <div>
                <div class="font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $friend->name }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Lvl {{ $friend->level }} • {{ $friend->longest_streak }} max streak</div>
            </div>
        </div>
    </div>
    
    <!-- Chat Area -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-messages" x-data x-init="
        $el.scrollTop = $el.scrollHeight;
        let observer = new MutationObserver(() => { $el.scrollTop = $el.scrollHeight; });
        observer.observe($el, { childList: true, subtree: true });
    ">
        @forelse($this->chatMessages as $msg)
            @php
                $isMe = $msg->sender_id === auth()->id();
            @endphp
            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%] {{ $isMe ? 'bg-indigo-600 text-white rounded-l-2xl rounded-tr-2xl' : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-r-2xl rounded-tl-2xl' }} px-4 py-2.5 shadow-sm">
                    <div class="whitespace-pre-wrap break-words text-[15px]">{{ $msg->content }}</div>
                    <div class="text-[10px] text-right mt-1 {{ $isMe ? 'text-indigo-200' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ $msg->created_at->format('g:i A') }}
                    </div>
                </div>
            </div>
        @empty
            <div class="h-full flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                <div class="text-4xl mb-4">💬</div>
                <p class="font-medium">No messages yet.</p>
                <p class="text-sm mt-1">Say hi to start the conversation!</p>
            </div>
        @endforelse
    </div>
    
    <!-- Input Area -->
    <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <form wire:submit="sendMessage" class="flex items-end gap-2 relative">
            <textarea 
                wire:model="messageContent"
                rows="1" 
                placeholder="Message..." 
                class="w-full bg-gray-100 dark:bg-gray-800 border-transparent focus:border-indigo-500 focus:ring-0 rounded-2xl px-4 py-3 resize-none text-[15px] text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 max-h-32"
                x-data 
                x-init="$el.focus()"
                @keydown.enter.prevent="if(!$event.shiftKey) { $wire.sendMessage() }"
                @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
            ></textarea>
            
            <button type="submit" class="shrink-0 h-12 w-12 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white flex items-center justify-center shadow-sm transition-transform hover:scale-105 active:scale-95 disabled:opacity-50" wire:loading.attr="disabled" {{ empty($messageContent) ? 'disabled' : '' }}>
                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
        </form>
    </div>
    
    <script>
        document.addEventListener('livewire:initialized', () => {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            Livewire.on('message-sent', () => {
                setTimeout(() => {
                    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
                }, 50);
            });
        });
    </script>
</div>
