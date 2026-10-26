@extends('layouts.app')
@section('page-title', 'Mass Promotion (Kenaikan Kelas)')
@section('content')

    <div class="card" style="max-width:600px;margin:0 auto">
        <div class="card-header">
            <div class="card-title">Atur Kenaikan Kelas</div>
        </div>
        <div class="card-body">
            <div class="alert alert-info"
                style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:13px;padding:12px">
                <h4 style="margin-bottom:6px;font-size:14px">Informasi Kenaikan Kelas</h4>
                <ul style="padding-left:16px;margin:0">
                    <li>Siswa kelas VII akan naik ke kelas VIII.</li>
                    <li>Siswa kelas VIII akan naik ke kelas IX.</li>
                    <li>Siswa kelas IX akan lulus (status berubah menjadi LULUS).</li>
                    <li>Tahun ajaran asal adalah tahun ajaran yang sedang aktif saat ini.</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('promotions.preview') }}" style="margin-top:20px">
                @csrf
                <div class="form-group">
                    <label>Tahun Ajaran Tujuan (Baru)</label>
                    <select name="to_year_id" class="form-control" required>
                        <option value="">Pilih Tahun Ajaran Tujuan...</option>
                        @foreach($years as $year)
                            @if(!$year->is_active)
                                <option value="{{ $year->id }}">{{ $year->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Catatan: Buat tahun ajaran baru terlebih dahulu
                        jika belum tersedia di pilihan ini.</div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:8px">Lihat Preview Kenaikan</button>
            </form>
        </div>
    </div>

@endsection