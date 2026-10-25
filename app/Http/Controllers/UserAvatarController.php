<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAvatarController extends Controller
{
    public function show(Request $request, User $user, string $filename): StreamedResponse|RedirectResponse
    {
        $path = is_string($user->avatar_path) ? $user->avatar_path : '';
        abort_if($path === '' || basename($path) !== $filename, 404);

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return redirect()->away($user->gravatarUrl((int) $request->integer('s', 96)));
        }

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $headers = [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
