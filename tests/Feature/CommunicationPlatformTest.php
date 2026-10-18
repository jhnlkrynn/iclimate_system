<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_people_directory_is_paginated_safe_and_searchable(): void
    {
        $farmer = User::factory()->farmer()->create(['name' => 'Current Farmer']);
        $farmer->farmerProfile()->create([
            'full_name' => $farmer->name,
            'barangay' => 'Kapito',
            'farm_type' => 'Irrigated',
        ]);
        User::factory()->maoPersonnel()->create(['name' => 'Municipal Agriculturist']);
        User::factory()->itExpert()->create(['name' => 'IT Support']);
        $kapito = User::factory()->farmer()->create(['name' => 'Kapito Farmer', 'barangay' => null]);
        $kapito->farmerProfile()->create([
            'full_name' => $kapito->name,
            'barangay' => 'Kapito',
            'farm_type' => 'Irrigated',
        ]);
        User::factory()->farmer()->count(29)->create();
        User::factory()->farmer()->create(['name' => 'Inactive Farmer', 'status' => User::STATUS_INACTIVE]);

        $response = $this->actingAs($farmer)->getJson(route('messages.people'));

        $response->assertOk()->assertJsonPath('meta.per_page', 30);
        $people = $response->json('people');
        $this->assertCount(30, $people);
        $this->assertTrue(collect($people)->every(fn (array $person): bool => ! in_array('email', array_keys($person), true)));
        $this->assertTrue(collect($people)->every(fn (array $person): bool => (int) $person['id'] !== $farmer->id));
        $this->assertFalse(collect($people)->contains('name', 'Inactive Farmer'));

        $search = $this->actingAs($farmer)->getJson(route('messages.people', ['q' => 'kapito']))
            ->assertOk();
        $this->assertSame('Kapito', $search->json('people.0.barangay'));
        $this->assertArrayNotHasKey('contact_number', $search->json('people.0'));
    }

    public function test_mao_can_publish_feed_post_with_media(): void
    {
        Storage::fake('public');
        $mao = User::factory()->maoPersonnel()->create();

        $response = $this->actingAs($mao)->post(route('community-feed.store'), [
            'title' => 'Seed Distribution Schedule',
            'body' => 'Distribution starts Monday at the municipal agriculture office.',
            'category' => 'Program',
            'visibility' => 'All Farmers',
            'attachments' => [
                UploadedFile::fake()->create('schedule.pdf', 120, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect(route('community-feed.index'));
        $this->assertDatabaseHas('feed_posts', ['title' => 'Seed Distribution Schedule']);
        $this->assertDatabaseHas('feed_media', ['media_type' => 'file', 'original_name' => 'schedule.pdf']);

        $post = FeedPost::query()->with('media')->firstOrFail();
        Storage::disk('public')->assertExists($post->media->first()->path);
    }

    public function test_uploaded_image_uses_persistent_public_path_and_renders_for_staff_and_farmer(): void
    {
        Storage::fake('public');
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $image = UploadedFile::fake()->image('field.png', 80, 60);

        $this->actingAs($mao)->post(route('community-feed.store'), [
            'title' => 'Field image update',
            'body' => 'The rice field is ready.',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
            'attachments' => [$image],
        ])->assertRedirect();

        $post = FeedPost::query()->with('media')->firstOrFail();
        $media = $post->media->sole();
        $this->assertSame('image', $media->media_type);
        $this->assertStringStartsWith('community-feed/', $media->path);
        $this->assertStringNotContainsString(base_path(), $media->path);
        Storage::disk('public')->assertExists($media->path);

        $url = $media->url();
        $this->assertNotNull($url);
        $this->actingAs($mao)->get(route('community-feed.index'))->assertOk()->assertSee($url, false);
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->assertSee($url, false);
    }

    public function test_edit_without_upload_keeps_image_and_replacement_removes_old_file(): void
    {
        Storage::fake('public');
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->post(route('community-feed.store'), [
            'title' => 'Original image', 'body' => 'Original body', 'category' => 'Update', 'visibility' => 'All Farmers',
            'attachments' => [UploadedFile::fake()->image('old.jpg')],
        ])->assertRedirect();

        $post = FeedPost::query()->with('media')->firstOrFail();
        $oldPath = $post->media->sole()->path;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($mao)->patch(route('community-feed.update', $post), [
            'title' => 'Edited text', 'body' => 'Edited body', 'category' => 'Update', 'visibility' => 'All Farmers',
        ])->assertRedirect();
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($mao)->patch(route('community-feed.update', $post), [
            'title' => 'Replaced image', 'body' => 'Edited body', 'category' => 'Update', 'visibility' => 'All Farmers',
            'attachments' => [UploadedFile::fake()->image('new.webp')],
        ])->assertRedirect();

        $newPath = $post->fresh()->media->sole()->path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        $this->actingAs($mao)->delete(route('community-feed.destroy', $post))->assertRedirect();
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_missing_legacy_attachment_has_no_broken_image(): void
    {
        Storage::fake('public');
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $post = FeedPost::query()->create([
            'user_id' => $mao->id, 'title' => 'Legacy image', 'body' => 'Old post', 'category' => 'Announcement', 'visibility' => 'All Farmers',
        ]);
        $post->media()->create([
            'path' => 'community-feed/deleted-image.jpg', 'original_name' => 'deleted-image.jpg', 'mime_type' => 'image/jpeg', 'media_type' => 'image', 'size' => 10,
        ]);

        $staffHtml = $this->actingAs($mao)->get(route('community-feed.index'))->assertOk()->assertSee('Attachment unavailable')->getContent();
        $farmerHtml = $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->assertSee('Attachment unavailable')->getContent();
        $this->assertStringNotContainsString('community-feed/deleted-image.jpg', $staffHtml);
        $this->assertStringNotContainsString('community-feed/deleted-image.jpg', $farmerHtml);
    }

    public function test_invalid_feed_attachment_is_rejected(): void
    {
        Storage::fake('public');
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->from(route('community-feed.index'))->post(route('community-feed.store'), [
            'title' => 'Invalid attachment', 'body' => 'Should not publish.', 'category' => 'Update', 'visibility' => 'All Farmers',
            'attachments' => [UploadedFile::fake()->create('payload.txt', 10, 'text/plain')],
        ])->assertRedirect(route('community-feed.index'))->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseMissing('feed_posts', ['title' => 'Invalid attachment']);
    }

    /** Farmers read MAO posts as read-only Announcements (2026-10); reactions and comments stay with MAO and IT. */
    public function test_farmers_cannot_react_or_comment_but_staff_still_can(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $post = FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'Training Activity',
            'body' => 'Rice pest management training this Friday.',
            'category' => 'Training',
            'visibility' => 'All Farmers',
        ]);

        $this->actingAs($farmer)->post(route('community-feed.reactions.store', $post), [
            'type' => 'Helpful',
        ])->assertRedirect(route('planting-advisories.index').'#announcements');

        $this->actingAs($farmer)->post(route('community-feed.comments.store', $post), [
            'body' => 'Can I bring my neighbor?',
        ])->assertRedirect(route('planting-advisories.index').'#announcements');

        $this->assertDatabaseMissing('feed_reactions', ['feed_post_id' => $post->id, 'user_id' => $farmer->id]);
        $this->assertDatabaseMissing('feed_comments', ['feed_post_id' => $post->id, 'user_id' => $farmer->id]);

        $this->actingAs($mao)->post(route('community-feed.comments.store', $post), [
            'body' => 'Bring your RSBSA ID.',
        ])->assertRedirect();
        $this->assertDatabaseHas('feed_comments', ['feed_post_id' => $post->id, 'user_id' => $mao->id]);
    }

    public function test_mao_can_edit_archive_and_delete_own_feed_post(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $post = FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'Original Title',
            'body' => 'Original community update.',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $this->actingAs($mao)->patch(route('community-feed.update', $post), [
            'title' => 'Updated Title',
            'body' => 'Updated community instructions.',
            'category' => 'Advisory',
            'visibility' => 'All Users',
        ])->assertRedirect();

        $this->assertDatabaseHas('feed_posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'body' => 'Updated community instructions.',
            'category' => 'Advisory',
            'visibility' => 'All Users',
        ]);

        $this->actingAs($mao)
            ->patch(route('community-feed.archive', $post))
            ->assertRedirect();

        $this->assertNotNull($post->fresh()->archived_at);

        $this->actingAs($mao)
            ->delete(route('community-feed.destroy', $post))
            ->assertRedirect();

        $this->assertDatabaseMissing('feed_posts', ['id' => $post->id]);
    }

    public function test_archived_feed_posts_are_hidden_from_farmers_and_closed_for_interactions(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $post = FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'Archived Training',
            'body' => 'This should no longer show to farmers.',
            'category' => 'Training',
            'visibility' => 'All Farmers',
            'archived_at' => now(),
        ]);

        // Farmers read posts as Announcements on the Advisories page; an archived post is not among them.
        $this->actingAs($farmer)
            ->get(route('planting-advisories.index'))
            ->assertOk()
            ->assertDontSee('Archived Training');

        // Farmers cannot react or comment on any post (2026-10); staff still cannot touch an archived one.
        $this->actingAs($farmer)
            ->post(route('community-feed.reactions.store', $post), ['type' => 'Helpful'])
            ->assertRedirect(route('planting-advisories.index').'#announcements');
        $this->actingAs($farmer)
            ->post(route('community-feed.comments.store', $post), ['body' => 'Can I still join?'])
            ->assertRedirect(route('planting-advisories.index').'#announcements');
        $this->actingAs($mao)
            ->post(route('community-feed.comments.store', $post), ['body' => 'Closed?'])
            ->assertForbidden();
        $this->assertDatabaseMissing('feed_comments', ['feed_post_id' => $post->id]);
    }

    public function test_farmer_and_mao_can_exchange_messages(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();

        $response = $this->actingAs($farmer)->post(route('messages.store'), [
            'recipient_id' => $mao->id,
            'body' => 'Can I ask about the upcoming fertilizer program?',
        ]);

        $conversation = Conversation::query()->firstOrFail();
        $response->assertRedirect(route('messages.show', $conversation));

        $this->actingAs($mao)->post(route('messages.reply', $conversation), [
            'body' => 'Yes, please visit the MAO desk this week.',
        ])->assertRedirect(route('messages.show', $conversation));

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $farmer->id,
            'body' => 'Can I ask about the upcoming fertilizer program?',
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $mao->id,
            'body' => 'Yes, please visit the MAO desk this week.',
        ]);
    }

    public function test_message_search_returns_bounded_authorized_accounts_with_empty_and_partial_queries(): void
    {
        $farmer = User::factory()->farmer()->create();
        $recentMao = User::factory()->maoPersonnel()->create(['name' => 'Maria Santos', 'email' => 'maria@example.test']);
        $otherMao = User::factory()->maoPersonnel()->create(['name' => 'Mark Reyes']);
        $anotherFarmer = User::factory()->farmer()->create(['name' => 'Farmer Hidden']);
        $inactiveMao = User::factory()->maoPersonnel()->create(['name' => 'Inactive MAO', 'status' => User::STATUS_INACTIVE]);

        Conversation::query()->forceCreate([
            'participant_one_id' => min($farmer->id, $recentMao->id),
            'participant_two_id' => max($farmer->id, $recentMao->id),
            'last_message_at' => now(),
        ]);

        $empty = $this->actingAs($farmer)->getJson(route('messages.search'))
            ->assertOk()
            ->assertJsonPath('recipients.0.id', $recentMao->id);

        $emptyIds = collect($empty->json('recipients'))->pluck('id');
        $this->assertTrue($emptyIds->contains($otherMao->id));
        $this->assertTrue($emptyIds->contains($anotherFarmer->id));
        $this->assertFalse($emptyIds->contains($inactiveMao->id));
        $this->assertSame('MAO Personnel', $empty->json('recipients.0.role'));

        $this->actingAs($farmer)->getJson(route('messages.search', ['q' => 'SANT']))
            ->assertOk()
            ->assertJsonPath('recipients.0.id', $recentMao->id)
            ->assertJsonMissing(['id' => $otherMao->id]);
    }

    public function test_json_conversation_loading_and_idempotent_replies_are_supported(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $conversation = Conversation::query()->forceCreate([
            'participant_one_id' => min($farmer->id, $mao->id),
            'participant_two_id' => max($farmer->id, $mao->id),
            'last_message_at' => now(),
        ]);

        $payload = ['body' => 'Please confirm the seed schedule.', 'client_message_id' => 'client-message-1'];
        $first = $this->actingAs($farmer)->postJson(route('messages.reply', $conversation), $payload)->assertOk();
        $second = $this->actingAs($farmer)->postJson(route('messages.reply', $conversation), $payload)->assertOk();

        $first->assertJsonPath('message.body', $payload['body']);
        $second->assertJsonPath('message.id', $first->json('message.id'));
        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());

        $this->actingAs($mao)->getJson(route('messages.show', $conversation))
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('messages.0.body', $payload['body']);
    }

    public function test_message_json_endpoints_still_enforce_conversation_membership(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $outsider = User::factory()->farmer()->create();
        $conversation = Conversation::query()->forceCreate([
            'participant_one_id' => min($farmer->id, $mao->id),
            'participant_two_id' => max($farmer->id, $mao->id),
            'last_message_at' => now(),
        ]);

        $this->actingAs($outsider)->getJson(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('messages.poll', $conversation))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('messages.reply', $conversation), ['body' => 'No'])->assertForbidden();
    }

    public function test_message_attachments_reject_unsafe_file_types(): void
    {
        Storage::fake('public');

        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($farmer)->post(route('messages.store'), [
            'recipient_id' => $mao->id,
            'body' => 'Please review this attachment.',
            'attachment' => UploadedFile::fake()->create('payload.php', 5, 'application/x-php'),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('conversation_messages', 0);
    }
}
