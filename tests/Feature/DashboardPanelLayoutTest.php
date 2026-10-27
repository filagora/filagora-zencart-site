<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Tests\TestCase;

class DashboardPanelLayoutTest extends TestCase
{
    public function test_dashboard_panel_uses_full_content_width(): void
    {
        $panel = Filament::getPanel('dashboard');

        $this->assertSame(Width::Full, $panel->getMaxContentWidth());
    }

    public function test_dashboard_panel_sidebar_is_collapsible_on_desktop(): void
    {
        $panel = Filament::getPanel('dashboard');

        $this->assertTrue($panel->isSidebarCollapsibleOnDesktop());
    }
}
