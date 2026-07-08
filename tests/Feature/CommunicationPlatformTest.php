<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationPlatformTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_farmer_can_react_and_comment_on_feed_post(): void
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
        ])->assertRedirect();

        $this->actingAs($farmer)->post(route('community-feed.comments.store', $post), [
            'body' => 'Can I bring my neighbor?',
        ])->assertRedirect();

        $this->assertDatabaseHas('feed_reactions', [
            'feed_post_id' => $post->id,
            'user_id' => $farmer->id,
            'type' => 'Helpful',
        ]);
        $this->assertDatabaseHas('feed_comments', [
            'feed_post_id' => $post->id,
            'user_id' => $farmer->id,
            'body' => 'Can I bring my neighbor?',
        ]);
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

        $this->actingAs($farmer)
            ->get(route('community-feed.index'))
            ->assertOk()
            ->assertDontSee('Archived Training');

        $this->actingAs($farmer)
            ->post(route('community-feed.reactions.store', $post), ['type' => 'Helpful'])
            ->assertForbidden();

        $this->actingAs($farmer)
            ->post(route('community-feed.comments.store', $post), ['body' => 'Can I still join?'])
            ->assertForbidden();
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
}
