<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['model' => 'order', 'name' => 'pending'],
            ['model' => 'order', 'name' => 'processing'],
            ['model' => 'order', 'name' => 'completed'],
            ['model' => 'order', 'name' => 'rejected'],
            ['model' => 'order', 'name' => 'expired']
        ];

        foreach ($data as $item) {
            $item['author_id'] = 1;
            $item['author_type'] = (new \App\Models\User())->getMorphClass();
            Status::create($item);
        }
    }
}
