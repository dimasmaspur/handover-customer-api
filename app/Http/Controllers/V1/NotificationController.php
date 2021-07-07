<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Order\Order;
use App\Order\OrderHistory;
use App\Order\OrderStatus;
use App\Order\Voucher;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function setPlayerId(Request $request)
    {
        $playerId = $request->has('player_id') && ($request->player_id != null || $request->player_id != '')
            ? $request->player_id
            : null;
            
        $status = true;

        if ($playerId) {

            // Set new Player ID
            $status = User::where('id', Auth::id())->update(['player_id' => $playerId]);
            
            // Removed Old Existing Player ID from Old User
            User::where('player_id', $playerId)
                ->where('id', '!=', Auth::id())
                ->update(['player_id' => null]);
        }

        return Format::response([
            'message' => $status ? 'PlayerID has been set' : 'Failed to set PlayerID'
        ]);
    }

    public function inbox()
    {
        $rawInboxes = [];
        $returnData = [];

        $thisWeek = Carbon::now()->subWeek();

        /* // Promos, Banners, Vouchers
        $vouchers = Voucher::select('title', 'created_at')
            ->active()
            // ->where('created_at', '>=', $thisWeek->format('Y-m-d'))
            ->orderBy('created_at', 'desc')
            ->get();
        
        foreach ($vouchers as $voucher) {
            $rawInboxes[] = [
                'message' => $voucher->title,
                'timestamp' => $voucher->created_at,
            ];
        } */

        if (Auth::check()) {

            $orderStatuses = OrderStatus::select('code', 'title', 'detail', 'notif_info')
                ->orderBy('code')
                ->get();

            // this week's transactions...
            $thisWeeksOrders = Order::select('id')
                ->where('member_id', Auth::id())
                ->where('created', '>=', $thisWeek->format('Y-m-d'))
                ->count();

            if ($thisWeeksOrders < 7) {
                $orders = Order::select('id')
                    ->where('member_id', Auth::id())
                    ->orderBy('id', 'desc')
                    ->limit(7)
                    ->get();
            } else {
                $orders = Order::select('id')
                    ->where('member_id', Auth::id())
                    ->where('created', '>=', $thisWeek->format('Y-m-d'))
                    ->orderBy('id', 'desc')
                    ->get();
            }

            $orderHistories = OrderHistory::select(
                    'tr_order_histories.order_id',
                    'tr_orders.code',
                    'tr_order_histories.order_status_code',
                    'tr_order_histories.created',
                )->leftJoin('tr_orders', 'tr_orders.id', 'tr_order_histories.order_id')
                ->where('tr_orders.member_id', Auth::id())
                ->whereIn('tr_order_histories.order_id', $orders->pluck('id'))
                // ->where('tr_order_histories.created', '>=', $thisWeek->format('Y-m-d'))
                ->orderBy('tr_order_histories.created', 'desc')
                ->get();

            foreach ($orderHistories as $history) {

                $myOSCode = $orderStatuses->where('code', $history->order_status_code)->first();
                
                /* $message = $myOSCode->notif_info
                    ? str_replace('[order_code]', $history->code, $myOSCode->notif_info)
                    : $myOSCode->detail . " ($history->code)"; */

                if ($myOSCode->notif_info) {
                    $message = str_replace('[order_code]', $history->code, $myOSCode->notif_info);
                    
                    $rawInboxes[] = [
                        'message' => $message,
                        'timestamp' => $history->created,
                    ];
                }
            }
        }
        // return count($rawInboxes);

        usort($rawInboxes, function ($item1, $item2) {
            return $item2['timestamp'] <=> $item1['timestamp'];
        });

        // var_dump($rawInboxes);die();

        foreach ($rawInboxes as $inbox) {
            $date = new Carbon($inbox['timestamp']);
            $returnData[$date->format('Y-m-d')]['tanggal'] = $date->locale('id_ID')->translatedFormat('l, j F Y, H:i');
            $returnData[$date->format('Y-m-d')]['content'][] = $inbox['message'];
        }
        
        $returnData = array_values($returnData);

        return Format::response([
            'data' => $returnData
        ]);
    }

    public static function push($pushData = [], $playerIds = [])
    {
        $error = true;
        $message = 'Failed to send push notification';

        if (env('BASE_URL_COMMUNICATION') && env('ONESIGNAL_APPID')) {
            
            $pushData['app_id'] = env('ONESIGNAL_APPID');
            $pushData['large_icon'] = url('ec-api/img/b2c_notif_icon.png');

            if ($playerIds) $pushData['include_player_ids'] = $playerIds;

            try {

                $sendPush = Http::post(env('BASE_URL_COMMUNICATION') . 'notification/push', $pushData);

                if ($sendPush->successful()) {

                    $responseBody = $sendPush->json();

                    $message = 'Bad Request';

                    if (isset($responseBody['status']) && isset($responseBody['message'])) {

                        $error = $responseBody['message'] == 'success';
                        
                        $message = $error == false
                            ? 'Push notification sent'
                            : $responseBody['message'];
                    }

                } else {
                    $message = 'Connection failed';
                }

            } catch (Exception $e) {
                $message = $e->getMessage();
            }

        } else {
            $message = 'ENV variables are not set';
        }

        Log::info($message);

        return ['error' => $error, 'message' => $message];
    }
}
