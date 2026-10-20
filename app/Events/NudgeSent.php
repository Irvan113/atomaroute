<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class NudgeSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fromUserId;
    public $fromUserName;
    public $fromUserAvatar;
    public $toUserId;
    public $nudgeType;
    public $message;
    public $nudgeId;

    /**
     * Create a new event instance.
     */
    public function __construct(User $fromUser, $toUserId, $nudgeType = 'bacok', $message = null, $nudgeId = null)
    {
        $this->fromUserId = $fromUser->id;
        $this->fromUserName = $fromUser->name;
        $this->fromUserAvatar = $fromUser->avatar_url;
        $this->toUserId = is_object($toUserId) ? $toUserId->id : $toUserId;
        $this->nudgeType = $nudgeType;
        $this->message = $message;
        $this->nudgeId = $nudgeId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->toUserId),
        ];
    }
    
    public function broadcastAs()
    {
        return 'nudge.sent';
    }
}
