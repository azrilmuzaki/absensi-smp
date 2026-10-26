@extends('layouts.app')
@section('page-title', 'Tambah Tahun Ajaran')
@section('page-actions')
    <a href="{{ route('academic-years.index') }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar tahun ajaran</a>
@endsection
@section('content')

    <div class="card" style="max-width:480px">
        <div class="card-header">
            <div class="card-title">Tambah Tahun Ajaran</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('academic-years.store') }}">
                @csrf
                <div class="form-group">
                    <label>Nama Tahun Ajaran</label>
                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name') }}" placeholder="contoh: 2027/2028" required />
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" />
                    </div>
                    <div class="form-group">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}" />
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('academic-years.index') }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                </div>
            </form>
        </div>
    </div>

@endsection
