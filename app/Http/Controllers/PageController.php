<?php

namespace App\Http\Controllers;

use App\Jobs\InitInstance;
use App\Jobs\PrepareMachine;
use App\Jobs\PrepareTrafficRouter;
use App\Jobs\TermInstance;
use App\Jobs\TurnOffInstance;
use App\Jobs\TurnOnInstance;
use App\Jobs\UpdateUserOwnDomain;
use App\Models\Instance;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\Source;
use App\Models\TrafficRouter;
use App\Models\User;
use App\Rules\ARecordExactValue;
use App\Rules\CnameRecordExactValue;
use App\Rules\IANAPortNumber;
use App\Rules\InsufficientCredit;
use App\Rules\Ipv4OrDomainARecordExists;
use App\Rules\SSHConnectionWorks;
use App\Rules\UserOwnServer;
use App\Rules\ValidDomainFormat;
use App\Rules\ValidPathFormat;
use App\Services\InstanceInputFilter;
use App\Services\InstanceInputSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Pdp\Domain;
use Pdp\TopLevelDomains;
use phpseclib3\Crypt\EC;

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
        validator(request()->all(), [
            'email' => ['required', 'email:rfc'],
            'name' => ['required', 'alpha_num:ascii'], // docker-compose env validation complexity
            'username' => ['required', 'alpha_num:ascii'],
        ])->validated();

        $user = auth()->user();

        $user->email = request('email');
        $user->name = request('name');
        $user->username = request('username');
        $user->save();

        return redirect()->route('dashboard');
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

        $input_seeder = [];

        if (method_exists(InstanceInputSeeder::class, $source_name)) {
            $input_seeder = InstanceInputSeeder::$source_name();
        }

        $hostnames = Machine::whereUserId(auth()->user()->id)->pluck('hostname');

        return view('instance.register')
            ->with('hostnames', $hostnames)
            ->with('i_s_count', $i_s_count)
            ->with('input_seeder', $input_seeder)
            ->with('source_name', $source_name);
    }

    public function postRegisterInstance()
    {
        $source_name = request('source');

        $user = auth()->user();

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

        if (request('hostname')) {
            $machine = Machine::query()
                ->whereUserId(auth()->user()->id)
                ->whereHostname(request('hostname'))
                ->first('id');

            validator(
                [
                    'hostname' => request('hostname'),
                    'ins_cred' => $user->credit,
                ],
                [
                    'hostname' => new UserOwnServer($machine),
                    'ins_cred' => new InsufficientCredit($source->plans[0]->setup_price),
                ]
            )->validated();

            $reg_info['machine_id'] = $machine->id;
        }

        $now = now();

        $sql_params = [];
        $sql = 'with '.
            'update_user as ('.
                'update users set ';

        if (request('hostname')) {
            $sql .=
                'credit = credit - ?, '; # $source->plans[0]->setup_price

            $sql_params[] = $source->plans[0]->setup_price;
        }

        $sql .=
                'instance_count = instance_count + 1, '.
                'updated_at = ? '. # $now
                'where id = ? '. # auth()->user()->id
                'returning id'.
            '), '.
            'delete_tmp as ('.
                'delete from tmp '.
                'where user_id = ? '. # auth()->user()->id
                'and k = ? '. # $source_name
                'returning *'.
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
        $sql_params[] = $user->id;

        $sql_params[] = $user->id;
        $sql_params[] = $source_name;

        $sql_params[] = $source->id;
        $sql_params[] = $user->id;
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

    public function editInstance()
    {
        $instance = Instance::query()
            ->whereId(request('instance_id'))
            ->whereUserId(auth()->user()->id)
            ->first();

        if ($instance === null) {
            return redirect()->back();
        }

        return view('instance.edit')->with('instance', $instance);
    }

    public function postEditInstance()
    {
        $instance = Instance::query()
            ->whereId(request('instance_id'))
            ->whereUserId(auth()->user()->id)
            ->first();

        if ($instance === null) {
            return redirect()->back();
        }

        if (request('domain')) {
            validator(request()->all(), [
                'domain' => new ValidDomainFormat,
            ])->validated();

            $tlds_alpha_by_domain_path = storage_path('app/public/').'tlds-alpha-by-domain.txt';

            $top_level_domains = TopLevelDomains::fromPath($tlds_alpha_by_domain_path);

            $result = $top_level_domains->resolve(request('domain'));

            if ($result->subDomain()->toString() === '') {
                validator(request()->all(), [
                    'domain' => new ARecordExactValue(
                        $instance->subdomain.'.'.config('app.domain')
                    ),
                ])->validated();
            } else {
                validator(request()->all(), [
                    'domain' => new CnameRecordExactValue(
                        $instance->subdomain.'.'.config('app.domain')
                    ),
                ])->validated();
            }
        }

        $reg_info = [];

        if (request('domain')) {
            $reg_info['domain'] = request('domain');
        }


        DB::transaction(function () use ($instance) {
            $now = now();
            $sql_params = [];
            $sql = 'update instances set '.
                'extra = jsonb_set(extra, \'{reg_info,domain}\', \'"'.request('domain').'"\', true), '.
                'updated_at = ? '. # $now
                'where id = ?'; # $instance->id

            $sql_params[] = $now;
            $sql_params[] = $instance->id;

            DB::select($sql, $sql_params);

            $chain = [];
            $chain[] = new TurnOffInstance($instance->id);

            if (request('domain')) {
                $chain[] = new UpdateUserOwnDomain($instance->id);
            }

            $chain[] = new TurnOnInstance($instance->id);

            Bus::chain($chain)->dispatch();
        });
    }

    public function server()
    {
        $machines = Machine::where('user_id', auth()->user()->id)->withCount('instances')->get();

        return view('server.server')->with('machines', $machines);
    }

    public function addServer()
    {
        $server = [];
        // we might not use this
        $server['ssh_privatekey'] = EC::createKey('Ed25519')->toString('OpenSSH', ['comment' => '']);

        $now = now();
        $sql_params = [];
        $sql =
            'insert into tmp (user_id, k, v, created_at, updated_at) '.
            'values ('.
                '?, '. # auth()->user()->id
                '?, '. # 'server'
                '?, '. # json_encode($server)
                '?, '. # $now
                '?'. # $now
            ') on conflict (user_id, k) do update set '.
            'updated_at = ? '. # $now
            'returning *';

        $sql_params[] = auth()->user()->id;
        $sql_params[] = 'server';
        $sql_params[] = json_encode($server);
        $sql_params[] = $now;
        $sql_params[] = $now;

        $sql_params[] = $now;

        $db = app('db')->select($sql, $sql_params);

        $privatekey = EC::load(json_decode($db[0]->v, true)['ssh_privatekey']);

        $ssh_publickey = $privatekey->getPublicKey()->toString('OpenSSH', ['comment' => '']);

        return view('server.add')->with('ssh_publickey', $ssh_publickey);
    }

    public function postAddServer()
    {
        validator(request()->all(), [
            'ssh_username' => ['required'],
            'ssh_address' => ['required', new Ipv4OrDomainARecordExists],
            'ssh_port' => ['required', new IANAPortNumber],
            'storage_path' => ['nullable', new ValidPathFormat],
        ])->validated();

        $sql_params = [];
        $sql = 'select * from tmp '.
            'where user_id = ? '. # auth()->user()->id
            'and k = ?'; # 'server'

        $sql_params[] = auth()->user()->id;
        $sql_params[] = 'server';

        $db = app('db')->select($sql, $sql_params);

        if (count($db) === 0) {
            return redirect()->route('add-server');
        }

        $privatekey = EC::load(json_decode($db[0]->v, true)['ssh_privatekey']);
        $ssh_privatekey = $privatekey->toString('OpenSSH', ['comment' => '']);;
        $ssh_publickey = $privatekey->getPublicKey()->toString('OpenSSH', ['comment' => '']);

        if (app('env') === 'production') {
            validator(
                [
                    'ssh_publickey' => $ssh_publickey
                ],
                [
                    'ssh_publickey' =>
                    new SSHConnectionWorks(
                        ssh_address: request('ssh_address'),
                        ssh_port: request('ssh_port'),
                        ssh_privatekey: $ssh_privatekey,
                        ssh_username: request('ssh_username'),
                    )
                ]
            )->validated();
        }

        DB::transaction(function () use ($ssh_privatekey) {
            $machine = new Machine;
            $machine->hostname = request('ssh_address');
            $machine->ip_address = fake()->ipv4();
            $machine->user_id = auth()->user()->id;

            if (app('env') === 'production') {
                if (filter_var(request('ssh_address'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $machine->ip_address = request('ssh_address');
                } else {
                    $dns_get_record = dns_get_record(request('ssh_address'), DNS_A);

                    $machine->ip_address = $dns_get_record[0]['ip'];
                }
            }

            $machine->ssh_port = request('ssh_port');
            $machine->ssh_privatekey = $ssh_privatekey;
            $machine->ssh_username = request('ssh_username');
            $machine->storage_path = request('storage_path') ?? '/opt/';
            $machine->save();

            User::whereId(auth()->user()->id)->increment('machine_count', 1);

            $traffic_router = new TrafficRouter;
            $traffic_router->machine_id = $machine->id;
            $traffic_router->save();

            Bus::chain([
                new PrepareMachine($machine->id),
                new PrepareTrafficRouter($traffic_router->id)
            ])->dispatch();

            DB::select(
                'delete from tmp '.
                'where user_id = ? '. # auth()->user()->id
                'and k = ?', # 'server'
                [
                    auth()->user()->id,
                    'server'
                ]
            );
        });

        return redirect()->route('server');
    }

    public function editServer()
    {
    }

    public function postEditServer()
    {
    }

    public function deleteServer()
    {
        $user = auth()->user();

        $now = now();
        $sql_params = [];
        $sql = 'with '.
            'update_user as ('.
                'update users set '.
                'machine_count = machine_count - 1, '.
                'updated_at = ? '. # $now
                'where id = ? '. # $user->id;
                'returning id'.
            '), '.
            'select_machine as ('.
                'select * from machines '.
                'where id = ? '. # request('machine_id')
                'and user_id = ? '. # $user->id
                'limit 1 '.
                'for update'.
            '), '.
            'select_instance as ('.
                'select * from instances '.
                'where machine_id = ? '. # request('machine_id')
                'limit 1'.
            ') '.
            'delete from machines '.
            'where id = (select id from select_machine) '.
            'and not exists (select * from select_instance)';

        $sql_params[] = $now;
        $sql_params[] = $user->id;

        $sql_params[] = request('machine_id');
        $sql_params[] = $user->id;
        $sql_params[] = request('machine_id');

        $db = app('db')->select($sql, $sql_params);

        return redirect()->route('server');
    }
}
