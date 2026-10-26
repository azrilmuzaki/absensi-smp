@extends('layouts.app')
@section('page-title', 'Kalender Sekolah')
@section('content')

    @php
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $prevMonth = $month == 1 ? 12 : $month - 1;
        $prevYear = $month == 1 ? $year - 1 : $year;
        $nextMonth = $month == 12 ? 1 : $month + 1;
        $nextYear = $month == 12 ? $year + 1 : $year;
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-body"
            style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div style="font-size:16px;font-weight:600">
                📅 {{ $monthNames[$month] }} {{ $year }}
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <a href="{{ route('school-calendars.index', ['month' => $prevMonth, 'year' => $prevYear]) }}"
                    class="btn btn-outline btn-sm">‹ Prev</a>
                <form method="GET" style="display:flex;gap:8px">
                    <select name="month" class="form-control" style="width:auto" onchange="this.form.submit()">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $monthNames[$m] }}</option>
                        @endfor
                    </select>
                    <input type="number" name="year" class="form-control" value="{{ $year }}" style="width:90px"
                        onchange="this.form.submit()" />
                </form>
                <a href="{{ route('school-calendars.index', ['month' => $nextMonth, 'year' => $nextYear]) }}"
                    class="btn btn-outline btn-sm">Next ›</a>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-modal-open="modal-add">+
                Tambah Hari Libur</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Daftar Hari Libur — {{ $monthNames[$month] }} {{ $year }}</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($calendars as $cal)
                        <tr>
                            <td><strong>{{ \Carbon\Carbon::parse($cal->date)->locale('id')->isoFormat('dddd, D MMMM Y') }}</strong>
                            </td>
                            <td><span
                                    class="badge {{ $cal->status === 'HOLIDAY' ? 'badge-red' : 'badge-green' }}">{{ $cal->status }}</span>
                            </td>
                            <td>{{ $cal->description ?? '-' }}</td>
                            <td>
                                <form method="POST" action="{{ route('school-calendars.destroy', $cal) }}"
                                    onsubmit="return confirm('Hapus data ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Tidak ada data libur untuk
                                bulan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah --}}
    <div id="modal-add" class="app-modal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:440px">
            <h3 id="modal-add-title" style="font-size:16px;margin-bottom:20px">Tambah/Ubah Hari Libur</h3>
            <form method="POST" action="{{ route('school-calendars.store') }}">
                @csrf
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="date" class="form-control" value="{{ old('date', sprintf('%04d-%02d-01', $year, $month)) }}" data-modal-autofocus required />
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="HOLIDAY" @selected(old('status', 'HOLIDAY') === 'HOLIDAY')>HOLIDAY (Libur)</option>
                        <option value="ACTIVE" @selected(old('status') === 'ACTIVE')>ACTIVE (Hari Aktif/Override)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Keterangan</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="Contoh: Hari Raya Idul Fitri" />
                </div>
                <div style="display:flex;gap:10px;margin-top:16px">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->has('date') || $errors->has('status') || $errors->has('description'))
        @push('scripts')
            <script>window.openAppModal('modal-add');</script>
        @endpush
    @endif
@endsection
