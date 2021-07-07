<?php

namespace App\Http\Controllers\V1\Auth;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\OtpRequest;
use App\Models\SmsQueue;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    private static function sendActivationCode($phone)
    {
        $activationCode = rand(1111, 9999);

        OtpRequest::insert([
            'otp' => $activationCode,
            'phone' => $phone,
            'expired_date' => Carbon::now()->addHour(),
            'registered_date' => Carbon::now(),
            'created_date' => Carbon::now(),
        ]);

        SmsQueue::create([
            'phone_number' => $phone,
            'subject' => 'Sandi Aktivasi KedaiMart',
            'detail' => 'Sandi Aktivasi KedaiMart Anda: '. $activationCode,
            'created' => Carbon::now()
        ]);
    }

    public function register(Request $request)
    {
        $this->validate($request, [
            'nama_lengkap' => 'required|string',
            'no_phone' => 'required',
            'password' => 'required',
        ], [], [
            'nama_lengkap' => 'Nama Lengkap',
            'no_phone' => 'Nomor Telepon',
            'password' => 'Kata Sandi'
        ]);

        $referredMember = null;
        $referredPhone = null;
        if ($request->has('referral_code') && ($request->referral_code != '' || $request->referral_code != null)) {
            $referredPhone = Format::phoneNumber($request->referral_code);
            $referredMember = User::where('phone', $referredPhone)
                ->where('status', 1)
                ->where('activated', 1)
                ->first();

            if (!$referredMember)
                abort(200, 'Nomor referral tidak ditemukan. Silahkan masukkan nomor referral yang benar.');
        }

        $validated['phone'] = Format::phoneNumber($request->no_phone);

        $user = User::where('phone', $validated['phone'])->first();

        $active = true;
        $message = "Terima kasih telah bergabung dengan KedaiMart";
        $message .= $referredPhone
            ? " dengan nomor referral " . Format::castPhoneNumber($referredPhone) . " ($referredMember->fullname)."
            : ".";
        $message .= " Kami telah mengirim sandi aktivasi ke nomor " . $request->no_phone . ". Pengiriman sandi aktivasi paling cepat berlangsung dalam 2 menit.";

        if ($user && $user->activated == 0) {
            $checkOTPs = OtpRequest::where('phone', $validated['phone'])
                ->whereDate('expired_date', date('Y-m-d'))
                ->orderBy('expired_date', 'desc')
                ->count();

            $active = false;

            if ($checkOTPs < 5) {
                self::sendActivationCode($validated['phone']);
                $message = 'Nomor kamu sudah terdaftar sebelumnya, silahkan menunggu sandi aktivasi.';
            } else {
                $message = 'Kamu sudah melebihi batas maksimal pengiriman sandi aktivasi. Silahkan hubungi customer service kami.';
            }
        } elseif ($user && $user->activated == 1) {
            abort(200, 'Nomor kamu sudah terdaftar sebelumnya, silahkan login.');
        }

        if (!$user) {
            $validated['username'] = strtolower(str_replace(' ', '', $request->nama_lengkap) . rand(11, 99));
            $validated['fullname'] = ucwords(strtolower($request->nama_lengkap));
            $validated['password'] = md5($request->password);
            $validated['short_url'] = "http://10.20.1.202/refmc/" . $validated['username'];
            $validated['referral_code'] = $referredPhone;
            $validated['member_parent_id'] = $referredMember ? $referredMember->id : null;
            $validated['status']
                = $validated['activated']
                = $validated['lost_password']
                = 0;

            $user = User::create($validated);

            self::sendActivationCode($validated['phone']);
        }

        return Format::response([
            'active' => $active,
            'message' => $message
        ]);
    }

    public function activateAccount(Request $request)
    {
        $this->validate($request, [
            'kode_aktivasi' => 'required|numeric'
        ]);

        $otp = OtpRequest::where('otp', $request->kode_aktivasi)
            ->where('expired_date', '>=', Carbon::now())
            ->orderBy('expired_date', 'desc')
            ->first();

        if (!$otp) {
            return responseSuccess(['message' => 'Maaf, Sandi aktivasi yang kamu masukkan salah atau telah kedaluwarsa.']);
        }

        $user = User::where('phone', $otp->phone)->first();

        if (!$user) {
            return responseSuccess(['message' => 'Akun dengan no telepon ' . $otp->phone . ' tidak ditemukan.']);
        }

        $user->activated = $user->status = 1;
        $user->save();

        $phone = Format::castPhoneNumber($user->phone);

        return Format::response([
            'message' => "Akun kamu $user->fullname ($phone) telah diaktivasi, silahkan login."
        ]);
    }
}
