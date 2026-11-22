<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarInitialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initials_handle_single_name_multiple_names_and_accents(): void
    {
        $single = new User(['name' => 'John']);
        $this->assertSame('J', $single->initials());

        $two = new User(['name' => 'John Luke']);
        $this->assertSame('JL', $two->initials());

        $multi = new User(['name' => 'John Luke Reynon']);
        $this->assertSame('JR', $multi->initials());

        $accentedSingle = new User(['name' => 'Élodie']);
        $this->assertSame('É', $accentedSingle->initials());

        $accentedMulti = new User(['name' => 'José María Álvarez']);
        $this->assertSame('JÁ', $accentedMulti->initials());

        $spaced = new User(['name' => '  Ana   Cruz  ']);
        $this->assertSame('AC', $spaced->initials());
    }

    public function test_topbar_shows_initials_fallback_and_avatar_image_when_set(): void
    {
        Storage::fake('public');

        $farmer = User::factory()->create([
            'name' => 'John Luke',
            'role' => User::ROLE_FARMER,
            'email_verified_at' => now(),
            'avatar_path' => null,
        ]);

        $htmlWithoutAvatar = $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Account menu for John Luke"', $htmlWithoutAvatar);
        $this->assertStringContainsString('ic-user-avatar-initials">JL<', $htmlWithoutAvatar);
        $this->assertStringNotContainsString('<circle cx="12" cy="8.5"', $htmlWithoutAvatar);

        $farmer->forceFill(['avatar_path' => 'avatars/john.jpg'])->save();

        $htmlWithAvatar = $this->actingAs($farmer->fresh())
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/storage/avatars/john.jpg', $htmlWithAvatar);
        $this->assertStringNotContainsString('ic-user-avatar-initials', $htmlWithAvatar);
    }
}
