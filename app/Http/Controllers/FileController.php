<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves uploaded files stored outside the public directory.
 */
class FileController extends Controller
{
    protected const PUBLIC_TO_USERS = ['avatars/', 'branding/', 'products/'];

    protected const RESTRICTED = ['expenses/' => 'expenses.view', 'backups/' => 'backups.manage', 'exports/' => null];

    public function show(Request $request, string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        foreach (self::RESTRICTED as $prefix => $permission) {
            if (str_starts_with($path, $prefix)) {
                if ($prefix === 'exports/') {
                    abort_unless(str_starts_with($path, 'exports/'.$request->user()->id.'/'), 403);
                } else {
                    abort_unless($request->user()->can($permission), 403);
                }
            }
        }

        abort_unless(collect([...self::PUBLIC_TO_USERS, ...array_keys(self::RESTRICTED)])->contains(fn ($p) => str_starts_with($path, $p)), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
