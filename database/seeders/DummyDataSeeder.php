<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if main user exists, else create one
        $mainUser = \App\Models\User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'xp' => 1250,
                'level' => 12,
                'monthly_xp' => 1250,
                'longest_streak' => 5,
                'current_streak' => 2,
            ]
        );

        // Create 10 dummy users
        $users = \App\Models\User::factory(10)->create([
            'xp' => rand(100, 3000),
            'level' => rand(1, 30),
            'monthly_xp' => rand(100, 3000),
            'longest_streak' => rand(1, 20),
            'current_streak' => rand(0, 10),
            'monthly_xp_reset_at' => now(),
        ]);

        // Add some accepted friends
        for ($i = 0; $i < 4; $i++) {
            \App\Models\Friendship::create([
                'user_id' => $mainUser->id,
                'friend_id' => $users[$i]->id,
                'status' => 'accepted'
            ]);
            
            // Add some dummy chat messages
            \App\Models\Message::create([
                'sender_id' => $users[$i]->id,
                'receiver_id' => $mainUser->id,
                'content' => 'Hey! How are you?',
                'is_read' => false
            ]);
            \App\Models\Message::create([
                'sender_id' => $mainUser->id,
                'receiver_id' => $users[$i]->id,
                'content' => 'I am doing great, working on some tasks.',
                'is_read' => true
            ]);
        }

        // Add some pending requests to the main user
        for ($i = 4; $i < 7; $i++) {
            \App\Models\Friendship::create([
                'user_id' => $users[$i]->id,
                'friend_id' => $mainUser->id,
                'status' => 'pending'
            ]);
        }

        // Add some dummy posts
        foreach ($users as $user) {
            \App\Models\Post::create([
                'user_id' => $user->id,
                'content' => 'Just finished a ' . rand(1, 4) . ' hour pomodoro session! Feeling productive 🚀',
            ]);
        }
    }
}
