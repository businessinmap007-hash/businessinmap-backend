<?php

namespace Tests\Feature;

use App\Models\ThreadMessage;
use App\Models\User;
use App\Services\DirectChatService;
use App\Services\ThreadService;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The admin/judge moderation view over every conversation, and the encryption
 * that makes "only the admin can read them" real:
 *  - message text is ciphertext at rest (a DB dump reveals nothing);
 *  - the merged screen is gated on the DISPUTES (judge) ability;
 *  - it decrypts for the judge who is allowed to read it.
 */
class AdminChatsTest extends TestCase
{
    use DatabaseTransactions;

    private User $alice;
    private User $bob;
    private int $threadId;
    private int $messageId;
    private string $secret = 'meet me at the usual place at 8';

    protected function setUp(): void
    {
        parent::setUp();

        $users = User::query()->orderBy('id')->limit(2)->get();
        if ($users->count() < 2) {
            $this->markTestSkipped('Needs two users.');
        }
        [$this->alice, $this->bob] = [$users[0], $users[1]];

        $thread = app(DirectChatService::class)->startWith($this->alice, (int) $this->bob->id);
        $message = app(ThreadService::class)->post(
            $thread,
            (int) $this->alice->id,
            $this->secret,
            [\Illuminate\Http\UploadedFile::fake()->create('evidence.jpg', 32, 'image/jpeg')]
        );

        $this->threadId = (int) $thread->id;
        $this->messageId = (int) $message->id;
    }

    protected function tearDown(): void
    {
        $storage = app(\App\Services\Media\ThreadAttachmentStorage::class);
        foreach (\App\Models\ThreadMessageAttachment::query()->pluck('path') as $path) {
            $storage->delete($path);
        }
        parent::tearDown();
    }

    private function judge(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::DISPUTES] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    public function test_message_text_is_encrypted_at_rest(): void
    {
        $raw = DB::table('thread_messages')->where('id', $this->messageId)->value('body');

        // The stored column is ciphertext, not the words.
        $this->assertNotSame($this->secret, $raw);
        $this->assertStringNotContainsString('usual place', (string) $raw);
        // …but it decrypts back, and the model reads it transparently.
        $this->assertSame($this->secret, Crypt::decryptString($raw));
        $this->assertSame($this->secret, (string) ThreadMessage::findOrFail($this->messageId)->body);
    }

    public function test_a_judge_sees_the_merged_list_and_the_decrypted_conversation(): void
    {
        $judge = $this->judge();

        $this->actingAs($judge)->get('/admin/chats')
            ->assertOk()
            ->assertSee('/admin/chats/' . $this->threadId); // its row's "view" link

        $this->actingAs($judge)->get("/admin/chats/{$this->threadId}")
            ->assertOk()
            ->assertSee($this->secret); // decrypted for the one allowed to read it
    }

    public function test_the_judge_can_stream_a_private_attachment_but_a_plain_admin_cannot(): void
    {
        $attachment = \App\Models\ThreadMessageAttachment::query()->latest('id')->firstOrFail();

        // The file is not public — only the authed admin route reaches it.
        $this->assertFileDoesNotExist(public_path($attachment->path));

        $this->actingAs($this->judge())
            ->get("/admin/chat-attachments/{$attachment->id}")
            ->assertOk();
    }

    public function test_an_admin_without_the_judge_ability_is_forbidden(): void
    {
        // A fresh admin granted only ACCESS — lacks DISPUTES, so the chats
        // screen is closed to them (403 whether or not ACCESS resolved).
        $plain = new User();
        $plain->name = 'Plain Admin';
        $plain->email = 'plain-' . uniqid() . '@example.test';
        $plain->phone = '0155' . random_int(1000000, 9999999);
        $plain->password = 'secret-password';
        $plain->type = User::TYPE_ADMIN;
        $plain->api_token = Str::random(80);
        $plain->save();

        \Bouncer::allow($plain)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        $this->actingAs($plain)->get('/admin/chats')->assertForbidden();
        $this->actingAs($plain)->get("/admin/chats/{$this->threadId}")->assertForbidden();
    }
}
