<?php

namespace App\Http\Controllers;

use System\Auth\Auth;
use System\Database\DB;
use System\Http\Request;
use System\Http\Session;
use System\Security\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return $this->view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => "required|email|max:150|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:30',
        ]);

        DB::table('users')->where('id', $user->id)->update($data);
        Auth::refresh();

        flash('success', 'Profile updated.');
        redirect(url('/profile'));
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|max:255|confirmed',
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            Session::flash('_errors', ['current_password' => 'The current password is incorrect.']);
            redirect(url('/profile'));
        }

        DB::table('users')->where('id', $user->id)
            ->update(['password' => Hash::make($data['password']), 'remember_token' => null]);

        // New session id after a credential change
        Session::regenerate();

        flash('success', 'Password changed.');
        redirect(url('/profile'));
    }
}
