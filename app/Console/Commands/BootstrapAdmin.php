<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\SecurityAudit;
use App\Services\UserInvitations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BootstrapAdmin extends Command
{
    protected $signature = 'newoo:bootstrap-admin {email} {--name=System Administrator}';
    protected $description = 'Create the first administrator with a private one-time password setup link';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');
            return self::FAILURE;
        }
        $url = DB::transaction(function () use ($email) {
            $role = Role::where('name', 'system_admin')->lockForUpdate()->sole();
            if (User::where('role_id', $role->id)->where('is_active', true)->exists()) {
                throw new \RuntimeException('An active administrator already exists. Use internal user management.');
            }
            $user = User::where('email', $email)->first();
            if ($user && ($user->account_type !== 'internal' || $user->role_id !== $role->id)) {
                throw new \RuntimeException('This email already belongs to another account. No access was changed.');
            }
            if (! $user) {
                $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => Str::random(64)]);
                $user->role_id = $role->id;
                $user->account_type = 'internal';
                $user->is_active = false;
                $user->save();
            }
            SecurityAudit::record('system.admin_bootstrapped', 'user', $user->id);
            return UserInvitations::create($user);
        });
        Storage::disk('local')->put('admin-setup-link.txt', "First administrator: {$email}\nExpires in 24 hours; single use.\n{$url}\n");
        $this->info('Administrator profile prepared. The private setup link is in storage/app/private/admin-setup-link.txt.');
        return self::SUCCESS;
    }
}
