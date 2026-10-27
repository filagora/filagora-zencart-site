<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Filament\Resources\Newsletters\Schemas\NewsletterForm;
use App\Services\NewsletterContentRenderer;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = NewsletterResource::class;

    protected function getSteps(): array
    {
        return NewsletterForm::steps();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->applyRenderedContentBlocks($data);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyRenderedContentBlocks(array $data): array
    {
        $renderer = app(NewsletterContentRenderer::class);
        $blocks = $renderer->preparedBlocks(is_array($data['content_blocks'] ?? null) ? $data['content_blocks'] : []);
        $data['content_blocks'] = $blocks;
        $data['body_html'] = $renderer->htmlFromBlocks($blocks);
        $data['body_text'] = $renderer->textFromBlocks($blocks);

        return $data;
    }
}
