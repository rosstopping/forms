<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		if (User::query()->exists()) {
			return;
		}

		User::query()->create([
			'name' => 'Administrator',
			'email' => 'admin@example.com',
			'password' => bcrypt('password123'),
		]);
	}
}
