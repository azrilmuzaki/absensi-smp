@extends('layouts.app')
@section('page-title', 'Tambah User')
@section('page-actions')
    <a href="{{ route('users.index', request()->query()) }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar pengguna</a>
@endsection
@section('content')

    <div class="card" style="max-width:520px">
        <div class="card-header">
            <div class="card-title">Tambah User Baru</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.store', request()->query()) }}">
                @csrf
                <div class="form-group">
                    <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name') }}" required />
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#dc2626">*</span></label>
                    <input type="email" name="email" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                        value="{{ old('email') }}" required />
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span style="color:#dc2626">*</span></label>
                        <input type="password" name="password" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role <span style="color:#dc2626">*</span></label>
                        <select name="role" class="form-control" required onchange="toggleClassField(this.value)">
                            <option value="WALI_KELAS" {{ old('role') === 'WALI_KELAS' ? 'selected' : '' }}>Wali Kelas
                            </option>
                            <option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>Admin</option>
                        </select>
                    </div>
                    <div class="form-group" id="class-field">
                        <label>Kelas (Wali) <span style="color:#dc2626">*</span></label>
                        <select name="class_id" class="form-control {{ $errors->has('class_id') ? 'is-invalid' : '' }}">
                            <option value="">Pilih Kelas...</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ old('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('class_id')
                        <div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Simpan</button>
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
