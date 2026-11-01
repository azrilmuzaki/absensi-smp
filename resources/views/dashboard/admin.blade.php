@extends('layouts.app')
@section('page-title', 'Dashboard Admin')
@section('content')

    <div data-live-dashboard data-live-url="{{ route('dashboard.live') }}" data-live-role="admin">
    {{-- Stats Grid --}}
    <div class="stats-grid">
        <div class="stat-card color-blue">
            <div class="stat-label">Total Siswa Aktif</div>
            <div class="stat-value" data-live-stat="total_students">{{ $stats['total_students'] }}</div>
        </div>
        <div class="stat-card color-green">
            <div class="stat-label">Hadir Hari Ini</div>
            <div class="stat-value" data-live-stat="hadir">{{ $stats['hadir'] }}</div>
        </div>
        <div class="stat-card color-yellow">
            <div class="stat-label">Terlambat</div>
            <div class="stat-value" data-live-stat="terlambat">{{ $stats['terlambat'] }}</div>
        </div>
        <div class="stat-card" style="border-left:3px solid #0891b2">
            <div class="stat-label">Izin</div>
            <div class="stat-value" data-live-stat="izin">{{ $stats['izin'] }}</div>
        </div>
        <div class="stat-card color-yellow">
            <div class="stat-label">Sakit</div>
            <div class="stat-value" data-live-stat="sakit">{{ $stats['sakit'] }}</div>
        </div>
        <div class="stat-card color-red">
            <div class="stat-label">Alpha</div>
            <div class="stat-value" data-live-stat="alpha">{{ $stats['alpha'] }}</div>
        </div>
    </div>

    {{-- Absensi Terbaru --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Absensi Terbaru Hari Ini</div>
            <div class="table-actions">
                <span style="font-size:12px;color:#6b7280" data-live-today>{{ now(config('app.school_timezone'))->format('d/m/Y') }}</span>
                <span class="badge badge-gray" data-live-status aria-live="polite">Menghubungkan…</span>
                <a href="{{ route('attendances.index') }}" class="btn btn-outline btn-sm">Semua Presensi</a>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Sumber</th>
                    </tr>
                </thead>
                <tbody data-live-recent-attendance>
                    @forelse($recentAttendance as $a)
                        <tr>
                            <td><strong>{{ $a->student->name ?? '-' }}</strong></td>
                            <td>{{ $a->time }}</td>
                            <td>
                                @php
                                    $color = match ($a->status) {
                                        'HADIR' => 'green',
                                        'TERLAMBAT' => 'yellow',
                                        'IZIN' => 'blue',
                                        'SAKIT' => 'yellow',
                                        'ALPHA' => 'red',
                                        default => 'gray',
                                    };
                                @endphp
                                <span class="badge badge-{{ $color }}">{{ $a->status }}</span>
                            </td>
                            <td><span class="badge badge-gray">{{ $a->source }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Belum ada absensi hari ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
@endsection
