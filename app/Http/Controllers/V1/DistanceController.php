<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Member\Distance;
use App\Wilayah\Pool;
use App\Wilayah\Wilayah;
use App\Wilayah\WilayahAddressDetail;
use Illuminate\Support\Facades\Http;

class DistanceController extends Controller
{
    public static function storeDistance($address)
    {
        /* $wilayah = Wilayah::select('id')
            ->where('status', 1)
            ->where('publish', 1)
            ->get(); */

        // $pools = [];
        // $origins = null;

        /* foreach ($wilayah as $wilayah) {

            $pool = Pool::select('id', 'wilayah_id', 'lat', 'lng')
                ->where('wilayah_id', $wilayah->id)
                // ->whereDistance($address->lat, $address->lng)
                // ->first();
                ->find(181); // ONLY for KSI

                dd($pool);

            $lapak = WilayahAddressDetail::select('id', 'wilayah_detail_id', 'lat', 'lng')
                ->where('wilayah_id', $wilayah->id)
                ->where('is_primary', 1)
                ->where('status', 1)
                ->where('publish', 1)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereDistance($address->lat, $address->lng);

            if ($pool) {
                $lapak = $lapak->where('wilayah_pool_id', $pool->id);
            }

            $lapak = $lapak->first();

            if ($pool) {
                $pool->lapak_id = $lapak ? $lapak->id : null;
                $pool->ts_id = $lapak ? $lapak->wilayah_detail_id : null;
                $pools[] = $pool;
                $origins[] = $pool->lat . ',' . $pool->lng;
            }
        } */

        $pool = Pool::select('id', 'wilayah_id', 'lat', 'lng')->find(181);
        // $lapak = null;

        /* if ($pool) {
            // $origins = $pool->lat . ',' . $pool->lng;

            // $lapak = WilayahAddressDetail::select('id', 'wilayah_detail_id', 'lat', 'lng')
            //     ->where('wilayah_pool_id', $pool->id)
            //     ->first();

            // $pool->lapak_id = $lapak ? $lapak->id : null;
            // $pool->ts_id = $lapak ? $lapak->wilayah_detail_id : null;

            // $pools[] = $pool;
        } */

        // if ($origins) {
        if ($pool) {

            $response = Http::asForm()->get(env('BASE_URL_GMAPS') . 'distancematrix/json', [
                // 'origins' => implode('|', $origins),
                // 'origins' => $origins,
                'origins' => $pool->lat . ',' . $pool->lng,
                'destinations' => $address->lat . ',' . $address->lng,
                'key' => env('GMAPS_KEY'),
            ]);

            $response = $response->json();

            if ($response != null && $response['status'] == 'OK') {

                foreach ($response['rows'][0]['elements'] as $key => $gmaps) {

                    if ($gmaps['status'] == 'OK') {

                        Distance::updateOrCreate([
                            'member_id' => $address->member_id,
                            'address_id' => $address->id,
                            // 'dc_id' => $pools[$key]->wilayah_id,
                            'dc_id' => $pool->wilayah_id,
                        ], [
                            // 'pool_id' => $pools[$key]->id,
                            'pool_id' => $pool->id,
                            // 'ts_id' => $pools[$key]->ts_id,
                            // 'ts_id' => $pool->ts_id,
                            'ts_id' => 5827,
                            // 'lapak_id' => $pools[$key]->lapak_id,
                            // 'lapak_id' => $pool->lapak_id,
                            'lapak_id' => 6098,
                            'created_by' => $address->member_id,
                            'updated_by' => $address->member_id,
                            'jarak' => $gmaps['distance']['value'] / 1000
                        ]);
                    }
                }
            }
        }
    }
}
