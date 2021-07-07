<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Order\Order;
use App\Order\OrderDetail;
use App\Order\OrderStatus;
use App\Order\PaymentType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public static function mapPendingPaymentOrder($order, $ordeStatusCode, $payment, $paymentNotif)
    {
        $sumProduct = OrderDetail::where('order_id', $order->id)->sum('product_qty');

        $deliveryDate = new Carbon($order->expected_delivery_date);
        $deliveryDate->locale('id_ID');

        /* Cashback */
        /* $getCashback = false;
        $contractDateBegin = date('Y-m-d', strtotime('2020-06-18'));
        $contractDateEnd = date('Y-m-d', strtotime('2020-06-30'));

        $oldPaidOrders = Order::select('id')
            ->whereDate('created', '<', $contractDateBegin)
            ->where('member_id', Auth::id())
            ->whereIn('order_status_code', ['OS01', 'OS04', 'OS00'])
            ->count();

        if ($oldPaidOrders <= 0) {

            $firstOrderInContract = Order::select('id')
                ->where('member_id', Auth::id())
                ->whereBetween('created', [$contractDateBegin, $contractDateEnd])
                ->where('order_expired', '>', date('Y-m-d H:i:s'))
                ->whereIn('order_status_code', ['OS04'])
                ->first();

            if ($firstOrderInContract && $firstOrderInContract->id == $order->id) {
                $getCashback = true;
            }
        } */

        // $paymentDesc = '';
        /* if ($getCashback) {
            $paymentDesc = '<p style="width:100%;font-size:12px!important;text-align:center;line-height:1.25;color:#ff8036"><strong>Anda mendapatkan potensi cashback maksimal Rp 50.000 untuk belanja pertama kalinya.</strong><br /></p>';
        } */

        // $paymentDesc .= $payment->description;

        /* if (
            $request->has('device_info') 
            && Str::contains($request->device_info, 'iOS') 
            || $request->has('phone_type') 
            && Str::contains($request->phone_type, 'iPhone') 
        ) {
            $paymentDesc = $payment->description;
            
        } else {

            $paymentDesc = [];
            
            $payment->load('steps:id,payment_type_id,description');

            foreach ($payment->steps as $header) {
                        
                $header->load('details:id,parent_id,image,description');

                $details = [];

                foreach ($header->details as $detail) {
                    $details[] = [
                        'image' => $detail->image,
                        'description' => $detail->description,
                    ];
                }

                $paymentDesc[] = [
                    'header' => $header->description,
                    'details' => $details
                ];
            }
        } */

        // Channel Payment Code
        $channelPaymentCode = '';
        if ($paymentNotif && isset($paymentNotif->channel_payment_code)) {
            $channelPaymentCode = str_replace(' ', '', $paymentNotif->channel_payment_code);
            $channelPaymentCode = chunk_split($channelPaymentCode, 4, ' ');
            $channelPaymentCode = trim($channelPaymentCode);
        }
        
        // New Payment Description
        $paymentDesc = [];
            
        $payment->load('steps:id,payment_type_id,description');

        foreach ($payment->steps as $header) {
                    
            $header->load('details:id,parent_id,image,description');

            $details = [];

            foreach ($header->details as $detail) {
                $details[] = [
                    'image' => $detail->image,
                    'description' => $detail->description,
                ];
            }

            $paymentDesc[] = [
                'header' => $header->description,
                'details' => $details
            ];
        }

        $orderExpired = new Carbon($order->order_expired);

        return [
            'is_paid' => $payment->id == 52 ? 1 : 0,
            'order_id' => $order->id,
            'order_id_midtrans' => $paymentNotif->order_id_midtrans,
            'created' => $order->created->format('Y-m-d H:i:s'),
            'code' => trim(strrev(chunk_split(strrev($order->code),4, ' '))),
            'order_code' => $order->code,
            'order_expired' => $order->order_expired,
            'ts_title' => $ordeStatusCode->order_status_title,
            'sum_product' => $sumProduct,
            'cust_addr_title' => $order->cust_addr_title,
            'lat' => $order->cust_addr_lat,
            'lng' => $order->cust_addr_lng,
            'address' => $order->cust_addr_detail,
            'g_route' => $order->cust_addr_g_route,
            'totalAmountInCart' => $paymentNotif->gross_amount,
            'phone' => $order->cust_addr_phone,
            'order_status_code' => $order->order_status_code,
            'payment_type_id' => $payment->id,
            'payment_description' => $paymentDesc,
            'image' => $payment->image,
            'app_code' => 1,
            'channel_names' => $paymentNotif->channel_names,
            'transaction_time' => $paymentNotif->transaction_time,
            'transaction_status' => $paymentNotif->transaction_status,
            'payment_type' => $paymentNotif->payment_type,
            'payment_code' => $paymentNotif->payment_code,
            'status_code' => $paymentNotif->status_code,
            'sisa_waktu_order' => 0,
            'payment_channel' => $payment->title,
            'channel_payment_code' => $channelPaymentCode,
            'diffInMinutes' => $orderExpired->diffInMinutes($order->created),
            'delivery_time' => $order->expected_delivery_date,
            'tgl_kirim' => $deliveryDate->translatedFormat('l, j F Y'),
            'eta' => 'ETA 08.00 - 12.00'
        ];
    }

    public function pendingPayment(Request $request)
    {
        $this->validate($request, [
            'order_code' => 'required|exists:App\Order\Order,code'
        ]);

        $order = Order::select('tr_orders.*', 'tm_members.player_id')
            ->leftJoin('tm_members', 'tm_members.id', 'tr_orders.member_id')
            ->where('tr_orders.member_id', Auth::id())
            ->where('tr_orders.code', $request->order_code)
            ->where('tr_orders.order_status_code', 'OS04')
            ->first();

        if (!$order) {
            abort(200, 'Order ' . $request->order_code . ' tidak ditemukan atau sudah dibatalkan');
        }

        $paymentNotif = DB::connection('mysql_cdb')
            ->table('payment_notifications')
            ->where('app_code', 1)
            ->where('order_id', $order->code)
            ->whereIn('status_message', ['SUCCESS', 'pending'])
            ->first();

        if (!$paymentNotif) {
            abort(200, 'Status pembayaran untuk Nomor Order ' . $request->order_code . ' tidak ditemukan');
        }

        $payment = PaymentType::find($order->payment_type_id);
        $ordeStatusCode = OrderStatus::where('code', $order->order_status_code)->first();
        $data = self::mapPendingPaymentOrder($order, $ordeStatusCode, $payment, $paymentNotif);
        $data['direct_url'] = null;

        // Regenerate payment screen
        $isRegenerate = 0;

        // Validation
        $expiredOrder = new Carbon($order->order_expired);
        if ($expiredOrder->lte(Carbon::now())) {
            $cancelOrder = OrderController::cancelAnOrder($order);
            abort(200, $cancelOrder['message']);
        } elseif ($payment->is_direct == 1) {

            $resPayment = SubmitOrderController::processDirectPayment($payment, $request, $order, $paymentNotif->gross_amount);
            
            if ($resPayment && isset($resPayment['status']) && $resPayment['status'] == 'success') {
                $isRegenerate = 1;

                if (isset($resPayment['data']['direct_url'])) {
                    $data['direct_url'] = $resPayment['data']['direct_url'];
                }
                
                if (isset($resPayment['data']['order_id_midtrans'])) {
                    $data['hutang_id'] = $data['order_id_midtrans'] = $resPayment['data']['order_id_midtrans'];
                }

            } else {
                $cancelOrder = OrderController::cancelAnOrder($order);
                abort(200, $cancelOrder['message']);
            }
        }

        $data['is_regenerate'] = $isRegenerate;

        /* $paymentDate = date('Y-m-d');
        $paymentDate = date('Y-m-d', strtotime($paymentDate));
        $contractDateBegin = date('Y-m-d', strtotime('2020-06-18'));
        $contractDateEnd = date('Y-m-d', strtotime('2020-06-30'));

        if (($paymentDate >= $contractDateBegin) && ($paymentDate <= $contractDateEnd)) {
            $froms = date('2020-06-18');
            $last = Order::select('id')
                    ->where('member_id', Auth::id())
                    ->whereDate('created', '<', $froms)
                    ->count();

            if ($last < 1) {
                $from = date('2020-06-18');
                $to = date('2020-06-31');
                $cards = Order::select('id')
                        ->where('member_id', Auth::id())
                        ->whereBetween('created', [$from, $to])
                        ->whereIn('order_status_code', ['OS18', 'OS17', 'OS03', 'OS04'])
                        ->count();

                if ($cards == '1') {
                    $pot_cashback = '<p style="width:100%;font-size:12px !important;text-align:center; line-height:1.25;"><strong>Anda mendapatkan potensi cashback maksimal Rp 50.000 untuk belanja pertama kalinya.</strong><br /></p>';
                    $data['payment_description'] = $pot_cashback . $data['payment_description'];
                }
            }
        } */

        $responseData = [
            'status' => 'Success',
            'base_url' => env('BASE_URL_BANK', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
            'base_url_payment_step' => env('BASE_URL_PAYMENT_STEP', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
            'data' => $data
        ];

        return Format::response($responseData);
    }
}
