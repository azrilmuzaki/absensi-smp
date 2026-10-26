@extends('layouts.app')
@section('page-title', 'Preview Kenaikan Kelas')
@section('page-actions')
    <a href="{{ route('promotions.index') }}" class="btn btn-outline" data-restore-scroll>← Kembali ke kenaikan kelas</a>
@endsection
@section('content')

    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">
            <div>
                <h3 style="font-size:16px;margin-bottom:4px">Preview Kenaikan Kelas</h3>
                <div style="font-size:13px;color:#6b7280">
                    Dari: <strong>{{ $fromYear->name }}</strong> (Aktif) ➔ Menuju: <strong>{{ $toYear->name }}</strong>
                </div>
            </div>
            <form method="POST" action="{{ route('promotions.execute') }}"
                onsubmit="return confirm('Proses Kenaikan Kelas ini bersifat MASIF. Apakah Anda yakin data penerus sudah benar?');">
                @csrf
                <input type="hidden" name="from_year_id" value="{{ $fromYear->id }}">
                <input type="hidden" name="to_year_id" value="{{ $toYear->id }}">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                <button type="submit" class="btn btn-primary">Konfirmasi Eksekusi</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kelas Asal ({{ $fromYear->name }})</th>
                        <th>Kelas Tujuan ({{ $toYear->name }})</th>
                        <th>Jumlah Siswa</th>
                        <th>Status Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($map as $entry)
                        <tr>
                            <td><strong>{{ $entry['from_class']->name }}</strong></td>
                            <td>
                                @if($entry['will_graduate'])
                                    <span class="badge badge-blue">LULUS</span>
                                @elseif($entry['to_class'])
                                    <strong>{{ $entry['to_class']->name }}</strong>
                                @else
                                    <span class="badge badge-red">KELAS "{{ $entry['to_class_name'] }}" BELUM DIBUAT</span>
                                @endif
                            </td>
                            <td>{{ $entry['student_count'] }} siswa</td>
                            <td>
                                @if($entry['will_graduate'])
                                    <span style="color:#2563eb;font-size:13px">Set sebagai alumni</span>
                                @elseif($entry['to_class'])
                                    <span style="color:#16a34a;font-size:13px">Naik kelas</span>
                                @else
                                    <span style="color:#dc2626;font-size:13px">Kelas tujuan tidak ditemukan, siswa tidak akan
                                        dinaikkan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Tidak ada kelas di tahun ajaran
                                asal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
