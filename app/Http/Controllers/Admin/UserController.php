<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $users = User::query()
            ->latest('created_at')
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ]);

        $data['password'] = bcrypt($data['password']);

        User::query()->create($data);

        return Redirect::route('admin.users.index')->with('status', 'User created.');
    }
}
