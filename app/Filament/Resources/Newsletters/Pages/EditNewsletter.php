<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\Actions\NewsletterSendActions;
use App\Filament\Resources\Newsletters\NewsletterResource;
use App\Filament\Resources\Newsletters\Schemas\NewsletterForm;
use App\Models\Newsletter;
use App\Services\NewsletterContentRenderer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditNewsletter extends EditRecord
{
    use EditRecord\Concerns\HasWizard;

    protected static string $resource = NewsletterResource::class;

    protected function getSteps(): array
    {
        return NewsletterForm::steps();
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Newsletter $record */
        $record = $this->getRecord();

        return NewsletterSendActions::statusBadge($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('newsletters.preview', $this->record))
                ->openUrlInNewTab(),
            NewsletterSendActions::sendTest(),
            NewsletterSendActions::scheduleSend(),
            NewsletterSendActions::cancelSchedule(),
            NewsletterSendActions::queueSend(),
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (Newsletter $record): bool => $record->isDeletable()),
        ];
    }

    /**
     * Saves the current form state without redirecting or notifying, so the "Review & Send"
     * step's preview iframe can be refreshed against up-to-date content in a single click.
     * Validation failures surface as normal inline form errors, same as the header Save action.
     */
    public function saveForPreview(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['content_blocks'] = app(NewsletterContentRenderer::class)->stripSystemChrome(
            is_array($data['content_blocks'] ?? null) ? $data['content_blocks'] : []
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->applyRenderedContentBlocks($data);
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
