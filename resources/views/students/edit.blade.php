@extends('layouts.app')
@section('page-title', 'Edit Siswa')
@section('page-actions')
    @if(request('back') === 'detail')
        <a href="{{ route('students.show', array_merge(request()->except('back'), ['student' => $student])) }}" class="btn btn-outline" data-restore-scroll>← Kembali ke detail siswa</a>
    @else
        <a href="{{ route('students.index', request()->except('back')) }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar siswa</a>
    @endif
@endsection
@section('content')

    <div class="card" style="max-width:560px">
        <div class="card-header">
            <div class="card-title">Edit Siswa: {{ $student->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('students.update', array_merge(request()->query(), ['student' => $student])) }}">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="form-group">
                        <label>NIS <span style="color:#dc2626">*</span></label>
                        <input type="text" name="nis" class="form-control {{ $errors->has('nis') ? 'is-invalid' : '' }}"
                            value="{{ old('nis', $student->nis) }}" required />
                        @error('nis')
                        <div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>NISN</label>
                        <input type="text" name="nisn" class="form-control {{ $errors->has('nisn') ? 'is-invalid' : '' }}"
                            value="{{ old('nisn', $student->nisn) }}" />
                        @error('nisn')
                        <div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name', $student->name) }}" required />
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Kelamin <span style="color:#dc2626">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="L" {{ old('gender', $student->gender) === 'L' ? 'selected' : '' }}>Laki-laki
                            </option>
                            <option value="P" {{ old('gender', $student->gender) === 'P' ? 'selected' : '' }}>Perempuan
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="birth_date" class="form-control"
                            value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}" />
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    @if(request('back') === 'detail')
                        <a href="{{ route('students.show', array_merge(request()->except('back'), ['student' => $student])) }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                    @else
                        <a href="{{ route('students.index', request()->except('back')) }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

@endsection
