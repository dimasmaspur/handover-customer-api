<?php

namespace App\Http\Controllers\V3;

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

class FinishedOrderController extends Controller
{
    
    public function terimaPesanan(Request $request)
    {
        $this->validate($request, [
            'order_code' => 'required|exists:App\Order\Order,code',
            'order_status_code' => 'required|exists:App\Order\OrderStatus,code',
        ]);

        $order = Order::where('code', $request->order_code)->first();

        $history = SubmitOrderController::storeOrderHistory($order, $request->order_status_code, 'Order Status changed by System');

        $updated = false;

        if ($history) {
            if($order->order_status_code === 'OS18' || $order->order_status_code === 'OS05'){
                $order->order_status_code = $request->order_status_code;
                $updated = $order->save();
            }
        }

        return Format::response([
            'message' => $updated ? 'Success' : 'Gagal mengubah status order'
        ], !$updated);
    }
}
