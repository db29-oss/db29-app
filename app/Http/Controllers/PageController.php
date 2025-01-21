<?php

namespace App\Http\Controllers;

use App\Jobs\InitInstance;
use App\Jobs\PrepareMachine;
use App\Jobs\PrepareTrafficRouter;
use App\Jobs\TermInstance;
use App\Jobs\TurnOffInstance;
use App\Jobs\TurnOnInstance;
use App\Models\Instance;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\Source;
use App\Models\TrafficRouter;
use App\Models\User;
use App\Rules\IANAPortNumber;
use App\Rules\Ipv4OrDomainARecordExists;
use App\Rules\SSHPrivateKeyRule;
use App\Rules\ValidPathFormat;
use App\Services\InstanceInputFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

        $login_id = str()->random(31);
        $substr = substr($login_id, 0, 11);

        $sql = 'delete from recharge_number_holes where '.
            'recharge_number = (select recharge_number from recharge_number_holes limit 1) '.
            'returning recharge_number';

        $db = app('db')->select($sql);

        $now = now();
        $sql_params = [];
        $sql =
            'insert into users ('.
                'login_id, bonus_credit, email, name, username, '.
                'recharge_number, last_logged_in_at, created_at, updated_at'.
            ') '.
            'values ('.
                '?, '. # str()->random(31)
                '?, '. # User::SIGN_UP_CREDIT
                '?, '. # $substr.'__@db29.ovh'
                '?, '. # $substr
                '?, '; # $substr

        $sql_params[] = str()->random(31);
        $sql_params[] = User::SIGN_UP_CREDIT;
        $sql_params[] = $substr.'__@db29.ovh';
        $sql_params[] = $substr;
        $sql_params[] = $substr;

        if (count($db) === 0) {
            $sql .=  '(select coalesce(max(recharge_number), 0) + 1 as recharge_number from users), ';
        } else {
            $sql .= '?, '; # $db[0]->recharge_number;
            $sql_params[] = $db[0]->recharge_number;
        }

        $sql .=
                '?, '. # $now
                '?, '. # $now
                '?'. # $now
            ') returning *';

        $sql_params[] = $now;
        $sql_params[] = $now;
        $sql_params[] = $now;

        $user = User::hydrate(app('db')->select($sql, $sql_params))[0];

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
        $sources = Source::query()
            ->where('enabled', true)
            ->whereHas('plans', function (Builder $q) {
                $q->where('base', true);
            })
            ->with(['plans' => function ($q) {
                $q->where('base', true);
            }])
            ->orderBy('name')
            ->get();

        $user = auth()->user();

        $user_setting = json_decode($user->setting, true);

        return view('source')
            ->with('sources', $sources)
            ->with('user', $user)
            ->with('user_setting', $user_setting);
    }

    public function prefill()
    {
        return view('prefill');
    }

    public function postPrefill()
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

    public function recharge()
    {
        $banking_details = json_decode(Setting::where('k', 'banking_details')->first()?->v, true);

        if ($banking_details === null) {
            $banking_details = [];
        }

        $crypto_details = json_decode(Setting::where('k', 'crypto_details')->first()?->v, true);

        if ($crypto_details === null) {
            $crypto_details = [];
        }

        return view('recharge')
            ->with('banking_details', $banking_details)
            ->with('crypto_details', $crypto_details);
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

        $source = Source::query()
            ->whereName($source_name)
            ->whereHas('plans', function (Builder $q) {
                $q->where('base', true);
            })
            ->where('enabled', true)
            ->first('id');

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

        $source = Source::whereName($source_name)
            ->where('enabled', true)
            ->whereHas('plans', function (Builder $q) {
                $q->where('base', true);
            })
            ->with(['plans' => function ($query) {
                $query->where('base', true);
            }])
            ->first(['id', 'name']);

        if ($source === null) {
            return redirect()->route('source');
        }

        $reg_info = [];

        if (method_exists(InstanceInputFilter::class, $source_name)) {
            $reg_info = InstanceInputFilter::$source_name();
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
                'plan_id, '.
                'queue_active, '.
                'extra, '.
                'created_at, '.
                'updated_at'.
            ') values ('.
                '?, '. # $source->id
                '?, '. # auth()->user()->id
                '?, '. # $source->plans[0]->id
                '?, '. # true
                '?, '. # json_encode(['reg_info' => $this->reg_info])
                '?, '. # $now
                '?'. # $now
            ') returning id';

        $sql_params[] = $now;
        $sql_params[] = auth()->user()->id;

        $sql_params[] = $source->id;
        $sql_params[] = auth()->user()->id;
        $sql_params[] = $source->plans[0]->id;
        $sql_params[] = true;
        $sql_params[] = json_encode(['reg_info' => $reg_info]);
        $sql_params[] = $now;
        $sql_params[] = $now;

        $db = app('db')->select($sql, $sql_params);

        if (count($db) === 0) {
            return redirect()->route('source');
        }

        InitInstance::dispatch($db[0]->id, $reg_info);

        return redirect()->route('instance');
    }

    public function server()
    {
        $machines = Machine::where('user_id', auth()->user()->id)->get();

        return view('server.server')->with('machines', $machines);
    }

    public function addServer()
    {
        return view('server.add');
    }

    public function postAddServer()
    {
        $validator = validator(request()->all(), [
            'ssh_address' => ['required', new Ipv4OrDomainARecordExists],
            'ssh_port' => ['required', new IANAPortNumber],
            'ssh_privatekey' => ['required', new SSHPrivateKeyRule],
            'storage_path' => ['nullable', new ValidPathFormat],
        ]);

        $data['ssh_address'] = request('ssh_address');
        $data['ssh_port'] = request('ssh_port');
        $data['ssh_privatekey'] = request('ssh_privatekey');
        $data['storage_path'] = request('storage_path');

        if (app('env') === 'production') {
            $data = $validator->validated();
        }

        DB::transaction(function () use ($data) {
            $machine = new Machine;
            $machine->hostname = $data['ssh_address'];
            $machine->ip_address = fake()->ipv4();

            if (app('env') === 'production') {
                if (filter_var($data['ssh_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $machine->ip_address = $data['ssh_address'];
                } else {
                    $dns_get_record = dns_get_record($data['ssh_address'], DNS_A);

                    $machine->ip_address = $dns_get_record[0]['ip'];
                }
            }

            $machine->ssh_port = $data['ssh_port'];
            $machine->ssh_privatekey = $data['ssh_privatekey'];
            $machine->storage_path = $data['storage_path'] ?? '/opt/';
            $machine->save();

            $traffic_router = new TrafficRouter;
            $traffic_router->machine_id = $machine->id;
            $traffic_router->save();

            PrepareMachine::dispatch($machine->id);

            PrepareTrafficRouter::dispatch($traffic_router->id);
        });
    }

    public function editServer()
    {
    }

    public function postEditServer()
    {
    }

    public function deleteServer()
    {
    }

    public function postDeleteServer()
    {
    }
}
