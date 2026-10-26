@extends('layouts.app')
@section('page-title', 'Data Siswa')
@section('content')

    {{-- Filter --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="padding:14px 20px">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="margin:0;min-width:200px">
                    <label style="font-size:12px">Cari Nama / NIS / NISN</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                        placeholder="Ketik untuk mencari..." />
                </div>
                @if(auth()->user()->role === 'ADMIN')
                    <div class="form-group" style="margin:0">
                        <label style="font-size:12px">Kelas</label>
                        <select name="class_id" class="form-control">
                            <option value="">Semua Kelas</option>
                            @foreach($allClasses as $c)
                                <option value="{{ $c->id }}" {{ request('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group" style="margin:0">
                    <label style="font-size:12px">Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option {{ request('status') === 'LULUS' ? 'selected' : '' }}>LULUS</option>
                        <option {{ request('status') === 'NONAKTIF' ? 'selected' : '' }}>NONAKTIF</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                    <a href="{{ route('students.index') }}" class="btn btn-outline btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Daftar Siswa</div>
            @if(auth()->user()->role === 'ADMIN')
                <a href="{{ route('students.create', request()->query()) }}" class="btn btn-primary btn-sm">+ Tambah Siswa</a>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>L/P</th>
                        <th>Kelas Aktif</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        @php
                            $activePlacement = $student->placements->first();
                            $class = $activePlacement?->schoolClass;
                        @endphp
                        <tr>
                            <td><code>{{ $student->nis }}</code></td>
                            <td><strong>{{ $student->name }}</strong></td>
                            <td>{{ $student->gender === 'L' ? '♂ L' : '♀ P' }}</td>
                            <td>{{ $class?->name ?? '-' }}</td>
                            <td>
                                @php $sc = match ($student->status) { 'ACTIVE' => 'green', 'LULUS' => 'blue', 'NONAKTIF' => 'red', default => 'gray'}; @endphp
                                <span class="badge badge-{{ $sc }}">{{ $student->status }}</span>
                            </td>
                            <td>
                                <div class="table-actions">
                                <a href="{{ route('students.show', array_merge(request()->query(), ['student' => $student])) }}" class="btn btn-outline btn-sm">Detail</a>
                                <a href="{{ route('students.edit', array_merge(request()->query(), ['student' => $student, 'back' => 'list'])) }}" class="btn btn-outline btn-sm">Edit</a>

                                @if(auth()->user()->role === 'ADMIN' && $student->status === 'ACTIVE')
                                    <form method="POST" action="{{ route('students.deactivate', array_merge(request()->query(), ['student' => $student])) }}"
                                        onsubmit="return confirm('Nonaktifkan {{ $student->name }}?')">
                                        @csrf
                                        <input type="hidden" name="status" value="NONAKTIF">
                                        <button type="submit" class="btn btn-danger btn-sm">Nonaktifkan</button>
                                    </form>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;color:#9ca3af;padding:24px">Tidak ada siswa ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
            <div style="padding:16px">{{ $students->withQueryString()->links() }}</div>
        @endif
    </div>

@endsection
