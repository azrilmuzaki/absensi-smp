@extends('layouts.app')
@section('page-title', 'Kartu RFID Tidak Dikenal')
@section('content')

    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 20px; font-size:13.5px">
            Halaman ini menampilkan log UID kartu RFID yang di-scan pada mesin absen (ESP32) namun <strong>belum
                terdaftar</strong> dalam sistem.
            <br><br>
            <strong>Cara Pendaftaran Cepat:</strong> Arahkan siswa untuk scan kartu kosongnya di mesin absen, masuk ke
            halaman ini, copy UID-nya, dan daftarkan ke profil siswa, atau langsung gunakan pilihan Daftarkan.
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Log Kartu Tidak Dikenal</div>
            @if($logs->count() > 0)
                <form method="POST" action="{{ route('unknown-rfids.clear') }}"
                    onsubmit="return confirm('Hapus semua log secara permanen?')">
                    @csrf
                    <button class="btn btn-danger btn-sm">Bersihkan Semua</button>
                </form>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu Scan</th>
                        <th>UID RFID</th>
                        <th>Perangkat Mesin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td><code
                                    style="font-size:14px;color:#0891b2;background:#cffafe;padding:2px 6px;border-radius:4px">{{ $log->uid }}</code>
                            </td>
                            <td>{{ $log->device->device_name ?? 'Unknown Device' }}</td>
                            <td>
                                <div class="table-actions">
                                {{-- Quick Assign Button (Membuka modal) --}}
                                <button type="button" class="btn btn-primary btn-sm" data-uid="{{ $log->uid }}"
                                    onclick="openAssignModal(this)">Daftarkan</button>

                                <form method="POST" action="{{ route('unknown-rfids.destroy', $log) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm">Hapus</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Tidak ada log kartu asing.
                                Bagus!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div style="padding:16px">{{ $logs->links() }}</div>
        @endif
    </div>

    {{-- Modal Quick Assign --}}
    <div id="modal-assign" class="app-modal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:480px">
            <h3 id="modal-assign-title" style="margin-bottom:20px;font-size:16px">Daftarkan Kartu RFID</h3>

            <form id="form-assign" method="POST" action="" data-action-template="{{ route('rfid.assign', ['student' => '__STUDENT_ID__']) }}">
                @csrf
                <div class="form-group">
                    <label>UID Kartu</label>
                    <input type="text" name="uid" id="assign-uid" class="form-control" readonly
                        style="background:#f3f4f6" />
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Catatan: Bila berhasil, log kartu tidak dikenal
                        akan otomatis terhapus.</div>
                </div>

                <div class="form-group">
                    <label>Pilih Siswa (Hanya Siswa Aktif yang Belum Memiliki Kartu)</label>
                    <select id="student-select" name="student_id" required class="form-control" onchange="updateFormAction(this.value)">
                        <option value="">Pilih Siswa...</option>
                        @foreach($students as $s)
                            <option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->name }}
                                ({{ $s->placements->first()?->schoolClass->name ?? 'Belum ada kelas' }})</option>
                        @endforeach
                    </select>
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
            function openAssignModal(button) {
                document.getElementById('assign-uid').value = button.dataset.uid;
                window.openAppModal('modal-assign', button);
            }

            function updateFormAction(studentId) {
                if (!studentId) return;
                const form = document.getElementById('form-assign');
                form.action = form.dataset.actionTemplate.replace('__STUDENT_ID__', encodeURIComponent(studentId));
            }

            @if(old('uid'))
                const select = document.getElementById('student-select');
                if (select.value) updateFormAction(select.value);
                document.getElementById('assign-uid').value = @json(old('uid'));
                window.openAppModal('modal-assign');
            @endif
        </script>
    @endpush

@endsection
