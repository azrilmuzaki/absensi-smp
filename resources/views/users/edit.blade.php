@extends('layouts.app')
@section('page-title', 'Edit User')
@section('page-actions')
    <a href="{{ route('users.index', request()->query()) }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar pengguna</a>
@endsection
@section('content')

    <div class="card" style="max-width:520px">
        <div class="card-header">
            <div class="card-title">Edit User: {{ $user->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.update', array_merge(request()->query(), ['user' => $user])) }}">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required />
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}"
                        required />
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password Baru <small style="color:#9ca3af">(kosongkan jika tidak diubah)</small></label>
                        <input type="password" name="password" class="form-control" />
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="form-control" />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control" onchange="toggleClassField(this.value)">
                            <option value="WALI_KELAS" {{ old('role', $user->role) === 'WALI_KELAS' ? 'selected' : '' }}>Wali
                                Kelas</option>
                            <option value="ADMIN" {{ old('role', $user->role) === 'ADMIN' ? 'selected' : '' }}>Admin</option>
                        </select>
                    </div>
                    <div class="form-group" id="class-field">
                        <label>Kelas</label>
                        <select name="class_id" class="form-control">
                            <option value="">Pilih Kelas...</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ old('class_id', $user->class_id) == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status Akun</label>
                    <select name="is_active" class="form-control">
                        <option value="1" {{ $user->is_active ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ !$user->is_active ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <a href="{{ route('users.index', request()->query()) }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function toggleClassField(role) {
                document.getElementById('class-field').style.display = role === 'WALI_KELAS' ? 'block' : 'none';
            }
            toggleClassField(document.querySelector('select[name=role]').value);
        </script>
    @endpush
@endsection
