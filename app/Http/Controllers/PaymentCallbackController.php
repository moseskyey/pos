<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        return response()->json(['ok' => false], 404);
    }
}
