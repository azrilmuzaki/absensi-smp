@extends('layouts.app')
@section('page-title', 'Tahun Ajaran')
@section('content')

    <div class="card">
        <div class="card-header">
            <div class="card-title">Daftar Tahun Ajaran</div>
            <a href="{{ route('academic-years.create') }}" class="btn btn-primary btn-sm">+ Tambah</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($years as $year)
                        <tr>
                            <td><strong>{{ $year->name }}</strong></td>
                            <td>{{ $year->start_date?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $year->end_date?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @if($year->is_active)
                                    <span class="badge badge-green">AKTIF</span>
                                @else
                                    <span class="badge badge-gray">Tidak Aktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                @if(!$year->is_active)
                                    <form method="POST" action="{{ route('academic-years.activate', $year) }}"
                                        onsubmit="return confirm('Aktifkan tahun ajaran {{ $year->name }}?')">
                                        @csrf
                                        <button class="btn btn-success btn-sm">Aktifkan</button>
                                    </form>
                                    <a href="{{ route('academic-years.edit', $year) }}" class="btn btn-outline btn-sm">Edit</a>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">Belum ada tahun ajaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
