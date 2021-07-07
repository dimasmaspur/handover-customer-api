<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\SubmitOrderController;
use App\Member\Point;
use App\Member\PointHistory;
use App\Order\Order;
use App\Order\Voucher;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderCronController extends Controller
{
    public function cancelOrders()
    {
        $orders = Order::select(
                'tr_orders.id',
                'tr_orders.member_id', 
                'tr_orders.code',
                'tr_orders.order_expired',
                'tr_orders.order_status_code',
                'tr_orders.promo_code',
                'tm_members.player_id',
            )
            ->leftJoin('tm_members', 'tm_members.id', 'tr_orders.member_id')
            ->whereIn('order_status_code', ['OS01', 'OS04', 'OS06'])
            ->where('order_expired', '<', Carbon::now())
            ->orderBy('order_expired', 'desc')
            ->get();

        $cancelledOrders = 0;
        $cancelledOrderIds = [];

        try {
            
            DB::beginTransaction();
            foreach ($orders as $order) {
            
                // Cancel the Actual Order
                Order::where('id', $order->id)->update([
                    'order_status_code' => 'OS00'
                ]);
    
                // Log Order History
                SubmitOrderController::storeOrderHistory($order, 'OS00', 'Order cancelled by System');
    
                // Get KSI Point from this order
                $point = PointHistory::where('order_id', $order->id)
                    ->where('transfered_type', 2) // 2 = point used to pay order.
                    ->first();
    
                if ($point) {
                    $update = Point::where('member_id', $order->member_id)->increment('point', $point->used_point);
    
                    if ($update) {
                        PointHistory::create([
                            'member_id' => $order->member_id,
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
    
                $cancelledOrderIds[] = $order->id;
                $cancelledOrders++;
            }

            DB::commit();
            echo "Cancelled Orders: $cancelledOrders";

        } catch (Exception $error) {
            DB::rollBack();
            echo $error->getMessage();
        }
    }

    public function updateCompleteOrder()
    {
        $date = Carbon::now()->subDay();
        $orderStatus = ['OS05', 'OS17', 'OS18'];
        $orders = Order::select(
                'tr_orders.id',
                'tr_orders.member_id', 
                'tr_orders.code',
                'tr_orders.order_status_code',
                'tr_orders.expected_delivery_date',
                'tm_members.player_id',
            )
            ->leftJoin('tm_members', 'tm_members.id', 'tr_orders.member_id')
            ->whereIn('tr_orders.order_status_code', $orderStatus)
            ->where('tr_orders.expected_delivery_date', '<', $date->format('Y-m-d'))
            ->orderBy('tr_orders.expected_delivery_date', 'desc')
            ->get();

        $count = 0;

        if ($orders) {
            foreach ($orders as $order) {
                
                SubmitOrderController::storeOrderHistory($order, 'OS03', 'Order updated by System');

                $order->order_status_code = 'OS03';
                $order->save();

                // Push Notification
                if ($order->player_id) {
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
                    ], [$order->player_id]);
                }

                $count++;
            }
        }

        echo "Updated Orders: $count";
    }
}
