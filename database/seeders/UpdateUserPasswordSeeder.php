<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserPasswordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'deewahyu@upi.edu')->first();

        if ($user) {
            $user->password = Hash::make('Ddw9889##');
            $user->save();

            $this->command->info('Password for deewahyu@upi.edu has been updated successfully.');
        } else {
            $this->command->warn('User with email deewahyu@upi.edu not found.');
        }

        $user = User::where('email', 'agusheri@upi.edu')->first();

        if ($user) {
            $user->password = Hash::make('123456');
            $user->save();

            $this->command->info('Password for deewahyu@upi.edu has been updated successfully.');
        } else {
            $this->command->warn('User with email deewahyu@upi.edu not found.');
        }
    }
}
