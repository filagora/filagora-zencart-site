<?php

namespace App\Http\Controllers;

use Filagora\ForumBugReports\Enums\BugReportStatus;
use Filagora\ForumBugReports\Models\BugReport;
use Filagora\ForumBugReports\Services\BugReports;
use Filagora\Forums\Models\Thread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A moderator opening a bug report on a thread, and changing it. The people following the thread
 * hear of each change (see `SendBugReportNotification`).
 */
class BugReportController extends Controller
{
    public function __construct(private readonly BugReports $reports) {}

    public function store(Request $request, Thread $thread): RedirectResponse
    {
        abort_unless($request->user()?->can('create', BugReport::class), 403);

        $validated = $request->validate(['description' => ['nullable', 'string', 'max:5000']]);

        $this->reports->report($request->user(), $thread, $validated['description'] ?? null);

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

        $this->reports->update(
            $bugReport,
            BugReportStatus::from($validated['status']),
            isset($validated['assigned_to_id']) ? (int) $validated['assigned_to_id'] : null,
            $validated['resolution_note'] ?? null,
        );

        return redirect()->to(route('threads.show', $bugReport->thread_id))->with('thread_status', __('forum.flash.bug_report_updated'));
    }
}
