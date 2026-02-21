<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'components' => [
                'database' => 'healthy',
                'vector_db' => 'unknown',
                'ast_service' => 'unknown',
                'queue' => 'unknown',
            ],
        ]);
    }
}
