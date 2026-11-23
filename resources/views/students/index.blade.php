@extends('layouts.app')
@section('page-title', 'Data Siswa')
@section('content')

    {{-- Filter --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="padding:14px 20px">
            <form id="student-filter-form" method="GET" action="{{ route('students.index') }}" role="search"
                class="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="flex min-w-0 flex-col gap-1 sm:col-span-2 @if(auth()->user()->role === 'ADMIN') lg:col-span-2 @else lg:col-span-3 @endif">
                    <label for="student-search" class="text-xs font-medium text-slate-600">Cari Nama / NIS / NISN</label>
                    <input id="student-search" type="search" name="search" class="form-control"
                        value="{{ is_string(request('search')) ? request('search') : '' }}"
                        placeholder="Ketik untuk mencari..." autocomplete="off" />
                </div>
                @if(auth()->user()->role === 'ADMIN')
                    <div class="flex min-w-0 flex-col gap-1 lg:col-span-1">
                        <label for="student-class-filter" class="text-xs font-medium text-slate-600">Kelas</label>
                        <select id="student-class-filter" name="class_id" class="form-control" aria-label="Filter kelas">
                            <option value="">Semua Kelas</option>
                            @foreach($allClasses as $c)
                                <option value="{{ $c->id }}" {{ request('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex min-w-0 flex-col gap-1 lg:col-span-1">
                    <label for="student-status-filter" class="text-xs font-medium text-slate-600">Status</label>
                    <select id="student-status-filter" name="status" class="form-control" aria-label="Filter status siswa">
                        <option value="">Semua Status</option>
                        <option {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option {{ request('status') === 'LULUS' ? 'selected' : '' }}>LULUS</option>
                        <option {{ request('status') === 'NONAKTIF' ? 'selected' : '' }}>NONAKTIF</option>
                    </select>
                </div>
                <div class="flex min-w-0 justify-end sm:col-span-2 lg:col-span-1">
                    <a href="{{ route('students.index') }}" class="btn btn-outline btn-sm w-full justify-center sm:w-auto" data-live-search-reset>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" id="student-results" aria-busy="false">
        <div class="card-header">
            <div class="card-title">Daftar Siswa</div>
            @if(auth()->user()->role === 'ADMIN')
                <a href="{{ route('students.create', request()->query()) }}" class="btn btn-primary btn-sm">+ Tambah Siswa</a>
            @endif
        </div>
        <div class="table-wrap overflow-x-auto shadow-sm rounded-lg">
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

                                @if(auth()->user()->role === 'ADMIN')
                                    <form method="POST" action="{{ route('students.destroy', array_merge(request()->query(), ['student' => $student])) }}"
                                        onsubmit="return confirm('Hapus permanen siswa ini? Data absensi, kartu RFID, dan riwayat kelas terkait juga akan terhapus dan tidak dapat dipulihkan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
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

    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('student-filter-form');
                const searchInput = document.getElementById('student-search');
                let results = document.getElementById('student-results');
                let debounceTimer;
                let activeRequest;
                let requestNumber = 0;

                async function updateResults() {
                    const url = new URL(form.action, window.location.origin);

                    for (const [name, value] of new FormData(form).entries()) {
                        if (typeof value === 'string' && value.trim() !== '') {
                            url.searchParams.set(name, value.trim());
                        }
                    }

                    url.searchParams.delete('page');
                    const thisRequest = ++requestNumber;
                    activeRequest?.abort();
                    activeRequest = new AbortController();
                    results.setAttribute('aria-busy', 'true');

                    try {
                        const response = await fetch(url, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: activeRequest.signal,
                        });

                        if (!response.ok) {
                            throw new Error('Pencarian siswa gagal.');
                        }

                        const payload = await response.json();
                        const template = document.createElement('template');
                        template.innerHTML = payload.html;
                        const nextResults = template.content.querySelector('#student-results');

                        if (!nextResults || thisRequest !== requestNumber) {
                            return;
                        }

                        results.replaceWith(nextResults);
                        results = nextResults;
                        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        window.location.assign(url.toString());
                    }
                }

                searchInput.addEventListener('input', () => {
                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(updateResults, 300);
                });

                form.querySelectorAll('select').forEach((select) => {
                    select.addEventListener('change', () => {
                        window.clearTimeout(debounceTimer);
                        updateResults();
                    });
                });

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    window.clearTimeout(debounceTimer);
                    updateResults();
                });

                form.querySelector('[data-live-search-reset]').addEventListener('click', (event) => {
                    event.preventDefault();
                    window.clearTimeout(debounceTimer);
                    searchInput.value = '';
                    form.querySelectorAll('select').forEach((select) => {
                        select.value = '';
                    });
                    updateResults();
                });
            })();
        </script>
    @endpush
@endsection
