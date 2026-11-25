<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_edit_button_uses_safe_data_attributes_and_targets_the_update_route(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $branch = Branch::default();
        $manager = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $manager->syncRoles('Super Admin');
        $staff = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'O\'Neil "Staff"',
            'phone' => '0700123456',
            'is_active' => true,
        ]);
        $staff->syncRoles('Admin');

        $response = $this->actingAs($manager)->get(route('users.index'))->assertOk();
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        $buttons = (new \DOMXPath($document))->query('//button[@data-edit-user]');
        $button = null;
        foreach ($buttons as $candidate) {
            $candidateData = json_decode($candidate->getAttribute('data-user'), true, 512, JSON_THROW_ON_ERROR);
            if ($candidateData['email'] === $staff->email) {
                $button = $candidate;
                break;
            }
        }

        $this->assertNotNull($button);
        $this->assertFalse($button->hasAttribute('onclick'));
        $this->assertSame(route('users.update', $staff), $button->getAttribute('data-action'));
        $response->assertSee('data-user-action-confirm', false)
            ->assertSee('role="alertdialog"', false)
            ->assertSee('Confirm user changes')
            ->assertSee('Confirm user deletion');
        $this->assertSame([
            'name' => 'O\'Neil "Staff"',
            'email' => $staff->email,
            'phone' => '0700123456',
            'role' => 'Admin',
            'is_active' => true,
            'branch_id' => $branch->id,
        ], $candidateData);

        $this->get('/locale/prs')->assertRedirect();
        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('تأیید تغییرات کاربر')
            ->assertSee('تأیید حذف کاربر');

        $this->get('/locale/ps')->assertRedirect();
        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('د کارن بدلونونه تایید کړئ')
            ->assertSee('د کارن ړنګول تایید کړئ');
    }
}
