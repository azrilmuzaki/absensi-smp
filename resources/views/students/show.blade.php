@extends('layouts.app')
@section('page-title', 'Detail Siswa')
@section('page-actions')
    <a href="{{ route('students.index', request()->except('back')) }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar siswa</a>
@endsection
@section('content')

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        {{-- Info Siswa --}}
        <div>
            <div class="card" style="margin-bottom:16px">
                <div class="card-header">
                    <div class="card-title">Informasi Siswa</div>
                    <a href="{{ route('students.edit', array_merge(request()->query(), ['student' => $student, 'back' => 'detail'])) }}" class="btn btn-outline btn-sm">Edit</a>
                </div>
                <div class="card-body">
                    <table style="width:100%">
                        <tr>
                            <td style="color:#6b7280;font-size:13px;padding:6px 0;width:120px">NIS</td>
                            <td><code>{{ $student->nis }}</code></td>
                        </tr>
                        <tr>
                            <td style="color:#6b7280;font-size:13px;padding:6px 0">NISN</td>
                            <td>{{ $student->nisn ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="color:#6b7280;font-size:13px;padding:6px 0">Nama</td>
                            <td><strong>{{ $student->name }}</strong></td>
                        </tr>
                        <tr>
                            <td style="color:#6b7280;font-size:13px;padding:6px 0">Jenis Kelamin</td>
                            <td>{{ $student->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                        </tr>
                        <tr>
                            <td style="color:#6b7280;font-size:13px;padding:6px 0">Tanggal Lahir</td>
                            <td>{{ $student->birth_date?->format('d M Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="color:#6b7280;font-size:13px;padding:6px 0">Status</td>
                            <td>
                                @php $sc = match ($student->status) { 'ACTIVE' => 'green', 'LULUS' => 'blue', 'NONAKTIF' => 'red', default => 'gray'}; @endphp
                                <span class="badge badge-{{ $sc }}">{{ $student->status }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Riwayat Kelas --}}
            <div class="card" style="margin-bottom:16px">
                <div class="card-header">
                    <div class="card-title">Riwayat Kelas</div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tahun Ajaran</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($student->placements->sortByDesc('created_at') as $p)
                                <tr>
                                    <td>{{ $p->academicYear->name }}</td>
                                    <td><strong>{{ $p->schoolClass->name }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- RFID --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Kartu RFID</div>
                    @if(auth()->user()->role === 'ADMIN' && !$student->rfidCards->where('status', 'ACTIVE')->first())
                        <button type="button" class="btn btn-primary btn-sm" data-modal-open="modal-rfid-assign">+ Daftarkan
                            Kartu</button>
                    @endif
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($student->rfidCards->sortByDesc('created_at') as $card)
                                <tr>
                                    <td><code style="font-size:13px">{{ $card->uid }}</code></td>
                                    <td>
                                        <span
                                            class="badge {{ $card->status === 'ACTIVE' ? 'badge-green' : 'badge-gray' }}">{{ $card->status }}</span>
                                    </td>
                                    <td style="font-size:12px;color:#6b7280;min-width:110px">
                                        {{ $card->created_at->format('d M Y') }}</td>
                                    <td>
                                        @if(auth()->user()->role === 'ADMIN' && $card->status === 'ACTIVE')
                                            <form method="POST" action="{{ route('rfid.revoke', $card) }}"
                                                onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan/mencabut RFID ini?')">
                                                @csrf
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    style="font-size:11px;padding:3px 8px">Cabut</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align:center;color:#9ca3af;padding:16px;font-size:13px">Belum
                                        ada RFID terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Modal Daftarkan RFID --}}
        @if(auth()->user()->role === 'ADMIN')
            <div id="modal-rfid-assign" class="app-modal"
                style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
                <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:380px">
                    <h3 id="modal-rfid-assign-title" style="margin-bottom:20px;font-size:16px">Daftarkan Kartu RFID</h3>
                    <form method="POST" action="{{ route('rfid.assign', $student) }}">
                        @csrf
                        <div class="form-group">
                            <label>UID Kartu 10 Digit (Hex/Dec)</label>
                            <input type="text" name="uid" class="form-control" value="{{ old('uid') }}" required placeholder="Contoh: A1B2C3D4E5"
                                data-modal-autofocus />
                            <div style="font-size:11px;color:#6b7280;margin-top:4px">Catatan: Satu siswa hanya dapat memiliki 1
                                kartu aktif. Kartu sebelumnya akan otomatis dinonaktifkan.</div>
                        </div>
                        <div style="display:flex;gap:10px;margin-top:20px">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($errors->has('uid') && auth()->user()->role === 'ADMIN')
            @push('scripts')
                <script>window.openAppModal('modal-rfid-assign');</script>
            @endpush
        @endif

        {{-- Absensi Terbaru --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Absensi Terbaru</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($student->attendances as $a)
                            <tr>
                                <td style="font-size:13px">{{ $a->attendance_date->format('d M Y') }}</td>
                                <td>
                                    @php $c = match ($a->status) { 'HADIR' => 'green', 'TERLAMBAT' => 'yellow', 'IZIN' => 'blue', 'SAKIT' => 'yellow', 'ALPHA' => 'red', default => 'gray'}; @endphp
                                    <span class="badge badge-{{ $c }}">{{ $a->status }}</span>
                                </td>
                                <td style="font-size:12px;color:#6b7280">{{ $a->time ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align:center;color:#9ca3af;padding:20px;font-size:13px">Belum ada
                                    data absensi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
