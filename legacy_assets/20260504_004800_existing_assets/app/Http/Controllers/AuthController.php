<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        $credentials = $this->ensureDefaultUserExists();

        return view('auth.login', [
            'defaultEmail' => $credentials['email'],
            'defaultPassword' => $credentials['password'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
            'password.required' => 'パスワードを入力してください。',
        ]);

        $this->ensureDefaultUserExists();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'email' => 'メールアドレスまたはパスワードが正しくありません。',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('memories.bubbles'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Create a single login user when the app is first opened.
     *
     * @return array{email: string, password: string}
     */
    private function ensureDefaultUserExists(): array
    {
        $email = config('bunshin.auth.email');
        $password = config('bunshin.auth.password');
        $name = config('bunshin.auth.name');

        if (! Schema::hasTable('users')) {
            return [
                'email' => $email,
                'password' => $password,
            ];
        }

        if (! User::query()->exists()) {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        }

        return [
            'email' => $email,
            'password' => $password,
        ];
    }
}
