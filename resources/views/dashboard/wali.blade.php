@extends('layouts.app')
@section('page-title', 'Dashboard — ' . ($myClass?->name ?? 'Wali Kelas'))
@section('content')

    <div class="stats-grid">
        <div class="stat-card color-blue">
            <div class="stat-label">Total Siswa Kelas</div>
            <div class="stat-value">{{ $stats['total_students'] }}</div>
        </div>
        <div class="stat-card color-green">
            <div class="stat-label">Hadir</div>
            <div class="stat-value">{{ $stats['hadir'] }}</div>
        </div>
        <div class="stat-card color-yellow">
            <div class="stat-label">Terlambat</div>
            <div class="stat-value">{{ $stats['terlambat'] }}</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #0891b2">
            <div class="stat-label">Izin</div>
            <div class="stat-value">{{ $stats['izin'] }}</div>
        </div>
        <div class="stat-card color-yellow">
            <div class="stat-label">Sakit</div>
            <div class="stat-value">{{ $stats['sakit'] }}</div>
        </div>
        <div class="stat-card color-red">
            <div class="stat-label">Alpha</div>
            <div class="stat-value">{{ $stats['alpha'] }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">

        {{-- Absensi Terbaru --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Absensi Terbaru</div>
                <a href="{{ route('attendances.index') }}" class="btn btn-outline btn-sm">Buka Presensi</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Waktu</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentAttendance as $a)
                            <tr>
                                <td>{{ $a->student->name ?? '-' }}</td>
                                <td>{{ $a->time }}</td>
                                <td>
                                    @php $color = match ($a->status) { 'HADIR' => 'green', 'TERLAMBAT' => 'yellow', 'IZIN' => 'blue', 'SAKIT' => 'yellow', 'ALPHA' => 'red', default => 'gray'}; @endphp
                                    <span class="badge badge-{{ $color }}">{{ $a->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align:center;color:#9ca3af;padding:20px">Belum ada absensi hari ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Belum Hadir --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="color:#dc2626">Belum Hadir ({{ $absentStudents->count() }})</div>
            </div>
            <div style="padding:12px;max-height:400px;overflow-y:auto">
                @forelse($absentStudents as $s)
                    <div style="padding:8px 4px;border-bottom:1px solid #f3f4f6;font-size:13px">{{ $s->name }}</div>
                @empty
                    <div style="text-align:center;color:#16a34a;padding:20px;font-size:13px">✅ Semua siswa sudah hadir!</div>
                @endforelse
            </div>
        </div>
    </div>

@endsection
