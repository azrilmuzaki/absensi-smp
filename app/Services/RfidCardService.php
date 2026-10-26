<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RfidCard;
use App\Models\Student;
use App\Models\UnknownRfidLog;
use Illuminate\Support\Facades\DB;

class RfidCardService
{
    /**
     * Daftarkan kartu RFID baru untuk siswa.
     */
    public function assignCard(Student $student, string $uid): RfidCard
    {
        return DB::transaction(function () use ($student, $uid) {
            $formattedUid = strtoupper(trim($uid));

            // Pastikan belum ada kartu aktif dengan UID ini di siswa mana pun
            $exists = RfidCard::where('uid', $formattedUid)
                ->where('status', 'ACTIVE')
                ->exists();

            if ($exists) {
                throw new \RuntimeException("RFID UID {$formattedUid} sudah terdaftar dan aktif untuk siswa lain.");
            }

            // Nonaktifkan semua kartu aktif yang dimiliki siswa ini (satu siswa hanya boleh 1 kartu aktif)
            RfidCard::where('student_id', $student->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'INACTIVE']);

            $card = RfidCard::create([
                'student_id' => $student->id,
                'uid' => $formattedUid,
                'status' => 'ACTIVE',
            ]);

            // Hapus log kartu ini dari unknown RFID (karena sekarang sudah dikenal)
            UnknownRfidLog::where('uid', $formattedUid)->delete();

            AuditLog::record('rfid.assigned', RfidCard::class, $card->id, [], $card->toArray());

            return $card;
        });
    }

    /**
     * Cabut/Nonaktifkan kartu RFID (misal hilang atau rusak).
     */
    public function revokeCard(RfidCard $card, string $reason = 'HILANG/RUSAK'): RfidCard
    {
        return DB::transaction(function () use ($card, $reason) {
            $old = $card->toArray();

            $card->update(['status' => 'INACTIVE']);

            AuditLog::record('rfid.revoked', RfidCard::class, $card->id, $old, [
                ...$card->fresh()->toArray(),
                'reason' => $reason,
            ]);

            return $card->fresh();
        });
    }
}
