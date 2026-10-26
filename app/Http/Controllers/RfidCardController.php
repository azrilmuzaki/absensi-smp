<?php

namespace App\Http\Controllers;

use App\Models\RfidCard;
use App\Models\Student;
use App\Services\RfidCardService;
use Illuminate\Http\Request;

class RfidCardController extends Controller
{
    public function __construct(private RfidCardService $service) {}

    public function assign(Request $request, Student $student)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'uid' => 'required|string|min:4|max:50',
        ]);

        try {
            $this->service->assignCard($student, $data['uid']);

            return back()->with('success', "Kartu RFID berhasil didaftarkan untuk {$student->name}.");
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function revoke(Request $request, RfidCard $rfidCard)
    {
        $this->authorizeAdmin();
        $this->service->revokeCard($rfidCard);

        return back()->with('success', "Kartu RFID {$rfidCard->uid} telah dicabut/dinonaktifkan.");
    }

    private function authorizeAdmin()
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403, 'Akses ditolak.');
    }
}
