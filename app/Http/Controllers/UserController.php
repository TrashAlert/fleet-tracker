<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users    = User::with('vehicle')->orderBy('role')->orderBy('name')->get();
        $vehicles = Vehicle::where('is_active', true)->get();
        return view('fleet.users', compact('users', 'vehicles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', Password::min(8)->mixedCase()->numbers()],
            'role'       => 'required|in:admin,manager,driver',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'is_active'  => 'boolean',
        ]);

        $data['password']  = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);

        // Only drivers can be linked to a vehicle
        if ($data['role'] !== 'driver') {
            $data['vehicle_id'] = null;
        }

        $user = User::create($data);

        ActivityLogger::logEvent(
            'user_created',
            "New user [{$user->name}] created with role: {$user->role}",
            'User', $user->id, $user->name,
            ['role' => $user->role, 'email' => $user->email]
        );

        return response()->json(['ok' => true, 'user' => $user->only('id', 'name', 'email', 'role', 'is_active')]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'role'       => 'required|in:admin,manager,driver',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'is_active'  => 'boolean',
        ]);

        if ($data['role'] !== 'driver') {
            $data['vehicle_id'] = null;
        }

        $data['is_active'] = $request->boolean('is_active', true);
        $user->update($data);

        ActivityLogger::logEvent(
            'user_updated',
            "User [{$user->name}] profile updated",
            'User', $user->id, $user->name,
            ['role' => $user->role]
        );

        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        ActivityLogger::logEvent(
            'password_reset',
            "Password reset for user [{$user->name}] by admin",
            'User', $user->id, $user->name
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'You cannot delete your own account.'], 403);
        }

        $label = $user->name;
        $id    = $user->id;
        $user->delete();

        ActivityLogger::logEvent(
            'user_deleted',
            "User [{$label}] permanently deleted",
            'User', $id, $label
        );

        return response()->json(['ok' => true]);
    }
}
