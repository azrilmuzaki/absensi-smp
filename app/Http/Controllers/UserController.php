<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
        ]);
        $search = trim($filters['search'] ?? '');
        $users = User::with('schoolClass')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $classes = SchoolClass::whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get();

        return view('users.create', compact('classes'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:ADMIN,WALI_KELAS',
            'class_id' => 'nullable|exists:school_classes,id',
        ]);

        if ($data['role'] === 'WALI_KELAS' && empty($data['class_id'])) {
            return back()->withErrors(['class_id' => 'Wali Kelas wajib memiliki kelas.'])->withInput();
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'class_id' => $data['role'] === 'WALI_KELAS' ? $data['class_id'] : null,
            'is_active' => true,
        ]);

        return redirect()->route('users.index', $request->query())->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        $classes = SchoolClass::whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get();

        return view('users.edit', compact('user', 'classes'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|in:ADMIN,WALI_KELAS',
            'class_id' => 'nullable|exists:school_classes,id',
            'is_active' => 'required|boolean',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'class_id' => $data['role'] === 'WALI_KELAS' ? $data['class_id'] : null,
            'is_active' => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()->route('users.index', $request->query())->with('success', 'User berhasil diperbarui.');
    }

    public function toggleActive(User $user)
    {
        abort_unless(auth()->user()->role === 'ADMIN', 403);
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa menonaktifkan diri sendiri.');

        $user->update(['is_active' => ! $user->is_active]);
        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "User {$user->name} berhasil {$statusText}.");
    }
}
