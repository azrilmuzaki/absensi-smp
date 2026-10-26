@extends('layouts.app')
@section('page-title', 'Manajemen User')
@section('content')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="user-search-form" role="search">
                <div class="form-group" style="margin:0;flex:1;min-width:220px">
                    <label for="user-search">Cari nama atau email</label>
                    <input type="search" id="user-search" name="search" class="form-control" value="{{ is_string(request('search')) ? request('search') : '' }}"
                        placeholder="Contoh: wali kelas atau nama@sekolah.sch.id" autocomplete="off" />
                </div>
                <div class="table-actions">
                    <button type="submit" class="btn btn-primary">Cari Pengguna</button>
                    @if(request()->filled('search'))
                        <a href="{{ route('users.index') }}" class="btn btn-outline">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Daftar Pengguna <span class="list-count">{{ $users->total() }}</span></div>
            <a href="{{ route('users.create', request()->query()) }}" class="btn btn-primary btn-sm">+ Tambah User</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td style="color:#6b7280;font-size:13px">{{ $user->email }}</td>
                            <td>
                                <span
                                    class="badge {{ $user->role === 'ADMIN' ? 'badge-purple' : 'badge-blue' }}">{{ $user->role }}</span>
                            </td>
                            <td>{{ $user->schoolClass?->name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'badge-green' : 'badge-red' }}">
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                <a href="{{ route('users.edit', array_merge(request()->query(), ['user' => $user])) }}" class="btn btn-outline btn-sm">Edit</a>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.toggle', $user) }}">
                                        @csrf
                                        <button class="btn btn-sm {{ $user->is_active ? 'btn-danger' : 'btn-success' }}">
                                            {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                {{ request()->filled('search') ? 'Tidak ada pengguna yang cocok dengan pencarian.' : 'Belum ada pengguna terdaftar.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="pagination-wrap">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
