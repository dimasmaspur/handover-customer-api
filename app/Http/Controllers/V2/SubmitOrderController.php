<?php

namespace App\Http\Controllers\V2;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\SubmitOrderController as V1SubmitOrderController;
use App\Member\Address;
use App\Member\Point;
use App\Member\PointHistory;
use App\Models\Param;
use App\Order\DeliveryFee;
use App\Order\Order;
use App\Order\OrderDetail;
use App\Order\OrderMapping;
use App\Order\OrderStatus;
use App\Order\PaymentType;
use App\Order\Voucher;
use App\Product\Pricing;
use App\Product\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
class SubmitOrderController extends Controller
{
    private $refreshResponse;

    public function __construct()
    {
        $this->refreshResponse = [
            'status' => 'Success',
            'data' => null,
            'base_url' => env('BASE_URL_BANK', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
            'base_url_payment_step' => env('BASE_URL_PAYMENT_STEP', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
            'is_refresh' => [
                'status' => 1,
                'message' => null,
            ]
        ];
    }

    public function submitOrder(Request $request)
    {
        // return $request->all();
        // Request Validation
        $validator = Validator::make($request->all(), [
            'dc_id'             => 'required|exists:App\Wilayah\Wilayah,id',
            'tgl_pengiriman'    => 'required|date|after_or_equal:tomorrow',
            'ongkir'            => 'required|numeric',
            'hargatotal'        => 'required|numeric',
            'poin'              => 'nullable|numeric',
            'voucher_code'      => 'nullable|required_with:nominal_voucher|exists:App\Order\Voucher,code',
            'nominal_voucher'   => 'nullable|required_with:voucher_code|numeric',
            'payment_type'      => 'required|exists:App\Order\PaymentType,id',
            'remark'            => 'nullable',

            'keranjang'                 => 'required',
            'keranjang.*.alamat_id'     => 'required|exists:App\Member\Address,id',
            'keranjang.*.parent_id'     => 'required|exists:App\Order\Store,id',
            'keranjang.*.produk'        => 'required',

            'keranjang.*.produk.*.id'       => 'required|exists:App\Product\Product,id',
            'keranjang.*.produk.*.harga'    => 'required|numeric',
            'keranjang.*.produk.*.qty'      => 'required|numeric|min:1',

        ], [
            'tgl_pengiriman.after_or_equal' => ':attribute pengiriman paling cepat adalah besok hari.'
        ], [
            'alamat_id' => 'Alamat',
            'dc_id' => 'DC',
            'tgl_pengiriman' => 'Tanggal pengiriman',
            'ongkir' => 'Ongkir',
            'hargatotal' => 'Harga total',
            'poin' => 'Poin',
            'voucher_code' => 'Kode voucher',
            'nominal_voucher' => 'Nominal potongan voucher',
            'payment_type' => 'Metode pembayaran',
            'remark' => 'Catatan',

            'keranjang.*.alamat_id' => 'Alamat',
            'keranjang.*.parent_id' => 'Tanggal Delivery',
            'keranjang.*.remark' => 'Catatan',

            'keranjang.*.produk.*.id' => 'Beberapa produk',
            'keranjang.*.produk.*.harga' => 'Harga produk',
            'keranjang.*.produk.*.qty' => 'Qty produk',
        ]);

        // $validator->sometimes('attribute', 'required', function ($input) {});

        if ($validator->fails()) {
            $errors = array_unique($validator->errors()->all());
            abort(200, implode(' ', $errors));
        }

        /* Order Validation
        - Open Order
        - Delivery Date
        - Area Coverage Validation
        - Minimum Payment Amount
        - Point Validation
        - Voucher
        - Product Availability
        - Product Price Difference
        - Product's additional, complementary items*/

        $shouldRefresh = SubmitOrderValidation::runValidation($request);

        if ($shouldRefresh) {
            $response = $this->refreshResponse;
            $response['is_refresh']['message'] = $shouldRefresh;
            return Format::response($response);
        }

        DB::beginTransaction();

        $response = [
            'error' => true,
            'message' => 'Oops!',
        ];

        try {
            
            // Store Order
            $order = self::storeOrder($request);

            // Store Order History
            V1SubmitOrderController::storeOrderHistory($order, 'OS01', 'Order created by ' . Auth::user()->fullname);

            // Calculate Grand Total
            $subtotal = $request->hargatotal;
            // $ongkir = $subtotal >= 100000
            //     ? 0
            //     : $request->ongkir;
            $point = $request->has('poin') && $request->poin
                ? $request->poin
                : 0;
            $voucher = $request->has('nominal_voucher') && $request->nominal_voucher
                ? $request->nominal_voucher
                : 0;

            // Assign Initial Ongkir
            $ongkir = $request->ongkir;
            
            // Free Delivery Cost
            /* $freeDeliveryMinOrder = 100000;
            $freeDeliveryParam = Param::where('param_code', 'EC_FREE_DELIVERY_ACTIVE')->first();
            $freeDeliveryMinOrderParam = Param::where('param_code', 'EC_FREE_DELIVERY_MIN_ORDER')->first();
            $freeDeliveryPeriodParam = Param::where('param_code', 'EC_FREE_DELIVERY_EXPIRED')->first();

            if ($freeDeliveryParam && $freeDeliveryMinOrderParam && $freeDeliveryPeriodParam) {

                $today = Carbon::now();
                $expiredOffer = new Carbon($freeDeliveryPeriodParam->param_value);
                $freeDeliveryMinOrder = $freeDeliveryMinOrderParam->param_value;

                if (
                    $freeDeliveryParam->param_value == 1
                    && $subtotal >= $freeDeliveryMinOrder
                    && $today->lte($expiredOffer)
                ) {
                    $ongkir = 0;
                }
            } */

            // Delivery Fee Yay Bitch!, Including the free one, yay
            /* $deliveryFee = DeliveryFee::where('min_order', '<=', $subtotal)->active()->first();
            if ($deliveryFee) {
                $ongkir = $deliveryFee->delivery_fee;
            } */

            /* $grandTotal = $request->hargatotal 
                + ($request->has('ongkir') && $request->ongkir ? $request->ongkir : 0) 
                + ($request->has('poin') && $request->poin ? $request->poin : 0)
                + ($request->has('nominal_voucher') && $request->nominal_voucher ? $request->nominal_voucher : 0); */

            $grandTotal = $subtotal + $ongkir - $point - $voucher;

            // Payment
            $payment = PaymentType::find($request->payment_type);

            // Payment Response
            $resPayment = null;
            $resPayment = self::processPayment($order, $payment, $grandTotal);

            // dd($resPayment);
            
            if (isset($resPayment['status']) && $resPayment['status'] == 'success') {
                
                $response['error'] = false;
                $response['message'] = $resPayment['message'];
                $response['status'] = $resPayment['status'];

                // dd('respayment success');

                // Change Order History
                V1SubmitOrderController::storeOrderHistory($order, 'OS04', 'Order status changed by system');
                $order->order_status_code = 'OS04';

                if (isset($resPayment['data']['diffInMinutes'])) {
                    $orderExpired = new Carbon($order->created);
                    $orderExpired->addMinutes($resPayment['data']['diffInMinutes']);

                    // Set Order Expired
                    $order->order_expired = $orderExpired;
                }

            } else {
                $response['error'] = true;
                $response['message'] = 'Terjadi kesalahan dalam memproses pembayaran Anda: ' . ucfirst($resPayment['message']);
            }
            
            // Using Point
            if ($request->poin) {
                self::applyPoint($order, $request->poin);
            }
            
            // Using Voucher
            if ($voucher) {
                // Order
                $order->nominal_promo = $voucher;
                $order->promo_code = $request->voucher_code;

                // Voucher
                $myVoucher = Voucher::where('code', $request->voucher_code)->first();
                $myVoucher->pemakaian_quota++;
                $myVoucher->save();
            }

            // Products Mapping and Order Mapping
            $productMaps = [];
            foreach ($request->keranjang as $item) {
                
                $productIds = [];

                foreach ($item['produk'] as $product) {
                    $productMaps[$product['id']] = $product;
                    $productMaps[$product['id']]['parent_id'] = $item['parent_id'];
                    $productIds[] = $product['id'];
                }

                // Store to Order Mapping
                OrderMapping::updateOrCreate([
                    'order_id' => $order->id,
                    'category_id' => $item['parent_id'],
                ], [
                    'member_id' => Auth::id(),
                    'address_id' => $item['alamat_id'],
                    'product_ids' => implode(',', $productIds)
                ]);
            }

            // Store Product Detail
            $products = Product::select([
                    'products.id',
                    'products.sku AS product_sku_code',
                    'products.title AS product_name',
                    'products.berat_kemasan AS product_berat_kemasan',
                    'products.jumlah_kemasan AS product_jumlah_kemasan',
                    'productpictures.title AS productpicture_title',
                    'hargas.id AS harga_id',
                    'hargas.normalprice AS product_normalprice',
                    'hargas.unitprice_ts AS product_price_ts',
                    'hargas.unitprice AS product_price_cust',
                    'hargas.index_ts_profit AS product_profit_ts',
                    'hargas.index_ts AS product_index_ts',
                    'hargas.index_cust AS product_index_cust',
                    'harga_grades.title AS product_grade',
                    'satuans.title AS product_satuan_title',
                    'satuans.simbol AS product_satuan_simbol',
                    'kemasans.title AS product_kemasan_title',
                    'kemasans.simbol AS product_kemasan_simbol',
                ])
                ->leftJoin('productpictures', function ($join) {
                    $join->on('productpictures.product_id', 'products.id')
                        ->where('productpictures.status', 1);                        ;
                })
                ->groupBy('productpictures.product_id')
                ->join('hargas', 'hargas.product_id', 'products.id')
                ->leftJoin('harga_grades', 'harga_grades.id', 'hargas.grade')
                ->leftJoin('satuans', 'satuans.id', 'products.satuan_id')
                ->leftJoin('kemasans', 'kemasans.id', 'products.kemasan_id')
                ->whereIn('products.id', array_keys($productMaps))
                ->where('hargas.wilayah_id', $request->dc_id)
                ->where('hargas.status', 1)
                ->get();

            if (count($products) != count($productMaps)) {
                abort(200, 'Beberapa produk tidak dapat diproses.');
            }

            $pricings = Pricing::whereIn('product_id', array_keys($productMaps))
                ->where('wilayah_id', $request->dc_id)
                ->active()
                ->get();

            $totalPromoItem = 0;
            $totalProductQty = 0;
            $totalAmountActual = 0;

            foreach ($products as $product) {
                $pricing = $pricings->where('product_id', $product->id)->first();
                $orderDetail = self::storeOrderDetail($order, $product, $productMaps, $pricing);
                $totalProductQty += $orderDetail->product_qty;
                $totalPromoItem += ($orderDetail->product_qty * $orderDetail->potongan_harga_item);
                $totalAmountActual += ($orderDetail->product_qty * $orderDetail->product_unitprice);
            }

            $order->totalPromoItem = $totalPromoItem;
            $order->totalAmountActual = $totalAmountActual;
            $order->totalPayment = $grandTotal;
            $order->save();

        } catch (Exception $err) {
            $response['error'] = true;
            $response['message'] = 'Terjadi kesalahan dalam memproses membuat pesanan Anda: ' . $err->getMessage();
            // $response['message'] = $err;
        }

        if ($response['error']) {
            DB::rollBack();    
        } else {

            DB::commit();

            $response = array_merge($response, $this->refreshResponse);
            $response['is_refresh']['status'] = 0;

            $orderStatus = OrderStatus::where('code', $order->order_status_code)->first();

            $response['data'] = V1SubmitOrderController::responseDataBuilder($order, $orderStatus, $totalProductQty, $resPayment, $payment, $grandTotal);
            $response['data']['order_expired'] = $order->order_expired->format('Y-m-d H:i:s');
            $response['data']['payment_type_id'] = $payment->id;

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

            $response['data']['payment_description'] = $paymentDesc;

            if (Auth::user()->player_id) {
                self::sendPushNotification($order);
            }
        }
        
        return Format::response($response);
    }

    public static function storeOrder(Request $request)
    {
        $address = Address::find($request->keranjang[0]['alamat_id']);

        return Order::create([
            'code' => V1SubmitOrderController::orderCodeGenerator(),
            'wilayah_id' => $request->dc_id,
            'wilayah_detail_id' => $request->has('ts_id') ? $request->ts_id : 0,
            'wilayah_address_detail_id' => $request->has('lapak_id') ? $request->lapak_id : 0,
            'pool_id' => $request->has('pool_id') ? $request->pool_id : 0,
            'member_id' => Auth::id(),
            'alamat_id' => $address->id,
            'alamat_notes' => $address->notes,
            'cust_addr_title' => $address->title,
            'cust_addr_email' => $address->email,
            'cust_addr_detail' => $address->address,
            'cust_addr_g_route' => $address->g_route,
            'cust_addr_adm_area_level_1' => $address->adm_area_level_1,
            'cust_addr_adm_area_level_2' => $address->adm_area_level_2,
            'cust_addr_adm_area_level_3' => $address->adm_area_level_3,
            'cust_addr_adm_area_level_4' => $address->adm_area_level_4,
            'cust_addr_country' => $address->country,
            'cust_addr_phone' => $address->phone,
            'cust_addr_postal_code' => $address->postal_code,
            'cust_addr_lat' => $address->lat,
            'cust_addr_lng' => $address->lng,
            'cust_addr_distance' => $request->has('jarak_pengiriman') ? $request->jarak_pengiriman : 0,
            'ongkir' => $request->ongkir,
            'totalAmountInCart' => $request->hargatotal,
            'totalPayment' => $request->hargatotal + $request->ongkir,
            'totalAmountActual' => $request->hargatotal,
            'payment_type_id' => $request->payment_type,
            'order_status_code' => 'OS01',
            'order_expired' => Carbon::now()->addMinutes(120),
            'expected_delivery_date' => $request->tgl_pengiriman,
            'remark' => $request->has('remark') ? $request->remark : null,
            'jarak_pengiriman' => $request->has('jarak_pengiriman') ? $request->jarak_pengiriman : 0,
            'app_code' => 1,
            'imei' => $request->has('imei') ? $request->imei : null,
            'device_info' => $request->has('device_info') ? $request->device_info : null,
            'sdk_version' => $request->has('sdk_version') ? $request->sdk_version : null,
            'phone_type' => $request->has('phone_type') ? $request->phone_type : null,
            'channel_name' => 'KSI B2C',
        ]);
    }

    public static function storeOrderDetail(Order $order, Product $product, $productMaps, Pricing $pricing = null)
    {
        $storeData = $product->toArray();
        $storeData['order_id'] = $order->id;
        $storeData['product_id'] = $product->id;
        $storeData['store_id'] = $productMaps[$product->id]['parent_id'];
        
        $potonganHargaItem = 0;
        $unitPrice = $product->product_price_cust;
        $markup = $unitPrice;

        if ($pricing) {
            $markup = $pricing->calculate($unitPrice);
            $potonganHargaItem = $markup - $unitPrice;
        }

        $storeData['product_unitprice'] = $markup;
        $storeData['product_qty'] = $productMaps[$product->id]['qty'];
        $storeData['product_qty_awal'] = $productMaps[$product->id]['qty'];
        $storeData['potongan_harga_item'] = $potonganHargaItem;
        
        unset($storeData['id']);

        return OrderDetail::create($storeData);
    }

    public static function applyPoint(Order $order, $point)
    {
        $myPoint = Point::where('member_id', Auth::id())->first();

        $myPoint->point -= $point;
        $myPoint->created_by = Auth::id();
        $myPoint->updated_by = Auth::id();

        if ($myPoint->save()) {

            PointHistory::create([
                'member_id' => Auth::id(),
                'order_id' => $order->id,
                'used_point' => $point,
                'transfered_type' => 2,
                'remark' => 'Point is used to pay Order ' . $order->code,
                'created_by' => Auth::id(),
                'created_at' => Carbon::now()
            ]);
        }
    }

    public static function processPayment(Order $order, PaymentType $payment, $grandTotal)
    {
        // Order Expired;
        $defaultTimeout = 120;
        $expiredMinute = Param::where('param_code', 'EC_ORDER_EXPIRED')->first();
        $expiredMinute = ($expiredMinute && $expiredMinute->param_value != null && $expiredMinute->param_value != '' && $expiredMinute->param_value > 0)
            ? (int) $expiredMinute->param_value
            : $defaultTimeout;

        // Determine Payment
        $baseUrlPayment = env('BASE_URL_PAYMENT');
        if (Str::endsWith($baseUrlPayment, 'v1/') || Str::endsWith($baseUrlPayment, 'v2/')) {
            $baseUrlPayment = substr($baseUrlPayment, 0, -3);
        }
        $endpoint = $payment->is_direct == 1
            ? 'v1/Payment/directPayment'
            : 'v2/Payment/vaGenerate';

        // dd($baseUrlPayment . $endpoint);

        // Payment Response
        $response = Http::post($baseUrlPayment . $endpoint, [
            'app_code' => 1,
            'url_title' => $payment->url_title,
            'channel_name' => $payment->channel_name ? $payment->channel_name : 'doku',
            'amount' => number_format($grandTotal, 2, '.', ''),
            'order_code' => $order->code,
            'exp_time' => $expiredMinute,
            'user' => [
                'id' => Auth::id(),
                'title' => Auth::user()->fullname,
                'phone' => $order->cust_addr_phone,
                'address' => $order->cust_addr_detail,
                'email' => Auth::user()->email,
            ]
        ]);

        return $response->json() ?? [];
    }

    public static function sendPushNotification(Order $order)
    {
        NotificationController::push([
            'headings' => [
                'en' => 'Waiting for Payment',
                'id' => 'Menunggu Pembayaran',
            ],
            'contents' => [
                'en' => 'Your order ' . $order->code . ' is waiting for your payment',
                'id' => 'Pesanan ' . $order->code . ' sedang menunggu pembayaran',
            ],
            'data' => [
                'type' => 'order',
                'type_code' => 1,
                'order_id' => $order->id,
                'order_code' => $order->code,
                'menu_tab' => 'order_payment_waiting',
                'desc' => 'order_list_screen'
            ]
        ], [Auth::user()->player_id]);
    }
}
