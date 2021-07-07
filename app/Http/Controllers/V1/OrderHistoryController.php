<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Helpers\Format;
use App\Order\Order;
use App\Order\OrderHistory;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderHistoryController extends Controller
{
    public function trackingHistory(Request $request)
    {
        $this->validate($request, [
            'order_code' => 'required|exists:App\Order\Order,code'
        ]);

        $order = Order::where('code', $request->order_code)
            ->where('member_id', Auth::id())
            ->first();

        if (!$order) {
            abort(200, 'Order tidak ditemukan');
        }

        $histories = OrderHistory::select([
                'tr_order_histories.id', 
                'tr_order_histories.order_status_code',
                'tr_order_histories.created',
                'order_statuses.order_status_title'
            ])
            ->leftJoin(env('DB_DATABASE') . '.order_statuses', 'order_statuses.code', 'tr_order_histories.order_status_code')
            ->where('tr_order_histories.order_id', $order->id)
            ->get();

        $myItem = [];

        foreach ($histories as $item) {
            $myItem[] = [
                'status' => $item->order_status_code,
                'message' => $item->order_status_title,
                'waktu' => $item->created->format('Y-m-d H:i:s')
            ];
        }

        $message = "Oops!";

        try {
            $client = new Client(['base_uri' => env('BASE_URL_SHIPMENT')]);
            $response = $client->post('shipment/tracking', ['json' => [
                'order_code' => [$request->order_code]
            ]]);
            $response = json_decode($response->getBody(), true);
        } catch (\Exception $error) {
            $message .= ' ' . $error->getMessage();
            $response = null;
        }

        if ($response && isset($response['data']['item'])) {

            $message = 'Success';

            foreach ($response['data']['item'] as $item) {

                $myItem[] = [
                    'status' => $item['status'],
                    'message' => $item['message'],
                    'waktu' => $item['waktu'],
                ];
            }

        } elseif ($response && isset($response['message'])) {

             $message .= ' ' . ucfirst(strtolower($response['message']));
             $message .= '.';
        }

        $waybillNo = $response['data']['header']['waybill_no'] ?? '';

        uasort($myItem, function ($a, $b) {
            $first = new Carbon($a['waktu']);
            $second = new Carbon($b['waktu']);

            return $first->gt($second) ? -1 : 1;
        });

        $newItems = [];

        foreach ($myItem as $item) {
            $newItems[] = $item;
        }
        
        if (isset($response['data']['header']['tgl_kirim'])) {
            $deliveryDate = $response['data']['header']['tgl_kirim'];
        } else {
            $deliveryDate = new Carbon($order->expected_delivery_date);
            $deliveryDate = $deliveryDate->locale('id_ID')->translatedFormat('j F Y');
        }

        return Format::response([
            'message' => $message,
            'base_url' => env('BASE_URL_ICON'),
            'data' => [
                'header' => [
                    'order_code' => $order->code,
                    'waybill_no' => $waybillNo,
                    'kurir' => $response['data']['header']['kurir'] ?? '',
                    'tgl_kirim' => $deliveryDate,
                ],
                'item' => $newItems
            ]
        ]);
    }

    public function storeOrderHistory(Request $request)
    {
        $this->validate($request, [
            'order_code' => 'required|exists:App\Order\Order,code',
            'order_status_code' => 'required|exists:App\Order\OrderStatus,code',
        ]);

        $order = Order::where('code', $request->order_code)->first();

        $history = SubmitOrderController::storeOrderHistory($order, $request->order_status_code, 'Order Status changed by System');

        $updated = false;

        if ($history) {
            $order->order_status_code = $request->order_status_code;
            $updated = $order->save();
        }

        return Format::response([
            'message' => $updated ? 'Success' : 'Gagal mengubah status order'
        ], !$updated);
    }
}
