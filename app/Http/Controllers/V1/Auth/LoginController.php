<?php

namespace App\Http\Controllers\V1\Auth;

use App\Helpers\Calculate;
use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Member\Distance;
use App\User;
use App\Wilayah\Pool;
use App\Wilayah\Wilayah;
use App\Wilayah\WilayahAddressDetail;
use App\Wilayah\WilayahDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public static function setUserToken($user)
    {
        // if ($user->token == null) {
            $user->token = Str::random(32);
        // }
        $user->last_login = Carbon::now();
        $user->token_expired = Carbon::now()->addMonths(6);
        $user->log = $_SERVER['HTTP_USER_AGENT'];
        $user->save();
    }

    public function login(Request $request)
    {
        $validated = $this->validate($request, [
            'handphone' => 'required',
            'password' => 'required',
        ], [], [
            'handphone' => 'No Telepon',
            'password' => 'Kata Sandi'
        ]);

        // FIND MEMBER BASED ON THEIR PHONE, USERNAME, OR EMAIL ADDRESS
        $validated['handphone'] = Format::phoneNumber($validated['handphone']);
        $user = User::select(
                'id', 'fullname', 'phone', 'email', 'status', 'activated', 'tanggal_lahir AS tgl_lahir',
                'created_at as created', 'lost_password', 'last_login', 'short_url',
                'password', 'token', 'token_expired', 'referral_code'
            )
            ->withTabungan()
            ->where('phone', $validated['handphone'])
            ->orWhere('username', $validated['handphone'])
            ->orWhere('email', $validated['handphone'])
            ->first();

        $active = true;
        $message = 'Success';

        // CHECK MEMBER STATUS
        if (!$user) {
            abort(200, 'Akun belum terdaftar.');
        } elseif ($user->password != md5($validated['password'])) {
            abort(200, 'No telepon atau kata sandi salah.');
        }

        if ($user->activated == 0) {
            $active = false;
            $message = 'Akun kamu belum diaktivasi.';
            abort(200, $message);
        } elseif ($user->status == 0) {
            $active = false;
            $message = 'Akun kamu telah dinonaktifkan.';
            abort(200, $message);
        }

        unset($user->password); // remove password attribute

        // MEMBER AUTHENTICATION TOKEN
        self::setUserToken($user);

        // REFERRAL MEMBER
        $user->phone = Format::castPhoneNumber($user->phone);
        $refMember = User::where('phone', $user->referral_code)->first();
        if ($refMember) {
            $user->referral_code = Format::castPhoneNumber($user->referral_code);
            $refPhone = Format::castPhoneNumber($refMember->phone);
            $refNames = explode(' ', $refMember->fullname);
            if (count($refNames) > 1) {
                $refName = $refNames[0] . ' ' . $refNames[1];
            } else {
                $refName = $refNames[0];
            }
            $user->phone .= " (Ref: $refPhone - $refName)";
        }

        // TABUNGAN
        if (!$user->tabungan) {
            $user->tabungan = 0;
        }

        // MEMBER ADDRESS
        $address = $user->addresses()->primary()->first();

        /* DC, POOL, TS, LAPAK */
        $wilayah = Wilayah::select('id', 'url_title', 'title', 'radius', 'minimal_belanja', 'delivery_time', 'publish as wilayah_publish', 'publish', 'lat', 'lng');
        $pool = Pool::select('id', 'wilayah_id', 'lat', 'lng');
        $ts = WilayahDetail::select('id', 'url_title', 'title', 'phone');
        $lapak = WilayahAddressDetail::select(
            'id', 'title', 'address AS alamat', 'g_route', 'lat', 'lng',
            DB::raw("CONCAT(adm_area_level_4, ', ', adm_area_level_3, ', ', adm_area_level_2) AS adm_area"),
            'wilayah_detail_id'
        );

        $defaultDcId = 12;

        if ($address) {
            $user->lat = $address->lat;
            $user->lng = $address->lng;

            $distance = Distance::where('address_id', $address->id)
                ->where('dc_id', $defaultDcId) // default cipondoh
                ->first();

        } else {
            $user->lat = $user->lng = null;
            $distance = null;
        }

        if ($distance && $distance->dc_id != 0) {
            $wilayah = $wilayah->find($distance->dc_id);
        } else {
            $wilayah = $wilayah->find($defaultDcId);
        }

        if ($distance && $distance->pool_id != 0) {
            $pool = $pool->find($distance->pool_id);
        } else {
            $pool = $pool->where('wilayah_id', $defaultDcId)
                ->active()
                ->first();
        }

        if ($distance && $distance->lapak_id != 0) {
            $lapak = $lapak->find($distance->lapak_id);
        } else {
            $lapak = $lapak->where('wilayah_pool_id', $pool->id)
                ->active()
                ->first();
        }

        if ($distance && $distance->ts_id != 0) {
            $ts = $ts->find($distance->ts_id);
        } else {
            $ts = $ts->where('id', $lapak->wilayah_detail_id)
                ->first();
        }

        if ($distance && $distance->jarak) {
            $ongkir = Calculate::ongkir($distance->jarak, $wilayah->id);
        } else {
            $ongkir = 0;
        }

        $apkVersion = DB::table('tm_params')->where('param_code', 'EC_APP_VERSION_CODE')->first();

        return Format::response([
            'message' => $message,
            'active' => $active,
            'data' => [
                'apk_version' => $apkVersion ? $apkVersion->param_value : '',
                'member' => $user,
                'dc' => $wilayah ?? null,
                'ts' => $ts ?? null,
                'lapak_ts' => $lapak ?? null,
                'pool' => $pool ?? null,
                'jarak_tempuh' => $distance ? $distance->jarak . " km" : "1 km",
                'ongkir' => $ongkir,
            ]
        ]);
    }

    public function logout()
    {
        Auth::user()->update([
            'token' => null,
            'token_expired' => Carbon::now(),
        ]);

        return Format::response([
            'message' => 'Berhasil keluar.'
        ]);
    }
}
