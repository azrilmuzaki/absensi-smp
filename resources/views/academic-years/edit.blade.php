@extends('layouts.app')
@section('page-title', 'Edit Tahun Ajaran')
@section('page-actions')
    <a href="{{ route('academic-years.index') }}" class="btn btn-outline" data-restore-scroll>← Kembali ke daftar tahun ajaran</a>
@endsection
@section('content')

    <div class="card" style="max-width:480px">
        <div class="card-header">
            <div class="card-title">Edit Tahun Ajaran: {{ $year->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('academic-years.update', $year) }}">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Nama Tahun Ajaran</label>
                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        value="{{ old('name', $year->name) }}" required />
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control"
                            value="{{ old('start_date', $year->start_date?->format('Y-m-d')) }}" />
                    </div>
                    <div class="form-group">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control"
                            value="{{ old('end_date', $year->end_date?->format('Y-m-d')) }}" />
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <a href="{{ route('academic-years.index') }}" class="btn btn-outline" data-restore-scroll>Batal</a>
                </div>
            </form>
        </div>
    </div>

@endsection
