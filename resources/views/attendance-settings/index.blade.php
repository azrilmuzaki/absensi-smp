@extends('layouts.app')
@section('page-title', 'Pengaturan Waktu Absensi')
@section('content')

    <div class="card" style="max-width:500px">
        <div class="card-header">
            <div class="card-title">Konfigurasi Jam Mesin ESP32</div>
        </div>
        <div class="card-body">
            <div class="alert alert-warning" style="font-size:12px;margin-bottom:20px">
                Perubahan ini akan langsung mempengaruhi penentuan status (Hadir/Terlambat) saat siswa tap kartu di mesin.
                Format jam harus berupa HH:MM.
            </div>

            <form method="POST" action="{{ route('attendance-settings.update') }}">
                @csrf @method('PUT')

                <div class="form-group">
                    <label>Awal Jam Masuk</label>
                    <input type="time" name="jam_masuk" class="form-control" required
                        value="{{ \Carbon\Carbon::parse($settings['jam_masuk']->value ?? '06:00:00')->format('H:i') }}" />
                    <div style="font-size:11px;color:#6b7280;margin-top:4px">Tap di bawah jam ini akan ditolak (Terlalu
                        pagi).</div>
                </div>

                <div class="form-group">
                    <label>Batas Jam Terlambat</label>
                    <input type="time" name="jam_terlambat" class="form-control" required
                        value="{{ \Carbon\Carbon::parse($settings['jam_terlambat']->value ?? '07:05:00')->format('H:i') }}" />
                    <div style="font-size:11px;color:#6b7280;margin-top:4px">Lewat dari jam ini (misal 07:06) status
                        otomatis TERLAMBAT.</div>
                </div>

                <div class="form-group">
                    <label>Batas Akhir Scan (Gerbang Ditutup)</label>
                    <input type="time" name="batas_scan" class="form-control" required
                        value="{{ \Carbon\Carbon::parse($settings['batas_scan']->value ?? '10:00:00')->format('H:i') }}" />
                    <div style="font-size:11px;color:#6b7280;margin-top:4px">Tap di atas jam ini akan ditolak oleh sistem.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:10px">Simpan Konfigurasi</button>
            </form>
        </div>
    </div>

@endsection