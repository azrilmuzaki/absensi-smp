<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $devices = Device::orderBy('device_name')->get();

        return view('devices.index', compact('devices'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $data = $request->validate([
            'device_code' => 'required|string|max:50|unique:devices,device_code',
            'device_name' => 'required|string|max:100',
        ]);

        Device::create([
            'device_code' => $data['device_code'],
            'device_name' => $data['device_name'],
            'api_key' => bin2hex(random_bytes(20)),
            'status' => 'OFFLINE',
        ]);

        return back()->with('success', 'Perangkat berhasil ditambahkan.');
    }

    public function destroy(Device $device)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $device->delete();

        return back()->with('success', 'Perangkat berhasil dihapus.');
    }

    public function regenerateKey(Device $device)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $device->update(['api_key' => bin2hex(random_bytes(20))]);

        return back()->with('success', "API Key perangkat {$device->device_name} berhasil di-generate ulang.");
    }
}
