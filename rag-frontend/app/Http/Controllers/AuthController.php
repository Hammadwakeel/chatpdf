<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller {
    public function showLogin() { return view('auth.login'); }
    public function showSignup() { return view('auth.signup'); }
    public function login(Request $request) {
        $response = Http::asForm()->post(env('FASTAPI_URL').'/login', [
            'username' => $request->username,
            'password' => $request->password,
        ]);
        if ($response->successful()) {
            Session::put('access_token', $response->json()['access_token']);
            return redirect()->route('dashboard');
        }
        return back()->withErrors(['message' => 'Invalid credentials']);
    }
    public function signup(Request $request) {
        $response = Http::asForm()->post(env('FASTAPI_URL').'/signup', [
            'username' => $request->username,
            'password' => $request->password,
        ]);
        if ($response->successful()) return redirect()->route('login');
        return back()->withErrors(['message' => 'Signup failed']);
    }
    public function logout() {
        Session::forget('access_token');
        return redirect()->route('login');
    }
}
