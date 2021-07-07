<?php

namespace App\Http\Controllers\V1\Auth;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\SmsQueue;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ForgetPasswordController extends Controller
{
    public function resetPassword(Request $request)
    {
        $this->validate($request, [
            'handphone' => 'required'
        ]);

        $phone = Format::phoneNumber($request->handphone);

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            abort(200, 'Akun ' . $phone . ' belum terdaftar.');
        } elseif ($user->activated == 0) {
            abort(200, 'Akun kamu belum diaktivasi.');
        } elseif ($user->status == 0) {
            abort(200, 'Akun kamu telah dinonaktifkan.');
        }

        $newPassword = strtoupper(Str::random(6));

        $user->password = md5($newPassword);
        $user->save();

        SmsQueue::create([
            'subject' => 'Lost Password',
            'phone_number' => $user->phone,
            'detail' => 'Kata sandi baru KedaiMart kamu: ' . $newPassword
        ]);

        return responseSuccess([
            'message' => 'Kata sandi kamu berhasil kami reset. Kami telah mengirimkan kata sandi baru ke nomor kamu.'
        ]);
    }
}
