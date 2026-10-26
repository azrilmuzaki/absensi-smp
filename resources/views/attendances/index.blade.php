@extends('layouts.app')
@section('page-title', 'Data Absensi')
@section('content')

    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="padding:16px 20px">
            <form method="GET" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="margin:0">
                    <label>Tanggal</label>
                    <input type="date" name="date" class="form-control" value="{{ $date->format('Y-m-d') }}"
                        onchange="this.form.submit()" />
                </div>
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
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Absensi Kelas: {{ $classes->firstWhere('id', $classId)?->name ?? '-' }}</div>
            <div style="font-size:12px;color:#6b7280">{{ $date->locale('id')->isoFormat('dddd, D MMMM Y') }}</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Status Asal</th>
                        <th>Waktu Hadir</th>
                        <th>Sumber</th>
                        <th>Ubah Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataset as $row)
                        <tr>
                            <td><strong>{{ $row->student->name }}</strong></td>
                            <td>
                                @php $sc = match ($row->status) { 'HADIR' => 'green', 'TERLAMBAT' => 'yellow', 'IZIN' => 'blue', 'SAKIT' => 'yellow', 'ALPHA' => 'red', default => 'gray'}; @endphp
                                <span class="badge badge-{{ $sc }}">{{ str_replace('_', ' ', $row->status) }}</span>
                            </td>
                            <td>{{ $row->time }}</td>
                            <td style="font-size:12px"><span
                                    style="color:#6b7280;border:1px solid #e5e7eb;padding:2px 6px;border-radius:4px">{{ $row->source }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-outline btn-sm" data-student-id="{{ $row->student->id }}"
                                    data-student-name="{{ $row->student->name }}" data-attendance-status="{{ $row->status }}"
                                    data-attendance-time="{{ $row->time }}" data-attendance-date="{{ $date->format('Y-m-d') }}"
                                    onclick="openManualModal(this)">Ubah/Koreksi</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">Tidak ada siswa atau kelas
                                belum aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Koreksi Manual --}}
    <div id="modal-manual" class="app-modal"
        data-current-date="{{ now(config('app.school_timezone'))->toDateString() }}"
        data-current-time="{{ now(config('app.school_timezone'))->format('H:i') }}"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:400px">
            <h3 id="modal-manual-title" style="margin-bottom:20px;font-size:16px">Input / Koreksi Absensi</h3>
            <form method="POST" action="{{ route('attendances.manual') }}">
                @csrf
                <input type="hidden" name="student_id" id="manual-student-id" value="{{ old('student_id') }}">
                <input type="hidden" name="date" id="manual-attendance-date" value="{{ old('date', $date->format('Y-m-d')) }}">

                <div class="form-group">
                    <label>Nama Siswa</label>
                    <input type="text" name="student_name" id="manual-student-name" class="form-control" value="{{ old('student_name') }}" readonly style="background:#f9fafb" />
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="manual-status" class="form-control" data-modal-autofocus required>
                        <option value="HADIR" @selected(old('status', 'HADIR') === 'HADIR')>Hadir (Manual)</option>
                        <option value="TERLAMBAT" @selected(old('status') === 'TERLAMBAT')>Terlambat (Manual)</option>
                        <option value="SAKIT" @selected(old('status') === 'SAKIT')>Sakit</option>
                        <option value="IZIN" @selected(old('status') === 'IZIN')>Izin</option>
                        <option value="ALPHA" @selected(old('status') === 'ALPHA')>Alpha</option>
                    </select>
                </div>

                <div class="form-group" id="manual-time-group">
                    <label for="manual-time">Jam Kehadiran</label>
                    <input type="time" name="time" id="manual-time"
                        class="form-control {{ $errors->has('time') ? 'is-invalid' : '' }}" value="{{ old('time') }}"
                        step="60" />
                    <div style="font-size:11px;color:#6b7280;margin-top:4px">Isi jam kedatangan yang sebenarnya. Untuk koreksi tanggal lampau, masukkan jam saat siswa hadir.</div>
                    @error('time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Keterangan / Alasan (Opsional)</label>
                    <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" placeholder="Contoh: Surat dokter masuk..." />
                </div>

                <div style="display:flex;gap:10px;margin-top:20px">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function openManualModal(id, name) {
                const trigger = typeof id === 'object' ? id : null;
                const studentId = trigger ? trigger.dataset.studentId : id;
                const studentName = trigger ? trigger.dataset.studentName : name;
                const dateInput = document.getElementById('manual-attendance-date');
                const statusInput = document.getElementById('manual-status');
                const timeInput = document.getElementById('manual-time');
                const timeGroup = document.getElementById('manual-time-group');
                const modal = document.getElementById('modal-manual');

                document.getElementById('manual-student-id').value = studentId;
                document.getElementById('manual-student-name').value = studentName;

                if (trigger) {
                    dateInput.value = trigger.dataset.attendanceDate;
                    statusInput.value = trigger.dataset.attendanceStatus === 'BELUM_HADIR'
                        ? 'HADIR'
                        : trigger.dataset.attendanceStatus;
                    const recordedTime = trigger.dataset.attendanceTime || '';
                    timeInput.value = /^\d{2}:\d{2}/.test(recordedTime) ? recordedTime.slice(0, 5) : '';
                }

                const updateTimeField = useDefaultTime => {
                    const requiresTime = ['HADIR', 'TERLAMBAT'].includes(statusInput.value);
                    timeGroup.hidden = !requiresTime;
                    timeInput.disabled = !requiresTime;
                    timeInput.required = requiresTime;

                    if (!requiresTime) {
                        timeInput.value = '';
                    } else if (useDefaultTime && !timeInput.value && dateInput.value === modal.dataset.currentDate) {
                        timeInput.value = modal.dataset.currentTime;
                    }
                };

                statusInput.onchange = () => updateTimeField(true);
                updateTimeField(Boolean(trigger));
                window.openAppModal('modal-manual', trigger || document.activeElement);
            }

            @if(old('student_id'))
                window.openAppModal('modal-manual');
            @endif
        </script>
    @endpush

@endsection
