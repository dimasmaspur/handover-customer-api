<?php

namespace App\Http\Controllers\V2;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Member\Address;
use App\Order\Order;
use App\Order\OrderDetail;
use App\Order\OrderHistory;
use App\Order\OrderMapping;
use App\Order\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function show(Request $request)
    {
        $this->validate($request, [
            'order_id' => 'required|numeric'
        ]);

        $order = Order::select([
                'tr_orders.id',
                'tr_orders.code',
                'tr_orders.alamat_id',
                'tr_orders.created',
                'tr_orders.expected_delivery_date',
                'tr_orders.payment_date',
                'tr_orders.order_status_code',
                env('DB_DATABASE') . '.order_statuses.order_status_title',
                'tr_orders.payment_type_id',
                env('DB_DATABASE') . '.payment_types.title AS payment_type_title',
                env('DB_DATABASE') . '.payment_types.is_direct',
                'tr_orders.order_expired',
                'tr_orders.totalAmountActual',
                'tr_orders.totalAmountInCart',
                'tr_orders.totalPromoItem',
                'tr_orders.ongkir',
                'tr_orders.nominal_promo',
                'tr_orders.promo_code',
                'tr_points_histories.used_point',
                'tr_orders.remark',
            ])
            ->leftJoin(env('DB_DATABASE') . '.order_statuses', env('DB_DATABASE') . '.order_statuses.code', 'tr_orders.order_status_code')
            ->leftJoin(env('DB_DATABASE') . '.payment_types', env('DB_DATABASE') . '.payment_types.id', 'tr_orders.payment_type_id')
            ->leftJoin('tr_points_histories', function ($join) {
                $join->on('tr_points_histories.order_id', '=', 'tr_orders.id')
                    ->where('tr_points_histories.transfered_type', 2);
            })
            ->where('tr_orders.member_id', Auth::id())
            ->find($request->order_id);
            
        if (!$order) {
            abort(200, "Order tidak ditemukan");
        }

        // Check if the order is paid
        $isPaid = OrderHistory::where('order_id', $order->id)
            ->where('order_status_code', 'OS17')
            ->count();
        $isPaid = $isPaid ? 1 : 0;
    
        // Check if the order is complete
        $isComplete = OrderHistory::where('order_id', $order->id)
            ->whereIn('order_status_code', ['OS12', 'OS03'])
            ->count();
        $isComplete = $isComplete ? 1 : 0;
        
        // Check if the order is cancelled
        $isCancelled = OrderHistory::where('order_id', $order->id)
            ->whereIn('order_status_code', ['OS00'])
            ->count();
        $isCancelled = $isCancelled ? 1 : 0;

        $data['id'] = $order->id;
        $data['order_code'] = $order->code;
        $data['status'] = $order->order_status_code == 'OS04' ? 1 : 0;
        $data['remark'] = $order->remark;
        $data['is_done'] = $isComplete;
        $data['is_paid'] = $isPaid;
        $data['is_direct_payment'] = $order->is_direct ? 1 : 0;

        $voucherLabel = $order->promo_code
            ? "Voucher (" . $order->promo_code . ")"
            : "Voucher";

        // Header
        $header = [
            [
                'key' => 'No. Order',
                'value' => $order->code,
            ],
            [
                'key' => 'Tanggal Order',
                'value' => $order->created->format('Y-m-d H:i:s'),
            ],
            [
                'key' => 'Tanggal Pengiriman',
                'value' => $order->expected_delivery_date,
            ],
            [
                'key' => 'Status Terakhir',
                'value' => $order->order_status_title,
            ],
            [
                'key' => 'Metode Pembayaran',
                'value' => $order->payment_type_title,
            ],
            [
                'key' => 'Batas Akhir Pembayaran',
                'value' => $order->order_expired,
            ],
            [
                'key' => 'Nominal Order',
                'value' => Format::rupiah($order->totalAmountActual),
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
                'value' => "-" . Format::rupiah($order->nominal_promo),
            ],
            [
                'key' => 'KSI Poin',
                'value' => "-" . Format::rupiah($order->used_point),
            ],
            [
                'key' => 'Total Pembayaran',
                'value' => Format::rupiah($order->totalAmountInCart + $order->ongkir - $order->nominal_promo - $order->used_point),
            ],
        ];

        $data['header'] = $header;

        /* Order Details */

        $details = [];
        $orderDetailItems = [];
        $orderDetails = OrderDetail::select([
                'product_id AS id', 
                'product_name AS nama',
                'product_qty_awal AS qty', 
                'product_price_cust AS harga', 
                'product_unitprice AS harga_coret',
                DB::raw('IFNULL(potongan_harga_item, 0) * 100 / IFNULL(product_unitprice, 0) AS discount'),
                DB::raw("CONCAT('sm', productpicture_title) AS image"),
                'store_id'
            ])
            ->where('order_id', $order->id)
            ->get();
        
        foreach ($orderDetails as $orderDetail) {

            $myStoreId = $orderDetail->store_id
                ? (int) $orderDetail->store_id
                : 1;

            $subtotal = $orderDetail->harga * $orderDetail->qty;
            $orderDetail->harga = Format::rupiah($orderDetail->harga);
            $orderDetail->harga_coret = Format::rupiah($orderDetail->harga_coret);
            $orderDetail->discount = number_format($orderDetail->discount) . "%";
            $orderDetail->subtotal = Format::rupiah($subtotal);

            $orderDetailItems[$myStoreId][] = $orderDetail;
        }

        $orderMappings = OrderMapping::where('order_id', $order->id)->get();

        $storeIds = ["1"];
        $addressIds = [$order->alamat_id];
        $mappingStoreAddress = [];

        if (count($orderMappings)) {
            $storeIds = array_merge($storeIds, $orderMappings->pluck('category_id')->toArray());
            $addressIds = array_merge($addressIds, $orderMappings->pluck('address_id')->toArray());
            
            foreach ($orderMappings as $orderMapping) {
                $mappingStoreAddress[$orderMapping->category_id] = $orderMapping->address_id;
            }
        }

        $storeIds = array_unique($storeIds);
        $addressIds = array_unique($addressIds);

        $stores = Store::whereIn('id', $storeIds)->get();
        $addresses = Address::whereIn('id', $addressIds)->get();
        $cekAddress =  Address::select(
            'tm_member_addresses.id',
            'tm_member_addresses.is_primary',
            'tm_tipe_alamat.id AS tipe_id',
            'tm_tipe_alamat.title AS tipe'
        )
        ->leftJoin('tm_tipe_alamat', 'tm_tipe_alamat.id', '=', 'tm_member_addresses.tipe')
        ->where('tm_member_addresses.id', $order->alamat_id)
        ->first();

        foreach ($stores as $store) {

            if (isset($orderDetailItems[$store->id])) {

                $myAddressId = isset($mappingStoreAddress[$store->id])
                        ? $mappingStoreAddress[$store->id]
                        : $order->alamat_id;
    
                $myAddress = $addresses->where('id', $myAddressId)->first();
                $addressContainer = [
                    'id' => $myAddress->id,
                    'name' => $myAddress->title,
                    'phone' => Format::castPhoneNumber($myAddress->phone),
                    'notes' => $myAddress->notes,
                    'detail' => $myAddress->fullAddress(),
                    'is_primary' => $cekAddress->is_primary ?? '',
                    'tipe_id' => (string) $cekAddress->tipe_id ?? '',
                    'tipe' => (string) $cekAddress->tipe ?? ''
                ];
    
                $details[] = [
                    'parent_id' => (int) $store->id,
                    'parent_title' => $store->name,
                    'address' => $addressContainer,
                    'items' => $orderDetailItems[$store->id],
                ];
            }
        }

        $data['details'] = $details;
        $response['base_url'] = env('BASE_URL_PRODUCT');
        $response['data'] = $data;

        return Format::response($response);
    }
}
