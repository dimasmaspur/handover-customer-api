<?php

use App\Order\Store;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'Kedai Sayur',
            'Kedai Bunga'
        ];

        foreach ($data as $item) {
            Store::withTrashed()->updateOrCreate([
                'name' => $item
            ], [
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ]);
        }
    }
}
