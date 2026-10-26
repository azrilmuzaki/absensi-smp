<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Device-Key') ?? $request->input('api_key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'code' => 'MISSING_KEY',
                'message' => 'API key tidak ditemukan.',
            ], 401);
        }

        $device = Device::where('api_key', $apiKey)->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_KEY',
                'message' => 'Perangkat tidak terdaftar atau API key salah.',
            ], 401);
        }

        // Tandai device online dan simpan ke request
        $device->update([
            'status' => 'ONLINE',
            'last_seen_at' => now(),
        ]);

        $request->merge(['_device' => $device]);
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
