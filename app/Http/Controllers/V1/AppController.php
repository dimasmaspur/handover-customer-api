<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function checkVersionCode(Request $request)
    {
        $this->validate($request, [
            'apps_version_code' => 'required|numeric',
            'device_info' => 'required',
        ]);

        $appVersion = DB::table('tm_params')->where('param_code', 'EC_APP_VERSION_CODE')->first();
        $deviceInfo = strtolower($request->device_info);
        $devicePlatform = Str::contains($deviceInfo, 'android') ? 'ANDROID': 'IOS';

        $deviceVersionCode = "EC_" . $devicePlatform . "_VERSION_CODE";
        $deviceUrl = "EC_" . $devicePlatform . "_URL";

        $latestVersion = DB::table('tm_params')
            ->where('param_code', $deviceVersionCode)
            ->first();

        $appUrl = DB::table('tm_params')
            ->where('param_code', $deviceUrl)
            ->first();

        if (!$latestVersion) {
            abort(200, 'Gagal melakukan pengecekan versi aplikasi.');
        }

        $forceUpdate = $latestVersion->param_value > $request->apps_version_code ? 1 : 0;

        return Format::response([
            'status' => 'Success',
            'message' => $forceUpdate
                ? $appUrl->message
                : 'Success',
            'apk_version' => $appVersion ? $appVersion->param_value : '',
            'apps_version_code' => $request->apps_version_code,
            'ec_android_version_code' => $latestVersion->param_value,
            'url' => $appUrl->param_value,
            'force_update' => $forceUpdate,
        ]);
    }

    public function checkMaintenance(Request $request)
    {
        if ($request->has('token')) {

            $group = DB::table('tm_group')
                ->select('tm_group.*')
                ->leftJoin('tr_member_group', 'tr_member_group.group_id', '=', 'tm_group.id')
                ->leftJoin('tm_members', 'tm_members.id', 'tr_member_group.member_id')
                ->where('tm_members.token', $request->token)
                ->first();

            if (!$group) {
                $group = DB::table('tm_group')->find(1);
            }
            
        } else {
            $group = DB::table('tm_group')->find(1);
        }

        $maintenanceStatus = (int) $group->is_maintenance;

        return response()->json([
            'code' => 200,
            'is_maintenance' => $maintenanceStatus
        ]);
    }

    public function phoneCs()
    {
        $error = false;

        $phone = DB::table('tm_params')
            ->where('param_code', 'CS_PHONE')
            ->first();

        if (!$phone) $error = true;

        return Format::response([
            'message' => $error ? 'Phone not found' : 'Success',
            'phone' => $phone ? Format::phoneNumber($phone->param_value) : ''
        ], $error);
    }
}
