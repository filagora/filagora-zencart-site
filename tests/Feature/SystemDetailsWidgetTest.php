<?php

namespace Tests\Feature;

use App\Filament\Widgets\SystemDetailsWidget;
use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class SystemDetailsWidgetTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_widget_renders_when_an_optional_package_is_not_installed(): void
    {
        $this->assertFalse(InstalledVersions::isInstalled('laravel/scout'));

        Livewire::test(SystemDetailsWidget::class)
            ->assertOk()
            ->assertSee('Scout not installed');
    }
}
