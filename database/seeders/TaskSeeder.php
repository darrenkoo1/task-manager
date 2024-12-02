<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run()
    {
        Task::create(['name' => 'Task 1']);
        Task::create(['name' => 'Task 2', 'parent_id' => 1]);
        Task::create(['name' => 'Task 3']);
    }
}
