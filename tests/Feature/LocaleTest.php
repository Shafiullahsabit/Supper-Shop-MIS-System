<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_supported_locales_can_be_selected(): void
    {
        $this->get('/locale/prs')
            ->assertRedirect()
            ->assertSessionHas('locale', 'prs');

        $this->get('/locale/ps')
            ->assertRedirect()
            ->assertSessionHas('locale', 'ps');
    }

    public function test_bengali_is_no_longer_a_supported_locale(): void
    {
        $this->get('/locale/bn')
            ->assertRedirect()
            ->assertSessionMissing('locale');
    }

    public function test_dari_and_pashto_translation_files_are_loaded(): void
    {
        $this->assertNotSame('Dashboard', Lang::get('Dashboard', [], 'prs'));
        $this->assertNotSame('Dashboard', Lang::get('Dashboard', [], 'ps'));
        $this->assertNotSame('current_password', Lang::get('validation.current_password', [], 'prs'));
        $this->assertNotSame('current_password', Lang::get('validation.current_password', [], 'ps'));
        $this->assertNotSame('Chats', Lang::get('Chats', [], 'prs'));
        $this->assertNotSame('Chats', Lang::get('Chats', [], 'ps'));
        $this->assertNotSame('Notifications', Lang::get('Notifications', [], 'prs'));
        $this->assertNotSame('Notifications', Lang::get('Notifications', [], 'ps'));
        $this->assertNotSame('New message from :name', Lang::get('New message from :name', ['name' => 'Amina'], 'prs'));
        $this->assertNotSame('New message from :name', Lang::get('New message from :name', ['name' => 'Amina'], 'ps'));
        $this->assertNotSame('Low stock alert', Lang::get('Low stock alert', [], 'prs'));
        $this->assertNotSame('Low stock alert', Lang::get('Low stock alert', [], 'ps'));
        $this->assertNotSame(
            ':reference was approved and is ready to dispatch.',
            Lang::get(':reference was approved and is ready to dispatch.', ['reference' => 'ST-1'], 'prs')
        );
        $this->assertNotSame(
            ':reference was approved and is ready to dispatch.',
            Lang::get(':reference was approved and is ready to dispatch.', ['reference' => 'ST-1'], 'ps')
        );
    }
}
