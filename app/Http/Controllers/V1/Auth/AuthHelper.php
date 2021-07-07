<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthHelper extends Controller
{
    private static $GOOGLE_URLSHORTURL = 'https://www.googleapis.com/urlshortener/v1/url';
    private static $GOOGLE_API_KEY = 'AIzaSyDurHh-LwgF0XYXa0KAT4382r8Tg2jORE8';

    public static function setToken($user)
    {
        $currentTime = Carbon::now();
        $user->token = Str::random(32);
        $user->last_login = Carbon::now();
        $user->token_expired = $currentTime->addYear();
        $user->log = $_SERVER['HTTP_USER_AGENT'];
        $user->save();
    }

    public static function getWilayah($lat, $lng, $dcId = 12)
    {
        $sql = "
            SELECT
                Wilayah.id,
                Wilayah.url_title,
                Wilayah.title as wilayah_title,
                Wilayah.radius,
                Wilayah.minimal_belanja,
                Wilayah.delivery_time,
                Wilayah.index_ongkir_ts,
                Wilayah.publish,
                Wilayah.open_cust,
                Wilayah.closing_cust,
                WilayahDetail.id as wilayah_detail_id,
                WilayahDetail.url_title,
                WilayahDetail.title as wilayah_detail_title,
                WilayahDetail.delivery_time,
                WilayahDetail.view_type,
                WilayahDetail.phone,
                WilayahAddressDetail.id as wilayah_address_detail_id,
                WilayahAddressDetail.wilayah_pool_id,
                WilayahAddressDetail.wilayah_id,
                WilayahAddressDetail.title as wilayah_address_detail_title,
                WilayahAddressDetail.address,
                WilayahAddressDetail.lat,
                WilayahAddressDetail.lng,
                WilayahAddressDetail.g_route,
                CONCAT(WilayahAddressDetail.adm_area_level_4,' ',WilayahAddressDetail.adm_area_level_3,', ',WilayahAddressDetail.adm_area_level_1) AS adm_area,
                round( 6371 * acos( cos( radians($lat) ) * cos( radians(WilayahAddressDetail.lat ) ) * cos( radians(WilayahAddressDetail.lng ) - radians($lng) ) + sin( radians($lat) ) * sin( radians(WilayahAddressDetail.lat ) ) ),1 )  AS distance
            FROM wilayah_address_details AS WilayahAddressDetail
            INNER JOIN wilayahs AS Wilayah ON Wilayah.id=WilayahAddressDetail.wilayah_id AND Wilayah.publish=1 AND Wilayah.status=1
            INNER JOIN wilayah_details AS WilayahDetail ON WilayahDetail.id = WilayahAddressDetail.wilayah_detail_id  AND WilayahDetail.status=1 AND WilayahDetail.publish=1
            WHERE WilayahAddressDetail.status=1 AND WilayahAddressDetail.publish=1 AND WilayahAddressDetail.is_receiveorder =1 AND WilayahDetail.app_code=2 and Wilayah.id=$dcId
            ORDER BY distance LIMIT 1
        ";

        // GROUP BY distance
        $wilayah = DB::select($sql);
        return count($wilayah) ? $wilayah[0]: null;
    }

    public static function getOngkir($wilayah_id, $distance)
    {
        $biaya = DB::table('wilayah_ongkirs')
            ->select('biaya')
            ->where('wilayah_id', $wilayah_id)
            ->where('jarak_mulai', '<=', $distance)
            ->orderBy('jarak_mulai', 'desc')
            ->first();

        if (!$biaya) {
            $biaya = DB::table('wilayah_ongkirs')
                ->select('biaya')
                ->where('wilayah_id', $wilayah_id)
                ->whereNull('jarak_mulai')
                ->orderBy('biaya', 'desc')
                ->first();
        }

        return $biaya ? $biaya->biaya : 0;
    }

    public static function shortenUrl($long_url)
    {
        $ch = curl_init(self::$GOOGLE_URLSHORTURL . '?key=' . self::$GOOGLE_API_KEY);
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_POST => 1,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS => '{"longUrl": "' . $long_url . '"}'));
        $json_response = json_decode(curl_exec($ch), true);
        return isset($json_response['id']) ? $json_response['id'] : $long_url;
    }
}
