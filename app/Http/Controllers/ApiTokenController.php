<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiTokenRequest;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The signed-in user's API tokens (Settings → Features → API access). */
class ApiTokenController extends Controller
{
    public function __construct(protected ApiTokenService $tokens) {}

    public function store(ApiTokenRequest $request): RedirectResponse
    {
        $data = $request->validated();
        [, $plain] = $this->tokens->create($request->user(), $data['name'], (bool) ($data['write'] ?? false), $data['expires_in_days'] ?? null);

        return redirect()->to(route('profile.edit').'#api')->with('api_token_plain', $plain)
            ->with('success', __('API token created. Copy it now: it will not be shown again.'));
    }

    public function destroy(Request $request, ApiToken $apiToken): RedirectResponse
    {
        abort_unless($apiToken->user_id === $request->user()->id || $request->user()->can('users.manage'), 403);
        $this->tokens->revoke($apiToken, $request->user());

        return redirect()->to(route('profile.edit').'#api')->with('success', __('API token revoked.'));
    }
}
