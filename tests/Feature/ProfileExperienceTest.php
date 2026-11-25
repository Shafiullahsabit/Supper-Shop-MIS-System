<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_and_cashier_profiles_include_system_version_and_logout_confirmation(): void
    {
        foreach (['Admin', 'Cashier'] as $role) {
            $user = User::factory()->create([
                'name' => $role.' User',
                'email' => strtolower(str_replace(' ', '.', $role)).'@example.test',
                'is_active' => true,
            ]);
            $user->assignRole($role);

            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertOk()
                ->assertSeeText($user->name)
                ->assertSeeText(__('System version'))
                ->assertSeeText('v'.config('app.version'))
                ->assertSee('class="sidebar-profile', false)
                ->assertSee('href="'.route('profile.edit').'"', false)
                ->assertSee('logoutConfirmation')
                ->assertSee(route('logout'), false);
        }
    }

    public function test_logout_endpoint_ends_the_authenticated_session(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_can_upload_a_profile_photo_visible_to_admin(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => UploadedFile::fake()->image('profile.png')->size(128),
            ])
            ->assertRedirect();

        $avatar = $user->fresh()->avatar;
        $this->assertNotNull($avatar);
        Storage::disk('public')->assertExists($avatar);

        $avatarUrl = '/storage/'.$avatar;
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($avatarUrl, false);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee($avatarUrl, false);
    }
}
