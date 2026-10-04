<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header bell's pop-up (resources/js/notifications-popup.js). "View all" stays in the pop-up and reads the same
 * feed with the Notifications page's tabs, search, type filter and pages; staff can send, edit and delete from it.
 */
class NotificationsPopupTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(): User
    {
        return User::factory()->farmer()->create();
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO]);
    }

    private function note(User $user, string $title, array $extra = []): Notification
    {
        return Notification::query()->create($extra + ['user_id' => $user->id, 'title' => $title, 'message' => 'Body of '.$title, 'type' => 'Announcement', 'is_read' => false]);
    }

    public function test_the_feed_keeps_its_default_shape_for_the_bell(): void
    {
        $farmer = $this->farmer();
        $this->note($farmer, 'First');
        $this->note($farmer, 'Second', ['is_read' => true]);
        $this->note($this->farmer(), 'Someone else');

        $this->actingAs($farmer)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJson(['ok' => true, 'unread' => 1, 'total' => 2, 'filtered' => 2, 'page' => 1, 'last_page' => 1, 'can_manage' => false, 'store_url' => null, 'form' => null])
            ->assertJsonCount(2, 'items')
            ->assertJsonMissingPath('items.0.destroy_url');

        $this->actingAs($farmer)->getJson(route('notifications.feed', ['filter' => 'unread']))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'First');
    }

    public function test_view_all_can_page_search_and_filter_the_feed(): void
    {
        $farmer = $this->farmer();
        foreach (range(1, 12) as $i) {
            $this->note($farmer, 'Update '.$i, ['is_read' => $i > 9]);
        }
        $this->note($farmer, 'Typhoon signal raised', ['type' => 'Warning']);   // the newest: listed first

        $this->actingAs($farmer)->getJson(route('notifications.feed', ['per_page' => 10]))
            ->assertOk()->assertJson(['filtered' => 13, 'page' => 1, 'last_page' => 2, 'from' => 1, 'to' => 10])->assertJsonCount(10, 'items')
            ->assertJsonPath('items.0.title', 'Typhoon signal raised')->assertJsonPath('items.1.title', 'Update 12');

        $this->actingAs($farmer)->getJson(route('notifications.feed', ['per_page' => 10, 'page' => 2]))
            ->assertOk()->assertJson(['page' => 2, 'from' => 11, 'to' => 13])->assertJsonCount(3, 'items');

        $this->actingAs($farmer)->getJson(route('notifications.feed', ['filter' => 'read']))
            ->assertOk()->assertJson(['filtered' => 3])->assertJsonCount(3, 'items');

        $this->actingAs($farmer)->getJson(route('notifications.feed', ['search' => 'typhoon']))
            ->assertOk()->assertJson(['filtered' => 1, 'total' => 13])->assertJsonPath('items.0.title', 'Typhoon signal raised');

        $this->actingAs($farmer)->getJson(route('notifications.feed', ['type' => 'Warning']))
            ->assertOk()->assertJson(['filtered' => 1]);

        // An unknown type is ignored and per_page stays within 5-30.
        $this->actingAs($farmer)->getJson(route('notifications.feed', ['type' => 'Nope', 'per_page' => 500]))
            ->assertOk()->assertJson(['filtered' => 13])->assertJsonCount(13, 'items');
    }

    public function test_staff_get_the_manage_links_and_the_form_fields(): void
    {
        $mao = $this->mao();
        $note = $this->note($mao, 'For staff');

        $this->actingAs($mao)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJson(['can_manage' => true, 'store_url' => route('notifications.store'), 'form' => null])
            ->assertJsonPath('items.0.update_url', route('notifications.update', $note))
            ->assertJsonPath('items.0.destroy_url', route('notifications.destroy', $note))
            ->assertJsonPath('items.0.user_id', $mao->id);

        $names = collect($this->actingAs($mao)->getJson(route('notifications.feed', ['form' => 1]))->assertOk()->json('form'))->pluck('name')->all();
        $this->assertSame(['recipient_scope', 'user_id', 'title', 'message', 'type', 'category'], $names);

        // Farmers never receive the form, whatever they ask for.
        $this->actingAs($this->farmer())->getJson(route('notifications.feed', ['form' => 1]))->assertOk()->assertJson(['form' => null]);
    }

    public function test_staff_can_send_edit_and_delete_from_the_pop_up_without_a_redirect(): void
    {
        $mao = $this->mao();
        $farmer = $this->farmer();

        $this->actingAs($mao)->postJson(route('notifications.store'), ['recipient_scope' => 'specific', 'user_id' => $farmer->id, 'title' => 'Seed distribution', 'message' => 'Monday, 8 AM.', 'type' => 'Announcement', 'category' => 'general'])
            ->assertOk()->assertJson(['ok' => true, 'message' => 'Notification sent to 1 recipient(s).']);
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'title' => 'Seed distribution', 'is_read' => false]);

        $this->actingAs($mao)->postJson(route('notifications.store'), ['recipient_scope' => 'all', 'title' => '', 'message' => '', 'type' => 'Announcement'])
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'message']);

        $own = $this->note($mao, 'Draft title');
        $this->actingAs($mao)->putJson(route('notifications.update', $own), ['user_id' => $mao->id, 'title' => 'Final title', 'message' => 'Updated.', 'type' => 'Advisory', 'is_read' => 1])
            ->assertOk()->assertJson(['ok' => true, 'message' => 'Notification updated successfully.']);
        $this->assertDatabaseHas('notifications', ['id' => $own->id, 'title' => 'Final title', 'type' => 'Advisory', 'is_read' => true]);

        $this->actingAs($mao)->deleteJson(route('notifications.destroy', $own))
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('notifications', ['id' => $own->id]);
    }

    public function test_farmers_cannot_send_edit_or_delete(): void
    {
        $farmer = $this->farmer();
        $note = $this->note($farmer, 'Mine');

        $this->actingAs($farmer)->postJson(route('notifications.store'), ['recipient_scope' => 'all', 'title' => 'x', 'message' => 'y', 'type' => 'Announcement'])->assertForbidden();
        $this->actingAs($farmer)->putJson(route('notifications.update', $note), ['user_id' => $farmer->id, 'title' => 'x', 'message' => 'y', 'type' => 'Announcement'])->assertForbidden();
        $this->actingAs($farmer)->deleteJson(route('notifications.destroy', $note))->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $note->id, 'title' => 'Mine']);
    }

    public function test_the_notifications_page_still_works_with_redirects(): void
    {
        $mao = $this->mao();
        $note = $this->note($mao, 'Page copy');

        $this->actingAs($mao)->get(route('notifications.index'))->assertOk()->assertSee('Page copy');
        $this->actingAs($mao)->delete(route('notifications.destroy', $note))->assertRedirect(route('notifications.index'));
        $this->actingAs($mao)->post(route('notifications.store'), ['recipient_scope' => 'specific', 'user_id' => $mao->id, 'title' => 'From the page', 'message' => 'Hello.', 'type' => 'Announcement'])
            ->assertRedirect(route('notifications.index'));
    }

    public function test_view_all_is_a_link_to_the_page_that_the_script_keeps_in_the_pop_up(): void
    {
        $js = file_get_contents(resource_path('js/notifications-popup.js'));

        $this->assertStringContainsString('data-ic-notif-viewall', $js);
        $this->assertStringContainsString('href="${escapeHtml(state.indexUrl)}"', $js);          // still a real link for a new tab
        foreach (['data-ic-notif-search', 'data-ic-notif-page', 'data-ic-notif-read', 'data-ic-notif-edit', 'data-ic-notif-delete', 'data-ic-notif-new', "['read', 'Read']"] as $hook) {
            $this->assertStringContainsString($hook, $js, $hook);
        }
    }
}
