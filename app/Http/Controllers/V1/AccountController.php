<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Helpers\Format;
use App\Member\Point;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'password_lama' => 'required|string',
            'password_baru' => 'required|string',
            'konfirmasi_password' => 'required|string|same:password_baru',
        ]);

        $user = Auth::user();

        if (md5($request->password_lama) != $user->password) {
            abort(200, 'Kata sandi lama salah.');
        }

        $user->password = md5($request->password_baru);
        $user->save();

        return Format::response([
            'message' => 'Kata sandi telah diubah.'
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $this->validate($request, [
            'nama_lengkap' => 'required|string',
            'tgl_lahir' => 'required|date_format:Y-m-d',
            'email' => 'required|email'
        ]);

        $existingEmail = User::where('email', $request->email)
            ->where('id', '!=', Auth::id())
            ->count();

        if ($existingEmail) {
            abort(200, 'Email sudah ada sebelumnya.');
        }

        Auth::user()->update([
            'fullname' => $validated['nama_lengkap'],
            'tanggal_lahir' => $validated['tgl_lahir'],
            'email' => $validated['email'],
        ]);

        return Format::response([
            'message' => 'Data diri berhasil diperbarui.'
        ]);
    }

    public function showProfile()
    {
        $refMember = null;
        if (Auth::user()->referral_code) {
            $refMember = User::where('phone', Auth::user()->referral_code)->first();
        }

        $ksiPoint = Point::where('member_id', Auth::id())->first();

        $phoneCs = DB::table('tm_params')
            ->where('param_code', 'CS_PHONE')
            ->first();

        return Format::response([
            'data' => [
                'id' => Auth::id(),
                'nama' => Auth::user()->fullname,
                'phone' => Format::castPhoneNumber(Auth::user()->phone),
                'email' => Auth::user()->email,
                'tgl_lahir' => Auth::user()->tanggal_lahir,
                'my_referral_code' => Format::castPhoneNumber(Auth::user()->phone),
                'used_referral_code' => Auth::user()->referral_code
                    ? Format::castPhoneNumber(Auth::user()->referral_code)
                    : '',
                'referral_member' => $refMember ? $refMember->fullname : '',
                'ksi_poin' => $ksiPoint ? (int) $ksiPoint->point : 0,
                'phone_cs' => $phoneCs ? Format::phoneNumber($phoneCs->param_value) : '',
            ]
        ]);
    }
}
