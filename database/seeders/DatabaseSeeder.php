<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // --- USERS ---
        $admin = User::factory()->create([
            'name' => 'Adam Admin',
            'email' => 'adamadmin3@gmail.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Adam User',
            'email' => 'adamuser3@gmail.com',
            'password' => bcrypt('password'),
        ]);

        User::factory(10)->create();

        $allUsers = User::pluck('id')->toArray();

        // --- GROUPS ---
        $groups = [];
        for ($i = 0; $i < 5; $i++) {
            $group = Group::factory()->create([
                'owner_id' => 1,
            ]);

            $members = User::inRandomOrder()->limit(rand(2, 5))->pluck('id')->toArray();
            $group->users()->sync(array_unique([1, ...$members]));

            $groups[] = $group;
        }

        // --- PRIVATE MESSAGES & CONVERSATIONS ---
        // Create 500 private messages
        $privateMessages = [];

        for ($i = 0; $i < 500; $i++) {
            [$sender, $receiver] = collect($allUsers)->random(2)->values();

            $privateMessages[] = Message::factory()->create([
                'sender_id'       => $sender,
                'receiver_id'     => $receiver,
                'group_id'        => null,
                'conversation_id' => null, // assign later
            ]);
        }

        // Group messages by conversation (user pairs)
        $messagesByPair = collect($privateMessages)->groupBy(function ($msg) {
            return collect([$msg->sender_id, $msg->receiver_id])->sort()->implode('_');
        });

        $conversations = $messagesByPair->map(function ($grouped) {
            return Conversation::create([
                'user_id1'        => $grouped->first()->sender_id,
                'user_id2'        => $grouped->first()->receiver_id,
                'last_message_id' => $grouped->last()->id,
            ]);
        });

        // Update conversation_id for messages
        foreach ($conversations as $conversation) {
            Message::where([
                ['sender_id', $conversation->user_id1],
                ['receiver_id', $conversation->user_id2],
            ])->orWhere([
                ['sender_id', $conversation->user_id2],
                ['receiver_id', $conversation->user_id1],
            ])->update(['conversation_id' => $conversation->id]);
        }

        // --- GROUP MESSAGES ---
        foreach ($groups as $group) {
            $groupUserIds = $group->users->pluck('id')->toArray();
            $messageCount = rand(20, 50);

            for ($i = 0; $i < $messageCount; $i++) {
                Message::factory()->create([
                    'sender_id' => collect($groupUserIds)->random(),
                    'receiver_id' => null,
                    'group_id' => $group->id,
                    'conversation_id' => null,
                ]);
            }

            // Update group's last_message_id
            $lastMessage = Message::where('group_id', $group->id)->latest()->first();
            if ($lastMessage) {
                $group->update(['last_message_id' => $lastMessage->id]);
            }
        }
    }
}
