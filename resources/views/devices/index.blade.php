@extends('layouts.app')
@section('page-title', 'Perangkat ESP32')
@section('content')

    <div class="alert alert-warning" role="note">
        <span class="alert-mark" aria-hidden="true">i</span>
        <span>API Key dibuat otomatis saat perangkat ditambahkan. Salin dari kolom API Key ke <code>DEVICE_API_KEY</code> di file <code>esp32/firmware/secrets.h</code>, lalu unggah ulang firmware. Jika key dibuat ulang, key di firmware juga harus diperbarui.</span>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Daftar Perangkat Absensi</div>
            <button type="button" class="btn btn-primary btn-sm" data-modal-open="modal-add">+
                Tambah Perangkat</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Terakhir Online</th>
                        <th>API Key</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td><code>{{ $device->device_code }}</code></td>
                            <td><strong>{{ $device->device_name }}</strong></td>
                            <td>
                                @php $isOnline = $device->last_seen_at && \Carbon\Carbon::parse($device->last_seen_at)->diffInMinutes() < 3; @endphp
                                <span class="badge {{ $isOnline ? 'badge-green' : 'badge-gray' }}">
                                    {{ $isOnline ? '🟢 ONLINE' : '⚫ OFFLINE' }}
                                </span>
                            </td>
                            <td style="font-size:12px;color:#6b7280">
                                {{ $device->last_seen_at ? \Carbon\Carbon::parse($device->last_seen_at)->diffForHumans() : 'Belum pernah' }}
                            </td>
                            <td>
                                <div class="device-key">
                                    <code>{{ $device->api_key }}</code>
                                    <button type="button" class="btn btn-outline btn-sm" data-copy-text="{{ $device->api_key }}" aria-label="Salin API Key">Salin</button>
                                </div>
                            </td>
                            <td>
                                <div class="table-actions">
                                <form method="POST" action="{{ route('devices.regenerate-key', $device) }}">
                                    @csrf
                                    <button class="btn btn-outline btn-sm"
                                        onclick="return confirm('Generate ulang API Key? ESP32 harus diupdate firmwarenya.')">🔄
                                        Regen Key</button>
                                </form>
                                <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                    onsubmit="return confirm('Hapus perangkat ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;color:#9ca3af;padding:24px">Belum ada perangkat terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah --}}
    <div id="modal-add" class="app-modal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:420px">
            <h3 id="modal-add-title" style="font-size:16px;margin-bottom:20px">Tambah Perangkat ESP32</h3>
            <form method="POST" action="{{ route('devices.store') }}">
                @csrf
                <div class="form-group">
                    <label>Kode Perangkat <span style="color:#dc2626">*</span></label>
                    <input type="text" name="device_code" class="form-control" placeholder="Contoh: ESP32-ABSEN-02"
                        value="{{ old('device_code') }}" data-modal-autofocus required />
                    <div class="form-hint">Harus unik. Digunakan sebagai identitas perangkat.</div>
                </div>
                <div class="form-group">
                    <label>Nama Lokasi <span style="color:#dc2626">*</span></label>
                    <input type="text" name="device_name" class="form-control" placeholder="Contoh: Gerbang Utama"
                        value="{{ old('device_name') }}" required />
                </div>
                <div class="alert alert-warning" style="margin-bottom:16px">
                    API Key akan muncul di daftar perangkat setelah disimpan. Salin key itu ke file <code>secrets.h</code>.
                </div>
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary">Tambah</button>
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->has('device_code') || $errors->has('device_name'))
        @push('scripts')
            <script>window.openAppModal('modal-add');</script>
        @endpush
    @endif
@endsection
