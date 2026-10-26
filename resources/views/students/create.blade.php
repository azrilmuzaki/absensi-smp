@extends('layouts.app')
@section('page-title', 'Tambah Siswa')
@section('page-actions')
    <a href="{{ route('students.index', request()->query()) }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar siswa</a>
@endsection
@section('content')

    <div class="card" style="max-width:560px">
        <div class="card-header">
            <div class="card-title">Tambah Siswa Baru</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('students.store', request()->query()) }}">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label>NIS <span style="color:#dc2626">*</span></label>
                        <input type="text" name="nis" class="form-control {{ $errors->has('nis') ? 'is-invalid' : '' }}"
                            value="{{ old('nis') }}" placeholder="12345678" required />
                        @error('nis')
                        <div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>NISN</label>
                        <input type="text" name="nisn" class="form-control {{ $errors->has('nisn') ? 'is-invalid' : '' }}"
                            value="{{ old('nisn') }}" placeholder="Opsional" />
                        @error('nisn')
                        <div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name') }}" placeholder="Nama siswa" required />
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Kelamin <span style="color:#dc2626">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="">Pilih...</option>
                            <option value="L" {{ old('gender') === 'L' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="P" {{ old('gender') === 'P' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                        @error('gender')
                        <div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date') }}" />
                    </div>
                </div>

                @if($activeYear)
                    <div class="form-group">
                        <label>Kelas ({{ $activeYear->name }}) <span style="color:#dc2626">*</span></label>
                        <select name="school_class_id" class="form-control" required>
                            <option value="">Pilih Kelas...</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}" {{ old('school_class_id') == $class->id ? 'selected' : '' }}>
                                    {{ $class->name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="academic_year_id" value="{{ $activeYear->id }}" />
                    </div>
                @else
                    <div class="alert alert-warning">Tidak ada tahun ajaran aktif. Aktifkan tahun ajaran terlebih dahulu.</div>
                @endif

                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary" {{ !$activeYear ? 'disabled' : '' }}>Simpan</button>
                    <a href="{{ route('students.index', request()->query()) }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                </div>
            </form>
        </div>
    </div>

@endsection
