<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Who the token belongs to, and the branches it can see. */
class ApiController extends Controller
{
    public function me(Request $request, BranchContext $context): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'business' => ['id' => tenant()->getKey(), 'name' => setting('business.name')],
            'branches' => $context->accessibleBranches()->map(fn (Branch $b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name])->values(),
            'token' => ['name' => $request->attributes->get('api_token')->name, 'abilities' => $request->attributes->get('api_token')->abilities],
        ]]);
    }
}
