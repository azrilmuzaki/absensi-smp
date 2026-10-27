<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class VercelCronController extends Controller
{
    public function markAlpha(Request $request): JsonResponse
    {
        $cronSecret = config('services.vercel.cron_secret');
        $authorization = (string) $request->header('Authorization');

        if (! is_string($cronSecret) || $cronSecret === '' || ! hash_equals('Bearer '.$cronSecret, $authorization)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $exitCode = Artisan::call('attendance:mark-alpha');

        if ($exitCode !== 0) {
            return response()->json(['message' => 'Attendance task failed.'], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
