<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bills:user {username} {--admin : Make this user an admin} {--password= : Password (asked if empty, use - to read it from stdin)}')]
#[Description('Create a user, or change the password of an existing user')]
class SaveUser extends Command
{
    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = $this->option('password') ?: $this->secret('Password');
        if ($password === '-') {
            $password = trim((string) fgets(STDIN));
        }
        if (! $password || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['username' => $username]);
        $user->password = $password;
        if ($this->option('admin')) {
            $user->is_admin = true;
        }
        $user->save();
        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." user '$username'".($user->is_admin ? ' (admin)' : '').'.');

        return self::SUCCESS;
    }
}
