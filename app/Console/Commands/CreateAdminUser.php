<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('forms:create-admin')]
#[Description('Create the first administrator user')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->warn('An administrator already exists.');

            return self::SUCCESS;
        }

        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password');

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
