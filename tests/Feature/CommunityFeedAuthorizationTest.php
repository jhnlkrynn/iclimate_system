<?php

namespace Tests\Feature;

use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author): FeedPost
    {
        return FeedPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Original title',
            'body' => 'Original body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);
    }

    public function test_a_mao_user_cannot_update_another_mao_users_post(): void
    {
        $author = User::factory()->maoPersonnel()->create();
        $other = User::factory()->maoPersonnel()->create();
        $post = $this->makePost($author);

        $response = $this->actingAs($other)->patch(route('community-feed.update', $post), [
            'title' => 'Hijacked title',
            'body' => 'Hijacked body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $response->assertForbidden();
    }

    public function test_a_mao_user_can_update_their_own_post(): void
    {
        $author = User::factory()->maoPersonnel()->create();
        $post = $this->makePost($author);

        $response = $this->actingAs($author)->patch(route('community-feed.update', $post), [
            'title' => 'My updated title',
            'body' => 'My updated body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $response->assertRedirect();
    }

    public function test_an_it_expert_can_update_another_users_post(): void
    {
        $author = User::factory()->maoPersonnel()->create();
        $itExpert = User::factory()->itExpert()->create();
        $post = $this->makePost($author);

        $response = $this->actingAs($itExpert)->patch(route('community-feed.update', $post), [
            'title' => 'Moderated title',
            'body' => 'Moderated body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $response->assertRedirect();
    }
}
