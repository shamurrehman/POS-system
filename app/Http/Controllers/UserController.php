<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'cashier', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        ActivityLog::record('user.created', "Created user {$user->username} ({$user->role})", $user);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        // Never let the last active admin lock everyone out.
        if ($user->isAdmin() && ($data['role'] !== 'admin' || ! $data['is_active'])
            && User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist()) {
            return back()->withInput()->with('error', 'You cannot demote or disable the only active administrator.');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);
        ActivityLog::record('user.updated', "Updated user {$user->username}".(isset($data['password']) ? ' (password reset)' : ''), $user);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
