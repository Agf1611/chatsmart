<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    
    public function index(){
        return view('auth.login');
    }

    public function store(Request $request){
        $credentials = $request->only(['username', 'password']);
        $user = User::where('username', $credentials['username'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            if ($user->level !== 'admin' && $user->status !== 'active') {
                throw ValidationException::withMessages([
                    'username' => 'Akun Anda masih menunggu persetujuan admin.',
                ]);
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect('/home');
        }

     throw ValidationException::withMessages([
         'username' => 'The provided credentials do not match our records.',
     ]);
    
    }
}
