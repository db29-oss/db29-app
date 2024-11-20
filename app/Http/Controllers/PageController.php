<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class PageController extends Controller
{
    public function homepage()
    {
        return view('homepage');
    }

    public function login()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function postLogin()
    {
        $user = User::whereUsername(request('username'))->first();

        if ($user === null) {
            return redirect()->route('login');
        }

        auth()->login($user);

        $user->last_logged_in_at = now();
        $user->save();

        return redirect()->route('dashboard');
    }

    public function postLogout()
    {
        auth()->logout();

        return view('login');
    }

    public function postRegister()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $user = new User;
        $user->username = str()->random(31);
        $user->save();

        return view('register')->with('user', $user);
    }

    public function dashboard()
    {
        return view('dashboard');
    }

    public function registeredService()
    {
        return view('registered_service');
    }

    public function supportedSource()
    {
        $sources = Source::orderBy('name')->get();

        return view('supported_source')->with('sources', $sources);
    }

    public function accountUpdate()
    {
        return view('account_update');
    }

    public function postAccountUpdate()
    {
        $user = auth()->user();

        $validator = validator(request()->all(), [ 
            'email' => 'email:rfc'
        ]);

        $data = $validator->validated();

        if ($data['email']) {
            $user->email = $data['email'];
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return view('dashboard');
    }

    public function faq()
    {
        return view('faq');
    }

    public function advancedFeature()
    {
        return view('advanced_feature');
    }
}
