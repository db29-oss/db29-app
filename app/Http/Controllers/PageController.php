<?php

namespace App\Http\Controllers;

use App\Jobs\InitInstance;
use App\Models\Instance;
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
        $user = User::whereLoginId(request('login_id'))->first();

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
        $user->login_id = str()->random(31);

        $substr = substr($user->login_id, 0, 11);

        $user->email = $substr.'__@db29.ovh';
        $user->username = $substr;
        $user->save();

        return view('register')->with('user', $user);
    }

    public function dashboard()
    {
        return view('dashboard');
    }

    public function instance()
    {
        return view('instance.instance');
    }

    public function source()
    {
        $sources = Source::where('enabled', true)->orderBy('name')->get();

        return view('source')->with('sources', $sources);
    }

    public function accountUpdate()
    {
        return view('account_update');
    }

    public function postAccountUpdate()
    {
        $validator = validator(request()->all(), [ 
            'email' => 'email:rfc'
        ]);

        $data = $validator->validated();

        $user = auth()->user();

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

    public function registerInstance()
    {
        $source_name = request('source');

        $source_exists = Source::whereName($source_name)->where('enabled', true)->exists();

        if (! $source_exists) {
            return redirect()->route('source');
        }

        if (! view()->exists('instance.'.$source_name.'.register')) {
            return redirect()->route('source');
        }

        return view('instance.'.$source_name.'.register');
    }

    public function postRegisterInstance()
    {
        $source_name = request('source');

        $source = Source::whereName($source_name)->where('enabled', true)->first(['id', 'name']);

        if (! $source) {
            return redirect()->route('source');
        }

        $now = now();
        $sql_params = [];
        $sql = 'with '.
            'update_user as ('.
                'update users set '.
                'instance_count = instance_count + 1, '.
                'updated_at = ? '. # $now
                'where id = ? '. # auth()->user()->id
                'returning id'.
            ') '.
            'insert into instances ('.
                'source_id, '.
                'user_id, '.
                'created_at, '.
                'updated_at'.
            ') values ('.
                '?, '. # $source->id
                '?, '. # auth()->user()->id
                '?, '. # $now
                '?'. # $now
            ') '.
            'returning id';

        $sql_params[] = $now;
        $sql_params[] = auth()->user()->id;

        $sql_params[] = $source->id;
        $sql_params[] = auth()->user()->id;
        $sql_params[] = $now;
        $sql_params[] = $now;

        $db = app('db')->select($sql, $sql_params);

        $reg_info = $this->{'filter_input_'.$source_name}();

        InitInstance::dispatch($db[0]->id, $source->id, $reg_info);

        return redirect()->route('instance');
    }

    protected function filter_input_planka()
    {
        $reg_info = [];

        $reg_info['email'] = request('email');
        $reg_info['password'] = request('password');
        $reg_info['name'] = request('name');
        $reg_info['username'] = request('username');

        return $reg_info;
    }
}
