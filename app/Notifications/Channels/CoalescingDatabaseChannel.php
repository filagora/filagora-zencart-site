<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Same storage as Laravel's built-in `database` channel, except: when the
 * notification's payload carries a `coalesce_key`, an existing *unread* row
 * for the same notifiable/type/key is updated in place - its data replaced
 * with the latest and a running `count` incremented, its timestamps bumped
 * so it resurfaces at the top of the bell - instead of inserting another
 * row. Read notifications are never touched, so once a user reads a row the
 * next event starts a fresh one.
 *
 * Used by ThreadUpdatedNotification (keyed by thread id) so ten replies to
 * one followed thread show as one bell row ("12 replies"), not ten.
 * Notifications that omit coalesce_key behave exactly like the standard
 * `database` channel.
 */
class CoalescingDatabaseChannel
{
    public function send(mixed $notifiable, Notification $notification): DatabaseNotification
    {
        $data = $this->dataFor($notifiable, $notification);
        $coalesceKey = $data['coalesce_key'] ?? null;

        if (is_string($coalesceKey) && $coalesceKey !== '') {
            // lockForUpdate + a transaction, not a plain select-then-save:
            // without it, two near-simultaneous deliveries (two queue workers
            // handling two replies to the same thread) can both read the same
            // "no existing row yet" or the same count before either writes,
            // losing an increment or inserting two rows instead of coalescing
            // into one.
            return DB::transaction(function () use ($notifiable, $notification, $data, $coalesceKey): DatabaseNotification {
                $existing = $notifiable->routeNotificationFor('database', $notification)
                    ->where('type', get_class($notification))
                    ->whereNull('read_at')
                    ->where('data->coalesce_key', $coalesceKey)
                    ->latest('created_at')
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof DatabaseNotification) {
                    $data['count'] = ((int) ($existing->data['count'] ?? 1)) + 1;

                    $existing->forceFill([
                        'data' => $data,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->save();

                    return $existing;
                }

                $data['count'] ??= 1;

                return $notifiable->routeNotificationFor('database', $notification)->create([
                    'id' => $notification->id,
                    'type' => get_class($notification),
                    'data' => $data,
                    'read_at' => null,
                ]);
            });
        }

        $data['count'] ??= 1;

        return $notifiable->routeNotificationFor('database', $notification)->create([
            'id' => $notification->id,
            'type' => get_class($notification),
            'data' => $data,
            'read_at' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(mixed $notifiable, Notification $notification): array
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($notifiable);

            return is_array($data) ? $data : $data->data;
        }

        return $notification->toArray($notifiable);
    }
}
