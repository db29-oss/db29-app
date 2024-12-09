<?php

namespace App\Http\Controllers;

use App\Jobs\InitInstance;
use App\Jobs\TermInstance;
use App\Jobs\TurnOffInstance;
use App\Jobs\TurnOnInstance;
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
        $user->name = $substr;
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
        $instances = Instance::whereUserId(auth()->user()->id)->with('source')->get();

        $sn_ii_map = []; // source_name_instance_id_map

        foreach ($instances as $instance) {
            if (! array_key_exists($instance->source->name, $sn_ii_map)) {
                $sn_ii_map[$instance->source->name] = [];
            }

            $sn_ii_map[$instance->source->name][$instance->id] = true;
        }

        ksort($sn_ii_map);

        return view('instance.instance')
            ->with('instances', $instances)
            ->with('sn_ii_map', $sn_ii_map);
    }

    public function deleteInstance()
    {
        $now = now();
        $sql_params = [];
        $sql = 'update instances set '.
            'queue_active = ?, '. # true
            'updated_at = ? '. # $now
            'where id = ? '.# request('instance_id')
            'and status = ? '. # 'ct_dw'
            'and queue_active = ? '. # false
            'and user_id = ? '. # auth()->user()->id
            'returning id';

        $sql_params[] = true;
        $sql_params[] = $now;
        $sql_params[] = request('instance_id');
        $sql_params[] = 'ct_dw';
        $sql_params[] = false;
        $sql_params[] = auth()->user()->id;

        $db = app('db')->select($sql, $sql_params);

        if (! count($db)) {
            return redirect()->route('instance');
        }

        TermInstance::dispatch($db[0]->id);

        return redirect()->route('instance');
    }

    public function turnOnInstance()
    {
        $now = now();
        $sql_params = [];
        $sql = 'update instances set '.
            'queue_active = ?, '. # true
            'updated_at = ? '. # $now
            'where id = ? '.# request('instance_id')
            'and status = ? '. # 'ct_dw'
            'and queue_active = ? '. # false
            'and user_id = ? '. # auth()->user()->id
            'returning id';

        $sql_params[] = true;
        $sql_params[] = $now;
        $sql_params[] = request('instance_id');
        $sql_params[] = 'ct_dw';
        $sql_params[] = false;
        $sql_params[] = auth()->user()->id;

        $db = app('db')->select($sql, $sql_params);

        if (! count($db)) {
            return redirect()->route('instance');
        }

        TurnOnInstance::dispatch($db[0]->id);

        return redirect()->route('instance');
    }

    public function turnOffInstance()
    {
        $now = now();
        $sql_params = [];
        $sql = 'update instances set '.
            'queue_active = ?, '. # true
            'updated_at = ? '. # $now
            'where id = ? '.# request('instance_id')
            'and status = ? '. # 'rt_up'
            'and queue_active = ? '. # false
            'and user_id = ? '. # auth()->user()->id
            'returning id';

        $sql_params[] = true;
        $sql_params[] = $now;
        $sql_params[] = request('instance_id');
        $sql_params[] = 'rt_up';
        $sql_params[] = false;
        $sql_params[] = auth()->user()->id;

        $db = app('db')->select($sql, $sql_params);

        if (! count($db)) {
            return redirect()->route('instance');
        }

        TurnOffInstance::dispatch($db[0]->id);

        return redirect()->route('instance');
    }

    public function source()
    {
        $sources = Source::where('enabled', true)->orderBy('name')->get();

        return view('source')->with('sources', $sources);
    }

    public function account()
    {
        return view('account');
    }

    public function postAccount()
    {
        $validator = validator(request()->all(), [ 
            'email' => ['required', 'email:rfc'],
            'name' => ['required', 'alpha_num:ascii'], // docker-compose env validation complexity
            'username' => ['required', 'alpha_num:ascii'],
        ]);

        $data = $validator->validated();

        $user = auth()->user();

        if ($data['email']) {
            $user->email = $data['email'];
        }

        if ($data['name']) {
            $user->name = $data['name'];
        }

        if ($data['username']) {
            $user->username = $data['username'];
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

        $source = Source::whereName($source_name)->where('enabled', true)->first('id');

        if ($source === null) {
            return redirect()->route('source');
        }

        if (! view()->exists('instance.'.$source_name.'.register')) {

            return redirect()->route('source');
        }

        $i_s_count = Instance::query()
            ->where('user_id', auth()->user()->id)
            ->where('source_id', $source->id)
            ->count();

        return view('instance.'.$source_name.'.register')->with('i_s_count', $i_s_count);
    }

    public function postRegisterInstance()
    {
        $source_name = request('source');

        $source = Source::whereName($source_name)->where('enabled', true)->first(['id', 'name']);

        if (! $source) {
            return redirect()->route('source');
        }

        $reg_info = $this->{'filter_input_'.$source_name}();

        $now = now()->toDateTimeString();

        $instance_id = str()->uuid()->toString();

        $sql_params = [];
        $sql =
            'begin; '.
            'update users set '.
            'instance_count = instance_count + 1, '.
            'updated_at = \''.$now.'\' '.
            'where id = \''.auth()->user()->id.'\'; '.
            'insert into instances ('.
                'id, '.
                'source_id, '.
                'user_id, '.
                'queue_active, '.
                'created_at, '.
                'updated_at'.
            ') values ('.
                '\''.$instance_id.'\', '. # $instance_id
                '\''.$source->id.'\', '. # $source->id
                '\''.auth()->user()->id.'\', '. # auth()->user()->id
                'true, '. # true
                '\''.$now.'\', '. # $now
                '\''.$now.'\' '. # $now
            '); '.
            'commit;';

        $db = app('db')->unprepared($sql);

        if ($db !== true) {
            return redirect()->route('source');
        }

        InitInstance::dispatch($instance_id, $reg_info);

        return redirect()->route('instance');
    }

    protected function filter_input_planka()
    {
        $validator = validator(request()->all(), [ 
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'alpha_num:ascii'],
            'name' => ['required', 'alpha_num:ascii'],
            'username' => ['required', 'alpha_num:ascii'],
        ]);

        $data = $validator->validated();

        $reg_info = [];

        $reg_info['email'] = request('email');
        $reg_info['password'] = request('password');
        $reg_info['name'] = request('name');
        $reg_info['username'] = request('username');
        $reg_info['secret_key'] = bin2hex(random_bytes(64));

        return $reg_info;
    }
}
