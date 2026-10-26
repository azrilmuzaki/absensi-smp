@extends('layouts.app')
@section('page-title', 'Data Kelas')
@section('content')

    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
        {{-- Filter Tahun --}}
        <form method="GET" style="display:flex;gap:8px;align-items:center">
            <select name="year_id" class="form-control" style="width:auto" onchange="this.form.submit()">
                @foreach($allYears as $y)
                    <option value="{{ $y->id }}" {{ $y->id == $year?->id ? 'selected' : '' }}>{{ $y->name }}</option>
                @endforeach
            </select>
        </form>

        {{-- Tambah Kelas --}}
        <button type="button" class="btn btn-primary btn-sm" data-modal-open="modal-add">+ Tambah
            Kelas</button>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Kelas untuk {{ $year?->name ?? 'Pilih Tahun Ajaran' }}</div>
            <span style="font-size:12px;color:#6b7280">{{ $classes->count() }} kelas</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Kelas</th>
                        <th>Jumlah Siswa</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $i => $class)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $class->name }}</strong></td>
                            <td>{{ $class->placements_count }} siswa</td>
                            <td>
                                <div class="table-actions">
                                <button type="button" class="btn btn-outline btn-sm" data-class-id="{{ $class->id }}"
                                    data-class-name="{{ $class->name }}" data-year-id="{{ $year->id }}"
                                    data-update-url="{{ route('school-classes.update', $class) }}" onclick="openEditModal(this)">Edit</button>

                                @if($class->placements_count == 0)
                                    <form method="POST" action="{{ route('school-classes.destroy', $class) }}"
                                        onsubmit="return confirm('Hapus kelas {{ $class->name }}? Tindakan ini tidak bisa dibatalkan.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                @else
                                    <button class="btn btn-danger btn-sm" disabled
                                        title="Tidak bisa dihapus — masih ada {{ $class->placements_count }} siswa"
                                        style="opacity:0.4;cursor:not-allowed">
                                        Hapus
                                    </button>
                                    <span class="action-note">Tidak bisa dihapus karena masih ada {{ $class->placements_count }} siswa di kelas ini.</span>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Belum ada kelas untuk tahun
                                ajaran ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah --}}
    <div id="modal-add" class="app-modal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:380px">
            <h3 id="modal-add-title" style="margin-bottom:20px;font-size:16px">Tambah Kelas</h3>
            <form method="POST" action="{{ route('school-classes.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="add" />
                <input type="hidden" name="academic_year_id" value="{{ $year?->id }}" />
                <div class="form-group">
                    <label>Nama Kelas</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="contoh: VII-A" data-modal-autofocus required />
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Edit --}}
    <div id="modal-edit" class="app-modal"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:380px">
            <h3 id="modal-edit-title" style="margin-bottom:20px;font-size:16px">Edit Kelas</h3>
            <form id="form-edit" method="POST">
                @csrf @method('PUT')
                <input type="hidden" name="_form_context" value="edit" />
                <input type="hidden" name="class_id" id="edit-class-id" />
                <input type="hidden" name="academic_year_id" id="edit-year-id" />
                <div class="form-group">
                    <label>Nama Kelas</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required />
                </div>
                <div style="display:flex;gap:10px;margin-top:8px">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function openEditModal(button) {
                document.getElementById('form-edit').action = button.dataset.updateUrl;
                document.getElementById('edit-class-id').value = button.dataset.classId;
                document.getElementById('edit-name').value = button.dataset.className;
                document.getElementById('edit-year-id').value = button.dataset.yearId;
                window.openAppModal('modal-edit', button);
            }

            @if($errors->has('name') && old('_form_context') === 'add')
                window.openAppModal('modal-add');
            @elseif($errors->has('name') && old('_form_context') === 'edit')
                const editedClass = [...document.querySelectorAll('[data-class-id]')]
                    .find(button => button.dataset.classId === @json((string) old('class_id')));
                if (editedClass) {
                    document.getElementById('form-edit').action = editedClass.dataset.updateUrl;
                    document.getElementById('edit-class-id').value = editedClass.dataset.classId;
                    document.getElementById('edit-name').value = @json(old('name'));
                    document.getElementById('edit-year-id').value = @json(old('academic_year_id'));
                    window.openAppModal('modal-edit');
                }
            @endif
        </script>
    @endpush
@endsection
