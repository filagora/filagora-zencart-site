<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class FrontendLinksWidget extends Widget
{
    protected string $view = 'filament.widgets.frontend-links-widget';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';
}
