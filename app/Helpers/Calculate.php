<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class Calculate
{
    public static function ongkir($distance, $wilayahId)
    {
        $biaya = DB::connection('mysql_cdb')
            ->table('wilayah_ongkirs')
            ->select('biaya')
            ->where('wilayah_id', $wilayahId)
            ->where('jarak_mulai', '<=', $distance)
            ->orderBy('jarak_mulai', 'desc')
            ->first();

        if (!$biaya) {
            $biaya = DB::connection('mysql_cdb')
                ->table('wilayah_ongkirs')
                ->select('biaya')
                ->where('wilayah_id', $wilayahId)
                ->whereNull('jarak_mulai')
                ->orderBy('biaya', 'desc')
                ->first();
        }

        return $biaya ? $biaya->biaya : 0;
    }
}
