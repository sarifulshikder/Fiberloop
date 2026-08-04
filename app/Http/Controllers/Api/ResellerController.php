<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Reseller dashboard']);
    }

    public function customers(Request $request): JsonResponse
    {
        return response()->json(['customers' => []]);
    }

    public function createCustomer(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Customer created']);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return response()->json(['subscriptions' => []]);
    }

    public function earnings(Request $request): JsonResponse
    {
        return response()->json(['earnings' => []]);
    }
}
