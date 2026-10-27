<?php

namespace App\Http\Controllers;

use App\BugReportStatus;
use App\Models\BugReport;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\Subscriptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A moderator opening a bug report on a thread, and changing it. The people following the thread
 * hear of each change (see `BugReportObserver`).
 */
class BugReportController extends Controller
{
    /**
     * An identical report on the same thread from the same reporter inside this window is a double
     * click or a resubmitted form, not a second report. A thread may collect several over time, so
     * only an immediate repeat is dropped.
     */
    private const DUPLICATE_SUBMISSION_WINDOW_SECONDS = 15;

    public function __construct(private readonly Subscriptions $subscriptions) {}

    public function store(Request $request, Thread $thread): RedirectResponse
    {
        abort_unless($request->user()?->can('create', BugReport::class), 403);

        $description = trim((string) $request->validate(['description' => ['nullable', 'string', 'max:5000']])['description'] ?? '') ?: null;

        $isDuplicate = BugReport::query()
            ->where('thread_id', $thread->id)
            ->where('reported_by_id', $request->user()->id)
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_SUBMISSION_WINDOW_SECONDS))
            ->where(fn ($query) => $description === null ? $query->whereNull('description') : $query->where('description', $description))
            ->exists();

        if (! $isDuplicate) {
            // Reporters follow their own report so its changes reach them.
            $this->subscriptions->subscribe($request->user(), $thread);

            BugReport::query()->create([
                'thread_id' => $thread->id,
                'reported_by_id' => $request->user()->id,
                'status' => BugReportStatus::REPORTED,
                'description' => $description,
            ]);
        }

        return redirect()->to(route('threads.show', $thread))->with('thread_status', __('forum.flash.bug_report_created'));
    }

    public function update(Request $request, BugReport $bugReport): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $bugReport), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:reported,confirmed,completed'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = BugReportStatus::from($validated['status']);
        $note = trim((string) ($validated['resolution_note'] ?? ''));

        $bugReport->status = $status;
        $bugReport->assigned_to_id = $validated['assigned_to_id'] ?? null ?: null;
        $bugReport->resolution_note = $note !== '' ? $note : $bugReport->resolution_note;
        $bugReport->confirmed_at = $status === BugReportStatus::CONFIRMED ? ($bugReport->confirmed_at ?? now()) : $bugReport->confirmed_at;
        $bugReport->completed_at = $status->isTerminal() ? ($bugReport->completed_at ?? now()) : null;
        $bugReport->save();

        return redirect()->to(route('threads.show', $bugReport->thread_id))->with('thread_status', __('forum.flash.bug_report_updated'));
    }
}
