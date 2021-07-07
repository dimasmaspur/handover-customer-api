<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Helpers\Format;
use App\Member\Point;
use App\Member\PointHistory;
use App\Models\Param;
use App\Order\Order;
use App\Order\OrderDetail;
use App\Order\OrderHistory;
use App\Member\Address;
use App\Member\AddressType;
use App\Order\OrderStatus;
use App\Order\PaymentType;
use App\Order\Voucher;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function paymentReceived(Request $request)
    {
        $this->validate($request, [
            'order_id' => 'required'
        ]);

        $order = Order::select('id', 'code', 'expected_delivery_date', 'order_expired', 'order_status_code', 'member_id')
            ->where(function ($query) use ($request) {
                $query->where('id', $request->order_id)
                    ->orWhere('code', $request->order_id);
            })
            ->where('order_status_code', 'OS04')
            ->where('order_expired', '>', Carbon::now())
            ->first();
            // ->get();

        // return $order;

        if ($order) {

            // Get order's expected_delivery_date
            $expectedDeliveryDate = new Carbon($order->expected_delivery_date);

            // Calculate CutOff for delivery based on time
            $cutOffTime = Param::where('param_code', 'EC_CUTOFF_TIME')->first();
            $cutOffTime = $cutOffTime && $cutOffTime->param_value != null && $cutOffTime->param_value != ''
                ? Carbon::createFromFormat('H:i', $cutOffTime->param_value)
                : Carbon::createFromFormat('H:i', '17:00');

            $orderDay = Param::where('param_code', 'EC_ORDER_DAY')->first();
            $orderDay = $orderDay && $orderDay->param_value
                ? (int) $orderDay->param_value
                : 1;
    
            $cutOffDays = $cutOffTime->lt(Carbon::now()) 
                ? $orderDay + 1
                : $orderDay;

            // $cutOffDays = $cutOffTime->lt(Carbon::now()) ? 3 : 2;
            $cutOffDeliveryDate = Carbon::now();
            $cutOffDeliveryDate->addDays($cutOffDays);

            $dayOffs = Param::where('param_code', 'EC_DAYOFFS')->first();
            $dayOffs = $dayOffs && $dayOffs->param_value != null && $dayOffs->param_value != ''
                ? explode(',', $dayOffs->param_value)
                : [];

            $myDay = (string) $cutOffDeliveryDate->format('N');
            /* if ($cutOffDeliveryDate->format('N') == 7) { // Skip Sunday
                $cutOffDeliveryDate->addDay();
            } */

            while (in_array($myDay, $dayOffs)) {
                $cutOffDeliveryDate->addDay();
                $myDay = (string) $cutOffDeliveryDate->format('N');
            }

            // Set new expectedDeliveryDate to cutOffDeliveryDate when the old one is lower than it
            $newDeliveryDate = $expectedDeliveryDate->lt($cutOffDeliveryDate)
                ? $cutOffDeliveryDate
                : $expectedDeliveryDate;

            // set to "payment received"
            $order->update([
                'order_status_code' => 'OS18',
                'payment_date' => Carbon::now(),
                'expected_delivery_date' => $newDeliveryDate->format('Y-m-d') . ' 05:00:00',
            ]);

            SubmitOrderController::storeOrderHistory($order, 'OS17', 'Order status changed by Payment Service.');
            SubmitOrderController::storeOrderHistory($order, 'OS18', 'Order status changed by Payment Service.');

            // Send OneSignal Notification

            $push = false;
            $user = User::find($order->member_id);
            if ($user && $user->player_id) {
                $push = NotificationController::push([
                    'headings' => [
                        'en' => 'Payment Received',
                        'id' => 'Pembayaran Diterima',
                    ],
                    'contents' => [
                        'en' => 'Your payment for ' . $order->code . ' has been confirmed',
                        'id' => 'Pembayaran kamu untuk order ' . $order->code . ' telah diterima',
                    ],
                    'data' => [
                        'type' => 'order',
                        'type_code' => 1,
                        'order_id' => $order->id,
                        'order_code' => $order->code,
                        'menu_tab' => 'order_processed',
                        'desc' => 'order_list_screen'
                    ]
                ], [$user->player_id]);
            }

            $message = 'Payment Confirmed';

            if (isset($push['message']))
                $message .= '. ' . $push['message'];

            return $message;

        } else {
            return 'Order not found';
        }
    }

    public function index()
    {
        $orders = Order::select(
                'tr_orders.id', 'tr_orders.code AS no_order',
                'tr_orders.created', 'tr_orders.expected_delivery_date',
                'tr_orders.order_status_code',
                DB::raw("IFNULL(tr_orders.totalAmountActual, 0)
                    - IFNULL(tr_orders.totalPromoItem, 0)
                    + IFNULL(tr_orders.ongkir, 0)
                    - IFNULL(tr_points_histories.used_point, 0)
                    + IFNULL(tr_orders.biaya_admin, 0)
                    as amount"),
                'tr_orders.nominal_promo',
                'tr_orders.promo_code',
            )
            ->leftJoin('tr_points_histories', 'tr_points_histories.order_id', '=', 'tr_orders.id')
            ->where('tr_orders.member_id', Auth::id())
            ->orderBy('tr_orders.created', 'desc')
            ->limit(20)
            ->get();

        $orderStatuses = OrderStatus::select('id', 'code', 'title', 'admin_title', 'order_status_title')->get();
        foreach ($orderStatuses as $status) {
            $mapOS[$status->code] = $status->order_status_title;
        }

        $codeWaitingForPayment = ['OS01', 'OS04', 'OS06'];
        $codeOnProcess = ['OS17', 'OS18', 'OS02', 'OS05', 'OS07', 'OS08', 'OS16', 'OS10'. 'OS09'];
        $codeCompleted = ['OS03', 'OS11', 'OS12'];
        $codeCancelled = ['OS00', 'OS13', 'OS14', 'OS15'];

        $waitingForPayment = $onProcess = $completed = $cancelled = [
            'header' => '',
            'detail' => []
        ];

        foreach ($orders as $order) {

            $tglOrder = new Carbon($order->created);
            $tglPengiriman = new Carbon($order->expected_delivery_date);

            $order->tgl_order = $tglOrder->locale('id_ID')->translatedFormat('j F Y');
            $order->tgl_pengiriman = $tglPengiriman->locale('id_ID')->translatedFormat('j F Y');
            $order->status = $mapOS[$order->order_status_code];

            if (($order->promo_code != null || $order->promo_code != '') && $order->nominal_promo > 0) {
                $order->amount -= $order->nominal_promo;
            }

            $order->amount = Format::rupiah($order->amount);

            unset($order->promo_code);
            unset($order->nominal_promo);
            unset($order->created);
            unset($order->expected_delivery_date);

            if (in_array($order->order_status_code, $codeWaitingForPayment)) {
                unset($order->order_status_code);
                $waitingForPayment['detail'][] = $order;
            } elseif (in_array($order->order_status_code, $codeOnProcess)) {
                unset($order->order_status_code);
                $onProcess['detail'][] = $order;
            } elseif (in_array($order->order_status_code, $codeCompleted)) {
                unset($order->order_status_code);
                $completed['detail'][] = $order;
            } elseif (in_array($order->order_status_code, $codeCancelled)) {
                unset($order->order_status_code);
                $cancelled['detail'][] = $order;
            }
        }

        $waitingForPayment['header'] = 'Menunggu Pembayaran (' . count($waitingForPayment['detail']) . ')';
        $onProcess['header'] = 'Pesanan Diproses (' . count($onProcess['detail']) . ')';
        $completed['header'] = 'Pesanan Selesai (' . count($completed['detail']) . ')';
        $cancelled['header'] = 'Dibatalkan (' . count($cancelled['detail']) . ')';

        return responseArray([
            'data' => [
                $waitingForPayment,
                $onProcess,
                $completed,
                $cancelled
            ]
        ]);
    }

    public function show(Request $request)
    {
        $this->validate($request, [
            'order_id' => 'required|exists:App\Order\Order,id'
        ]);

        $order = Order::select(
                'tr_orders.*',
                'tr_orders.code AS no_order',
                // 'tr_orders.created',
                // 'tr_orders.expected_delivery_date',
                // 'tr_orders.order_status_code',
                // 'tr_orders.status',
                // 'tr_orders.totalAmountInCart',
                // 'tr_orders.ongkir',
                'tr_points_histories.used_point',
                DB::raw("IFNULL(tr_orders.totalAmountInCart, 0)
                    + IFNULL(tr_orders.biaya_admin, 0)
                    + IFNULL(tr_orders.ongkir, 0)
                    - IFNULL(tr_orders.totalPromoItem, 0)
                    - IFNULL(tr_points_histories.used_point, 0)
                    as amount"),
                // address
                // 'tr_orders.alamat_id',
                // 'tr_orders.cust_addr_title',
                // 'tr_orders.cust_addr_phone',
                // 'tr_orders.cust_addr_g_route',
                // 'tr_orders.cust_addr_',
                // 'tr_orders.cust_addr_detail',
                // 'tr_orders.alamat_notes',
                // 'tr_orders.remark',
            )
            ->leftJoin('tr_points_histories', 'tr_points_histories.order_id', '=', 'tr_orders.id')
            ->where('tr_orders.member_id', Auth::id())
            ->find($request->order_id);

        if (!$order) {
            abort(200, 'Order tidak ditemukan.');
        }

        // Order Status
        $orderStatus = OrderStatus::where('code', $order->order_status_code)->first();

        // Payment Type
        $paymentType = PaymentType::find($order->payment_type_id);

        $tglOrder = new Carbon($order->created);
        $tglPengiriman = new Carbon($order->expected_delivery_date);
        $order->tgl_order = $tglOrder->locale('id_ID')->translatedFormat('j F Y H:i:s');
        $order->tgl_pengiriman = $tglPengiriman->locale('id_ID')->translatedFormat('j F Y');

        $orderExpired = new Carbon($order->created);
        $orderExpired->addHours(2);

        // VOUCHER
        $voucherLabel = 'Voucher';
        if ($order->promo_code && $order->nominal_promo > 0) {
            $voucherLabel .= " ($order->promo_code)";
            $voucher = "-" . Format::rupiah($order->nominal_promo);
            $order->amount -= $order->nominal_promo;
        } else {
            $voucher = "-" . Format::rupiah(0);
        }

        $header = [
            [
                'key' => 'No. Order',
                'value' => $order->no_order,
            ],
            [
                'key' => 'Tanggal Order',
                'value' => $order->created->format('Y-m-d H:i:s'),
            ],
            [
                'key' => 'Tanggal Pengiriman',
                'value' => $order->tgl_pengiriman,
            ],
            [
                'key' => 'Status Terakhir',
                'value' => $orderStatus->order_status_title,
            ],
            [
                'key' => 'Metode Pembayaran',
                'value' => $paymentType ? $paymentType->title : '',
            ],
            [
                'key' => 'Batas Akhir Pembayaran',
                'value' => $orderExpired->format('Y-m-d H:i:s'),
            ],
            [
                'key' => 'Nominal Order',
                'value' => Format::rupiah($order->totalAmountInCart),
            ],
            [
                'key' => 'Potongan Harga',
                'value' => "-" . Format::rupiah($order->totalPromoItem),
            ],
            [
                'key' => 'Ongkos Kirim',
                'value' => Format::rupiah($order->ongkir),
            ],
            [
                'key' => $voucherLabel,
                'value' => $voucher,
            ],
            [
                'key' => 'KSI Poin',
                'value' => "-" . Format::rupiah($order->used_point),
            ],
            [
                'key' => 'Total Pembayaran',
                'value' => Format::rupiah($order->amount),
            ],
        ];

        $orderDetails = OrderDetail::select(
                'product_id AS id', 
                'product_name AS nama',
                'product_qty_awal AS qty', 
                'product_price_cust AS harga', 
                'product_unitprice AS harga_coret',
                // DB::raw('product_unitprice / IFNULL(product_price_cust, 0) - 1 discount'),
                DB::raw('IFNULL(potongan_harga_item, 0) * 100 / IFNULL(product_unitprice, 0) AS discount'),
                DB::raw("CONCAT('sm', productpicture_title) AS image"),
            )
            ->where('order_id', $order->id)
            ->get();

        foreach ($orderDetails as $detail) {
            $subtotal = $detail->harga * $detail->qty;

            // $discount = ($detail->harga_coret / $detail->harga) - 1;
            // $discount *= 100;

            // $discount = $

            $detail->harga = Format::rupiah($detail->harga);
            $detail->harga_coret = Format::rupiah($detail->harga_coret);
            // $detail->discount = number_format($discount) . "%";
            $detail->discount = number_format($detail->discount) . "%";
            $detail->subtotal = Format::rupiah($subtotal);
        }

        $status = $completed = 0;
        if ($order->order_status_code == 'OS04' && $order->payment_type_id != 52) {
            $status = 1; // enable the confirmation button
        } elseif ($order->order_status_code == 'OS12') {
            $completed = 1; // enable the complete order button
        }

        $isPaid = OrderHistory::where('order_id', $order->id)
            ->where('order_status_code', 'OS17')
            ->count();
        $cekAddress =  Address::select(
            'tm_member_addresses.id',
            'tm_member_addresses.is_primary',
            'tm_tipe_alamat.id AS tipe_id',
            'tm_tipe_alamat.title AS tipe'
        )
        ->leftJoin('tm_tipe_alamat', 'tm_tipe_alamat.id', '=', 'tm_member_addresses.tipe')
        ->where('tm_member_addresses.id', $order->alamat_id)
        ->first();



        return responseArray([
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => [
                'id' => $order->id,
                'order_code' => $order->no_order,
                'status' => $status,
                'header' => $header,
                'item' => $orderDetails,
                'alamat' => [
                    'id' => (string) $order->alamat_id ?? '',
                    'name' => (string) $order->cust_addr_title ?? '',
                    'is_primary' => $cekAddress->is_primary ?? '',
                    'tipe_id' => (string) $cekAddress->tipe_id ?? '',
                    'tipe' => (string) $cekAddress->tipe ?? '',
                    'phone' => (string) Format::castPhoneNumber($order->cust_addr_phone) ?? '',
                    'detail' => (string) $order->fullAddress() ?? '',
                    'notes' => (string) $order->alamat_notes ?? '',
                ],
                'remark' => $order->remark ?? '',
                'is_done' => $completed,
                'is_paid' => $isPaid > 0 ? 1 : 0,
                'is_direct_payment' => $paymentType && $paymentType->is_direct ? 1 : 0,
            ]
        ]);
    }

    public function completeOrder(Request $request)
    {
        $this->validate($request, [
            'order_id' => 'required|exists:App\Order\Order,id'
        ]);

        $orderStatus = 'OS12';
        $newOrderStatus = 'OS03';

        $order = Order::where('member_id', Auth::id())
            ->where('order_status_code', $orderStatus)
            ->find($request->order_id);

        $updated = false;
        if ($order) {
            $history = SubmitOrderController::storeOrderHistory($order, $newOrderStatus, 'Order Status changed by system.');
            if ($history) {
                $order->order_status_code = $newOrderStatus;
                $order->save();
                $updated = true;
            }
        }

        // Push Notification
        if (Auth::user()->player_id) {
            NotificationController::push([
                'headings' => [
                    'en' => 'Order Completed',
                    'id' => 'Pesanan Selesai',
                ],
                'contents' => [
                    'en' => 'Your order ' . $order->code . ' is now complete',
                    'id' => 'Pesanan ' . $order->code . ' telah selesai',
                ],
                'data' => [
                    'type' => 'order',
                    'type_code' => 1,
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'menu_tab' => 'order_completed',
                    'desc' => 'order_list_screen'
                ]
            ], [Auth::user()->player_id]);
        }

        return Format::response([
            'message' => $updated ? 'Success' : 'Gagal menyelesaikan order',
        ], !$updated);
    }

    public function cancelOrder(Request $request)
    {
        $this->validate($request, [
            'order_id' => 'required_without:order_code',
            'order_code' => 'required_without:order_id',
        ]);

        $orderId = $request->has('order_id') && ($request->order_id != null || $request->order_id != '')
            ? $request->order_id
            : null;
        $orderCode = $request->has('order_code') && ($request->order_code != null || $request->order_code != '')
            ? $request->order_code
            : null;

        $order = Order::select(
                'tr_orders.id', 
                'tr_orders.code', 
                'tr_orders.order_status_code',
                'tr_orders.promo_code',
                'tm_members.player_id',
            )
            ->leftJoin('tm_members', 'tm_members.id', 'tr_orders.member_id')
            ->where('tr_orders.member_id', Auth::id())
            ->where(function ($query) use ($orderId, $orderCode) {
                $query->where('tr_orders.id', $orderId)
                    ->orWhere('tr_orders.code', $orderCode);
            })
            ->first();

        if (!$order) {
            abort(200, 'Order tidak ditemukan.');
        } elseif (in_array($order->order_status_code, ['OS17', 'OS18', 'OS05'])) {
            abort(200, 'Order sudah dibayarkan tidak dapat dibatalkan.');
        } elseif (in_array($order->order_status_code, ['OS12', 'OS03'])) {
            abort(200, 'Order sudah selesai tidak dapat dibatalkan.');
        } elseif (!in_array($order->order_status_code, ['OS01', 'OS04'])) {
            abort(200, 'Hanya dapat membatalkan order yang baru dibuat.');
        }

        $cancelOrder = self::cancelAnOrder($order);

        return Format::response([
            'message' => $cancelOrder['message']
        ], $cancelOrder['error']);
    }

    public static function cancelAnOrder(Order $order)
    {
        $osCode = 'OS00';

        DB::beginTransaction();
        // Update Order
        $update = $order->update([
            'order_status_code' => $osCode
        ]);

        $orderHistory = null;

        if ($update) {
            $orderHistory = SubmitOrderController::storeOrderHistory($order, $osCode, 'Order cancelled by ' . Auth::user()->fullname);

            // Return Point
            $point = PointHistory::where('order_id', $order->id)
                ->where('transfered_type', 2) // 2 = point used to pay order.
                ->first();

            if ($point) {
                $update = Point::where('member_id', Auth::id())->increment('point', $point->used_point);

                if ($update) {
                    PointHistory::create([
                        'member_id' => Auth::id(),
                        'order_id' => $order->id,
                        'used_point' => $point->used_point,
                        'transfered_type' => 1, // 1 = added to KSI point
                        'remark' => 'Points returned from cancelled Order ' . $order->code,
                        'created_by' => 0, // System
                        'created_at' => Carbon::now()
                    ]);
                }
            }

            // Return Voucher Quota
            $voucher = Voucher::where('code', $order->promo_code)->first();

            if ($voucher) {
                $voucher->pemakaian_quota -= 1;
                if ($voucher->pemakaian_quota < 0) {
                    $voucher->pemakaian_quota = 0;
                }
                $voucher->save();
            }

            // Send Push Notification
            if ($order->player_id) {
                NotificationController::push([
                    'headings' => [
                        'en' => 'Order Cancelled',
                        'id' => 'Pesanan Dibatalkan',
                    ],
                    'contents' => [
                        'en' => 'Your order ' . $order->code . ' has been cancelled',
                        'id' => 'Pesanan ' . $order->code . ' dibatalkan',
                    ],
                    'data' => [
                        'type' => 'order',
                        'type_code' => 1,
                        'order_id' => $order->id,
                        'order_code' => $order->code,
                        'menu_tab' => 'order_cancelled',
                        'desc' => 'order_list_screen'
                    ]
                ], [$order->player_id]);
            }
        }

        if ($orderHistory) {
            $error = false;
            $message = 'Order dibatalkan.';
            DB::commit();
        } else {
            $error = false;
            $message = 'Gagal membatalkan order.';
            DB::rollBack();
        }

        return [
            'error' => $error,
            'message' => $message,
        ];
    }
}
