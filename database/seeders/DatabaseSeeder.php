<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use App\Models\SubTask;
use App\Models\Journal;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\Like;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DummyDataSeeder::class,
        ]);

        // 1. Create Main User for Testing
        $mainUser = User::firstOrCreate(
            ['email' => 'atoma@example.com'],
            [
                'name' => 'Atoma Master',
                'password' => Hash::make('password'),
                'xp' => 1250,
                'level' => 13,
                'current_streak' => 5,
                'longest_streak' => 12,
                'streak_coupons' => 3,
                'coins' => 1250,
                'bio' => 'Building the future of productivity!',
            ]
        );

        // 2. Create Friends
        $friendNames = ['Alex Dev', 'Sarah Design', 'Budi Coder', 'Emma Writer'];
        $friends = [];
        foreach ($friendNames as $i => $name) {
            $friend = User::firstOrCreate(
                ['email' => 'friend' . $i . '@example.com'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'xp' => rand(100, 2000),
                    'level' => rand(2, 21),
                    'bio' => 'I love ATOMA!',
                    'current_streak' => rand(0, 10),
                ]
            );
            $friends[] = $friend;

            // Make them friends
            Friendship::firstOrCreate(['user_id' => $mainUser->id, 'friend_id' => $friend->id], ['status' => 'accepted']);
        }

        // 3. Create Tasks (Overdue, Today, Future, Recurring)
        $today = Carbon::today();
        
        // 3a. Overdue Task (Red Notification feature test)
        Task::firstOrCreate([
            'user_id' => $mainUser->id,
            'title' => 'Learn Laravel Livewire (Overdue)',
            'date' => $today->copy()->subDays(2),
            'is_completed' => false,
            'mode' => 'sistematis',
            'priority' => 'high',
            'category' => 'Study',
        ]);

        // 3b. Today Zen Task
        Task::firstOrCreate([
            'user_id' => $mainUser->id,
            'title' => 'Meditate for 10 minutes',
            'date' => $today,
            'is_completed' => false,
            'mode' => 'zen',
        ]);

        // 3c. Today Sistematis Task with Subtasks
        $sistematisTask = Task::firstOrCreate([
            'user_id' => $mainUser->id,
            'title' => 'Build ATOMA Features',
            'description' => 'Complete the required features for today.',
            'date' => $today,
            'start_time' => '09:00',
            'deadline' => '17:00',
            'is_completed' => false,
            'mode' => 'sistematis',
            'priority' => 'high',
            'category' => 'Work',
            'is_recurring' => true,
            'recurrence_pattern' => 'daily',
        ]);

        if ($sistematisTask->subTasks()->count() == 0) {
            $sistematisTask->subTasks()->create(['title' => 'Setup PWA', 'is_completed' => false]);
            $sistematisTask->subTasks()->create(['title' => 'Test Notifications', 'is_completed' => true]);
        }

        // 3d. Completed Task Today (For Journal testing)
        Task::firstOrCreate([
            'user_id' => $mainUser->id,
            'title' => 'Morning Workout',
            'date' => $today,
            'is_completed' => true,
            'mode' => 'zen',
        ]);

        // 4. Create Journals for Insights (Past 7 days)
        $moods = ['happy', 'happy', 'neutral', 'sad', 'happy', 'tired', 'happy'];
        for ($i = 0; $i < 7; $i++) {
            $journalDate = $today->copy()->subDays($i);
            Journal::firstOrCreate(
                ['user_id' => $mainUser->id, 'created_at' => $journalDate->startOfDay()],
                [
                    'title' => 'Reflection for ' . $journalDate->format('l'),
                    'content' => 'Today was a ' . $moods[$i] . ' day. I did some work and felt pretty good about it.',
                    'mood' => $moods[$i],
                    'tags' => ['daily', 'work', 'health'],
                    'updated_at' => $journalDate->startOfDay(),
                ]
            );
        }

        // 5. Create Social Posts and Likes
        foreach ($friends as $friend) {
            $post = Post::firstOrCreate(
                ['user_id' => $friend->id, 'content' => 'Just completed an awesome task using ATOMA!'],
                ['created_at' => now()->subHours(rand(1, 48))]
            );
            
            // Random likes
            if (rand(0, 1)) {
                Like::firstOrCreate(['user_id' => $mainUser->id, 'post_id' => $post->id]);
            }
        }
        
        $mainUserPost = Post::firstOrCreate(
            ['user_id' => $mainUser->id, 'content' => 'Loving the new Win-Win Journal feature! 🎉'],
            ['created_at' => now()->subMinutes(30)]
        );
        Like::firstOrCreate(['user_id' => $friends[0]->id, 'post_id' => $mainUserPost->id]);
    }
}
