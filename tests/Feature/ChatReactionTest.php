<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use Tests\TestCase;

class ChatReactionTest extends TestCase
{
    public function test_chat_message_appends_reactions_summary_in_json(): void
    {
        $message = new ChatMessage([
            'sender_id' => 1,
            'recipient_id' => 2,
            'body' => 'Hello',
        ]);
        $message->setRelation('reactions', collect([
            (object) [
                'user_id' => 1,
                'emoji' => '💜',
                'user' => (object) ['id' => 1, 'name' => 'Alice', 'fullname' => 'Alice Smith', 'email' => 'alice@example.com'],
            ],
        ]));

        $array = $message->toArray();

        $this->assertArrayHasKey('reactions_summary', $array);
        $this->assertCount(1, $array['reactions_summary']);
        $this->assertSame('💜', $array['reactions_summary'][0]['emoji']);
        $this->assertSame(1, $array['reactions_summary'][0]['count']);
        $this->assertSame([1], $array['reactions_summary'][0]['user_ids']);
    }
}
