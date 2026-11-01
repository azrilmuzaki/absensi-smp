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
            <div class="card-title">Log Kartu Tidak Dikenal (<span data-live-unknown-count>{{ $logs->total() }}</span>)</div>
            <div class="table-actions">
                <span class="badge badge-gray" data-unknown-live-status aria-live="polite">Menghubungkan…</span>
                <form method="POST" action="{{ route('unknown-rfids.clear') }}"
                    onsubmit="return confirm('Hapus semua log secara permanen?')"
                    data-clear-all-unknown @if($logs->total() === 0) style="display:none" @endif>
                    @csrf
                    <button class="btn btn-danger btn-sm">Bersihkan Semua</button>
                </form>
            </div>
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
                <tbody data-live-unknown-logs
                    data-live-url="{{ route('unknown-rfids.live') }}"
                    data-delete-url-template="{{ route('unknown-rfids.destroy', ['unknownRfid' => '__LOG_ID__']) }}"
                    data-live-page="{{ $logs->currentPage() }}">
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

            (() => {
                const tableBody = document.querySelector('[data-live-unknown-logs]');
                if (!tableBody) return;

                const liveStatus = document.querySelector('[data-unknown-live-status]');
                let lastSnapshot = null;
                if (tableBody.dataset.livePage !== '1') {
                    liveStatus.textContent = 'Pembaruan langsung tersedia di halaman terbaru';
                    return;
                }

                const setLiveStatus = (message, connected) => {
                    liveStatus.className = `badge ${connected ? 'badge-green' : 'badge-yellow'}`;
                    liveStatus.textContent = message;
                };

                const createCell = value => {
                    const cell = document.createElement('td');
                    cell.textContent = value ?? '-';
                    return cell;
                };

                const renderLogs = logs => {
                    if (!logs.length) {
                        const row = document.createElement('tr');
                        const cell = document.createElement('td');
                        cell.colSpan = 4;
                        cell.style.cssText = 'text-align:center;color:#9ca3af;padding:24px';
                        cell.textContent = 'Tidak ada log kartu asing. Bagus!';
                        row.appendChild(cell);
                        tableBody.replaceChildren(row);
                        return;
                    }

                    const deleteUrlTemplate = tableBody.dataset.deleteUrlTemplate;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

                    tableBody.replaceChildren(...logs.map(log => {
                        const row = document.createElement('tr');
                        row.appendChild(createCell(log.created_at));

                        const uidCell = document.createElement('td');
                        const uid = document.createElement('code');
                        uid.style.cssText = 'font-size:14px;color:#0891b2;background:#cffafe;padding:2px 6px;border-radius:4px';
                        uid.textContent = log.uid;
                        uidCell.appendChild(uid);
                        row.appendChild(uidCell);
                        row.appendChild(createCell(log.device_name));

                        const actionCell = document.createElement('td');
                        const actions = document.createElement('div');
                        actions.className = 'table-actions';

                        const assignButton = document.createElement('button');
                        assignButton.type = 'button';
                        assignButton.className = 'btn btn-primary btn-sm';
                        assignButton.dataset.uid = log.uid;
                        assignButton.textContent = 'Daftarkan';
                        assignButton.addEventListener('click', () => openAssignModal(assignButton));
                        actions.appendChild(assignButton);

                        const deleteForm = document.createElement('form');
                        deleteForm.method = 'POST';
                        deleteForm.action = deleteUrlTemplate.replace('__LOG_ID__', encodeURIComponent(log.id));
                        const tokenInput = document.createElement('input');
                        tokenInput.type = 'hidden';
                        tokenInput.name = '_token';
                        tokenInput.value = csrfToken;
                        const methodInput = document.createElement('input');
                        methodInput.type = 'hidden';
                        methodInput.name = '_method';
                        methodInput.value = 'DELETE';
                        const deleteButton = document.createElement('button');
                        deleteButton.type = 'submit';
                        deleteButton.className = 'btn btn-outline btn-sm';
                        deleteButton.textContent = 'Hapus';
                        deleteForm.append(tokenInput, methodInput, deleteButton);
                        actions.appendChild(deleteForm);

                        actionCell.appendChild(actions);
                        row.appendChild(actionCell);
                        return row;
                    }));
                };

                const refreshUnknownLogs = async () => {
                    if (document.hidden || refreshUnknownLogs.inProgress) return;
                    refreshUnknownLogs.inProgress = true;

                    try {
                        const response = await fetch(tableBody.dataset.liveUrl, {
                            headers: { Accept: 'application/json' },
                            cache: 'no-store',
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Unknown RFID live request failed');

                        const data = await response.json();
                        const snapshot = JSON.stringify({
                            total: data.total ?? 0,
                            logs: data.logs ?? [],
                            students: data.students ?? [],
                        });

                        if (snapshot !== lastSnapshot) {
                            renderLogs(data.logs ?? []);
                            document.querySelector('[data-live-unknown-count]').textContent = String(data.total ?? 0);
                            document.querySelector('[data-clear-all-unknown]').style.display = data.total > 0 ? '' : 'none';

                            const studentSelect = document.getElementById('student-select');
                            const selectedStudentId = studentSelect.value;
                            studentSelect.replaceChildren(new Option('Pilih Siswa...', ''));
                            (data.students ?? []).forEach(student => {
                                studentSelect.add(new Option(student.label, String(student.id)));
                            });
                            if ([...studentSelect.options].some(option => option.value === selectedStudentId)) {
                                studentSelect.value = selectedStudentId;
                                updateFormAction(selectedStudentId);
                            }

                            lastSnapshot = snapshot;
                        }

                        setLiveStatus(`Live · diperbarui ${data.updated_at_label}`, true);
                    } catch {
                        setLiveStatus('Koneksi realtime terputus', false);
                    } finally {
                        refreshUnknownLogs.inProgress = false;
                    }
                };

                refreshUnknownLogs();
                window.setInterval(refreshUnknownLogs, 5000);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) refreshUnknownLogs();
                });
            })();

            @if(old('uid'))
                const select = document.getElementById('student-select');
                if (select.value) updateFormAction(select.value);
                document.getElementById('assign-uid').value = @json(old('uid'));
                window.openAppModal('modal-assign');
            @endif
        </script>
    @endpush

@endsection
