@extends('layouts.app')
@section('page-title', 'Laporan Rekap Absensi')
@section('content')

    @php
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="padding:14px 20px">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                @if(auth()->user()->role === 'ADMIN')
                    <div class="form-group" style="margin:0">
                        <label>Kelas</label>
                        <select name="class_id" class="form-control" onchange="this.form.submit()">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ $c->id == $classId ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group" style="margin:0">
                    <label>Bulan</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $monthNames[$m] }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Tahun</label>
                    <input type="number" name="year" class="form-control" value="{{ $year }}" style="width:90px"
                        onchange="this.form.submit()" />
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <div class="card-title">Unduh Data Absensi</div>
        </div>
        <div class="card-body" style="padding:14px 20px">
            <form method="GET" action="{{ route('reports.export') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                @if(auth()->user()->role === 'ADMIN')
                    <div class="form-group" style="margin:0">
                        <label>Kelas</label>
                        <select name="class_id" class="form-control" required>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ $c->id == $classId ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group" style="margin:0">
                    <label>Periode</label>
                    <select name="period" id="export-period" class="form-control">
                        <option value="day">Harian</option>
                        <option value="month" selected>Bulanan</option>
                        <option value="year">Tahunan</option>
                    </select>
                </div>
                <div class="form-group" id="export-date-group" style="margin:0;display:none">
                    <label>Tanggal</label>
                    <input type="date" name="date" class="form-control" value="{{ request('date', now(config('app.school_timezone'))->toDateString()) }}" />
                </div>
                <div class="form-group" id="export-month-group" style="margin:0">
                    <label>Bulan</label>
                    <select name="month" class="form-control">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $monthNames[$m] }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-group" id="export-year-group" style="margin:0">
                    <label>Tahun</label>
                    <input type="number" name="year" class="form-control" value="{{ $year }}" min="1900" max="2200" style="width:110px" />
                </div>
                <button type="submit" class="btn btn-primary">Unduh Rekap</button>
            </form>
            <div class="form-hint" style="margin-top:10px">Rekap bulanan diunduh sebagai Excel dengan tabel bergaris. Rekap harian dan tahunan diunduh sebagai CSV. Periode awal mengikuti filter rekap di atas.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Rekap Kelas {{ $className }} — {{ $monthNames[$month] }} {{ $year }}</div>
            <div style="font-size:12px;color:#6b7280">Hari efektif: <strong>{{ $schoolDays }} hari</strong></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Siswa</th>
                        <th style="color:#16a34a;text-align:center">H</th>
                        <th style="color:#d97706;text-align:center">T</th>
                        <th style="color:#2563eb;text-align:center">I</th>
                        <th style="color:#d97706;text-align:center">S</th>
                        <th style="color:#dc2626;text-align:center">A</th>
                        <th style="text-align:center">%Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @forelse($report as $row)
                        @php
                            $totalHadir = $row->hadir + $row->terlambat;
                            $pct = $schoolDays > 0 ? round(($totalHadir / $schoolDays) * 100) : 0;
                            $pctColor = $pct >= 80 ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626');
                        @endphp
                        <tr>
                            <td>{{ $no++ }}</td>
                            <td><strong>{{ $row->student->name }}</strong></td>
                            <td style="text-align:center"><span class="badge badge-green">{{ $row->hadir }}</span></td>
                            <td style="text-align:center"><span class="badge badge-yellow">{{ $row->terlambat }}</span></td>
                            <td style="text-align:center"><span class="badge badge-blue">{{ $row->izin }}</span></td>
                            <td style="text-align:center"><span class="badge badge-yellow">{{ $row->sakit }}</span></td>
                            <td style="text-align:center"><span class="badge badge-red">{{ $row->alpha }}</span></td>
                            <td style="text-align:center;font-weight:600;color:{{ $pctColor }}">{{ $pct }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:#9ca3af;padding:24px">Tidak ada data siswa di kelas
                                ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 20px;font-size:12px;color:#6b7280;border-top:1px solid #f3f4f6">
            Keterangan: <strong>H</strong>=Hadir &nbsp; <strong>T</strong>=Terlambat &nbsp; <strong>I</strong>=Izin &nbsp;
            <strong>S</strong>=Sakit &nbsp; <strong>A</strong>=Alpha
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const period = document.getElementById('export-period');
                const dateGroup = document.getElementById('export-date-group');
                const monthGroup = document.getElementById('export-month-group');
                const yearGroup = document.getElementById('export-year-group');

                function updateExportFields() {
                    const showDate = period.value === 'day';
                    const showMonth = period.value === 'month';
                    const showYear = period.value !== 'day';
                    dateGroup.style.display = showDate ? 'block' : 'none';
                    monthGroup.style.display = showMonth ? 'block' : 'none';
                    yearGroup.style.display = showYear ? 'block' : 'none';
                    dateGroup.querySelector('input').disabled = !showDate;
                    monthGroup.querySelector('select').disabled = !showMonth;
                    yearGroup.querySelector('input').disabled = !showYear;
                }

                period.addEventListener('change', updateExportFields);
                updateExportFields();
            })();
        </script>
    @endpush
@endsection
