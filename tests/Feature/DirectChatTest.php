<?php

namespace Tests\Feature;

use App\Models\ThreadMessageAttachment;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * General person-to-person chat (direct messages). The plain messaging case
 * the threads system was built to also serve — a subjectless thread with
 * `member` seats — reusing the same posting rules and evidence attachments as
 * the dispute room and operation chat, with no conduct charter.
 */
class DirectChatTest extends TestCase
{
    use DatabaseTransactions;

    private User $alice;
    private User $bob;
    private User $carol;

    protected function setUp(): void
    {
        parent::setUp();

        $users = User::query()->orderBy('id')->limit(3)->get();

        if ($users->count() < 3) {
            $this->markTestSkipped('Needs at least three users.');
        }

        [$this->alice, $this->bob, $this->carol] = [$users[0], $users[1], $users[2]];
    }

    protected function tearDown(): void
    {
        $uploads = app(ImageUploadService::class);
        foreach (ThreadMessageAttachment::query()->pluck('path') as $path) {
            $uploads->delete($path);
        }
        parent::tearDown();
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('note.jpg', 64, 'image/jpeg');
    }

    public function test_starting_a_chat_is_deduped_to_one_thread(): void
    {
        Sanctum::actingAs($this->alice);

        $first = $this->postJson('/api/v2/chats', ['user_id' => $this->bob->id])
            ->assertCreated()
            ->json('data.id');

        // Starting again with the same person lands in the SAME conversation.
        $second = $this->postJson('/api/v2/chats', ['user_id' => $this->bob->id])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
    }

    public function test_you_cannot_chat_yourself_or_a_phantom(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson('/api/v2/chats', ['user_id' => $this->alice->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->postJson('/api/v2/chats', ['user_id' => 99999999])
            ->assertNotFound();
    }

    public function test_a_party_posts_with_an_attachment_and_the_other_reads_it(): void
    {
        Sanctum::actingAs($this->alice);
        $threadId = $this->postJson('/api/v2/chats', ['user_id' => $this->bob->id])->json('data.id');

        // No conduct step at all.
        $this->postJson("/api/v2/chats/{$threadId}/messages", [
            'body' => 'hi bob',
            'attachments' => [$this->file()],
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonCount(1, 'data.attachments');

        Sanctum::actingAs($this->bob);
        $this->getJson("/api/v2/chats/{$threadId}")
            ->assertOk()
            ->assertJsonPath('data.0.is_mine', false)
            ->assertJsonCount(1, 'data.0.attachments');
    }

    public function test_a_non_participant_cannot_read_or_post(): void
    {
        Sanctum::actingAs($this->alice);
        $threadId = $this->postJson('/api/v2/chats', ['user_id' => $this->bob->id])->json('data.id');

        Sanctum::actingAs($this->carol);
        $this->getJson("/api/v2/chats/{$threadId}")->assertNotFound();
        $this->postJson("/api/v2/chats/{$threadId}/messages", ['body' => 'butting in'])->assertNotFound();
    }

    public function test_the_conversation_list_shows_the_other_party_and_unread_count(): void
    {
        Sanctum::actingAs($this->alice);
        $threadId = $this->postJson('/api/v2/chats', ['user_id' => $this->bob->id])->json('data.id');
        $this->postJson("/api/v2/chats/{$threadId}/messages", ['body' => 'you there?'])->assertCreated();

        // Bob has one unread; the list names Alice as the other party.
        Sanctum::actingAs($this->bob);
        $list = $this->getJson('/api/v2/chats')->assertOk();

        $row = collect($list->json('data'))->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['unread_count']);
        $this->assertSame((int) $this->alice->id, (int) $row['participants'][0]['user_id']);
        $this->assertFalse($row['last_message']['is_mine']);
    }
}
