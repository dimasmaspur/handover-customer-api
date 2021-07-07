<?php

namespace App\Http\Controllers\V3;

use App\Http\Controllers\Controller;
use App\Helpers\Format;
use App\Member\Address;
use App\Member\Distance;
use App\Member\Point;
use App\Member\PointHistory;
use App\Models\Param;
use App\Models\Wilayah\CoverageDetail;
use App\Order\Order;
use App\Order\OrderDetail;
use App\Order\OrderHistory;
use App\Order\OrderMapping;
use App\Order\OrderStatus;
use App\Order\PaymentType;
use App\Order\Voucher;
use App\Product\MaxQty;
use App\Product\Pricing;
use App\Product\Product;
use App\Wilayah\Wilayah;
use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SubmitOrderController extends Controller
{
    public function submitOrder(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'required|exists:App\Wilayah\Wilayah,id',
            'pool_id' => 'required|exists:App\Wilayah\Pool,id',
            'alamat_id' => 'required|exists:App\Member\Address,id',
            'ts_id' => 'required|numeric',
            'lapak_id' => 'required|numeric',
            'jarak_pengiriman' => 'required|numeric',
            'payment_type' => 'nullable',
            'hargatotal' => 'required|numeric',
            'ongkir' => 'required|numeric',
            'produk.*.id' => 'required|exists:App\Product\Product,id',
            'produk.*.harga' => 'required|numeric',
            'produk.*.qty' => 'required|numeric',
            'tgl_pengiriman' => 'required|string',
            'poin' => 'nullable|numeric',
            'remark' => 'nullable|string',
            'voucher_code' => 'nullable|exists:App\Order\Voucher,code',
            'nominal_voucher' => 'nullable|numeric|gt:0',
        ]);

        // Assign these variables to validate the request
        if ($request->has('hargatotal')) {
            $hargaTotal = ($request->hargatotal != null || $request->hargatotal != '')
                ? $request->hargatotal
                : 0;
        } else {
            $hargaTotal = 0;
        }

        if ($request->has('ongkir')) {
            $ongkir = ($request->ongkir != null || $request->ongkir != '')
                ? $request->ongkir
                : 0;
        } else {
            $ongkir = 0;
        }

        if ($request->has('poin')) {
            $poin = ($request->poin != null || $request->poin != '')
                ? $request->poin
                : 0;
        } else {
            $poin = 0;
        }

        if ($request->has('nominal_voucher')) {
            $nominalVoucher = ($request->nominal_voucher != null || $request->nominal_voucher != '')
                ? $request->nominal_voucher
                : 0;
        } else {
            $nominalVoucher = 0;
        }

        // Open Order Validation
        $orderTime = Param::where('param_code', 'EC_ORDER_TIME')->first();
        if ($orderTime && $orderTime->param_value != null && $orderTime->param_value != '') {
            $startOrderTime = Carbon::createFromFormat('H:i', $orderTime->param_value);
            $currentTime = Carbon::now();
            if ($currentTime->lt($startOrderTime)) {
                abort(200, 'Pesanan baru dibuka pukul ' . $startOrderTime->format('H:i'));
            }
        }

        // Delivery Date Validation
        $cutOffTime = Param::where('param_code', 'EC_CUTOFF_TIME')->first();
        $cutOffTime = $cutOffTime && $cutOffTime->param_value != null && $cutOffTime->param_value != ''
            ? Carbon::createFromFormat('H:i', $cutOffTime->param_value)
            : Carbon::createFromFormat('H:i', '17:00');

        $orderDay = Param::where('param_code', 'EC_ORDER_DAY')->first();
        $orderDay = $orderDay && $orderDay->param_value
            ? (int) $orderDay->param_value
            : 1;

        $earliestDeliveryDate = $cutOffTime->lt(Carbon::now()) 
            ? $orderDay + 1
            : $orderDay;

        // $earliestDeliveryDate = $cutOffTime->lt(Carbon::now()) ? 3 : 2;

        $earliestDeliveryDate = Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 00:00:00')
            ->addDays($earliestDeliveryDate)
            ->locale('id_ID');

        $dayOffs = Param::where('param_code', 'EC_DAYOFFS')->first();
        $dayOffs = $dayOffs && $dayOffs->param_value != null && $dayOffs->param_value != ''
            ? explode(',', $dayOffs->param_value)
            : [];

        $myDay = (string) $earliestDeliveryDate->format('N');
        /* if ($earliestDeliveryDate->format('N') == 7) { // Skip Sunday
            $earliestDeliveryDate->addDay();
        } */

        while (in_array($myDay, $dayOffs)) {
            $earliestDeliveryDate->addDay();
            $myDay = (string) $earliestDeliveryDate->format('N');
        }
    
        $userDeliveryDate = new Carbon($request->tgl_pengiriman);
        $userDeliveryDate->locale('id_ID');

        $myUserDay = (string) $userDeliveryDate->format('N');

        if ($userDeliveryDate->lt($earliestDeliveryDate)) {
            abort(200, 'Tanggal pengiriman paling cepat adalah ' . $earliestDeliveryDate->translatedFormat('l, j F Y'));
        }

        if (in_array($myUserDay, $dayOffs)) {
            abort(200, 'Pengiriman tidak bisa dilakukan di hari ' . $userDeliveryDate->translatedFormat('l'));
        }

        // Voucher Validations
        if ($request->has('voucher_code') && ($request->voucher_code != '' || $request->voucher_code != null) && $nominalVoucher > 0) {
            $voucher = Voucher::where('code', $request->voucher_code)
                ->active()
                ->first();

            if (!$voucher) {
                abort(200, 'Voucher tidak ditemukan');
            }

            $usedVoucherTimes = Order::where('order_status_code', '!=', 'OS00')
                ->where('member_id', Auth::id())
                ->where('promo_code', $voucher->code)
                ->count();

            if ($usedVoucherTimes >= $voucher->maks_penggunaan) {
                abort(200, 'Batas penggunaan promo Anda telah tercapai.');
            }
        } else {
            $nominalVoucher = 0;
        }

        // Calculate Actual Payment Amount
        $actualPaymentAmount = $hargaTotal + $ongkir - $poin - $nominalVoucher;

        // Check System Minimum Order by Cart
        $minOrderParam = Param::where('param_code', 'MIN_ORDER')->first();
        $sysMinOrder = 10000;
        if ($minOrderParam && $minOrderParam->param_value != null && $minOrderParam->param_value != '') {
            $sysMinOrder = (int) $minOrderParam->param_value;
        }
        // Minimum Order by Cart
        if ($hargaTotal < $sysMinOrder) {
            abort(200, "Minimal belanja " . Format::rupiah($sysMinOrder));
        }

        // Check Minimum Order by VA
        if ($actualPaymentAmount < 10000) {
            abort(200, "Minimal pembayaran " . Format::rupiah(10000));
        }

        // Check if user attempted to use KSI point but the APK didn't send payment_type
        if ($actualPaymentAmount > 0 && (!$request->has('payment_type') || $request->payment_type == 0)) {
            abort(200, 'Harap pilih metode pembayaran.');
        }

        /* Kelengkapan Validation... */
        // Map Product Requests
        $productIds = [];
        foreach ($request->produk as $item) {
            $productIds[] = $item['id'];
            $mapProducts[$item['id']] = $item;
        }

        /* Product Validation */
        $unavailableProducts = Product::select('products.id', 'products.title')
            ->join('product_wilattrs', function ($join) use ($request) {
                $join->on('product_wilattrs.product_id', '=', 'products.id')
                    ->where('product_wilattrs.publish', 1)
                    ->where('product_wilattrs.wilayah_id', $request->dc_id);
            })
            ->whereIn('products.id', $productIds)
            ->where(function (Builder $query) {
                return $query->where('products.status', 0)
                    ->orWhere('products.publish', 0)
                    ->orWhere('product_wilattrs.is_cust', 0);
            })
            ->orderBy('products.title')
            ->get();

        $unavailableProductTitles = [];
        foreach ($unavailableProducts as $item) {
            $unavailableProductTitles[] = $item->title;
        }

        if (count($unavailableProductTitles)) {
            // $productCollectionTitles = implode(', ', $unavailableProductTitles);
            //$myMessage = $productCollectionTitles . ' sudah tidak bisa dibeli lagi. Silahkan hapus dari keranjang Anda.';
            $myMessage = 'Beberapa produk sudah tidak tersedia. Silahkan pastikan kembali pesanan Anda.';
            return Format::response([
                'status' => 'Success',
                'data' => null,
                'base_url' => env('BASE_URL_BANK', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
                'base_url_payment_step' => env('BASE_URL_PAYMENT_STEP', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
                'is_refresh' => [
                    'status' => 1,
                    'message' => $myMessage,
                ]
            ]);
        }

        /* Check Product's Price Different */
        $originalProducts = Product::withMinimalDetailWhere($request->dc_id)
            ->whereIn('products.id', $productIds)
            ->get();

        $priceDiff = [];
        $maxQtyDiff = [];
        foreach ($originalProducts as $orProduct) {
            
            $unitPrice = $orProduct->unitprice;

            /* $pricing = Pricing::where('product_id', $orProduct->product_id)->active()->first();

            if ($pricing) {

                $etalase = $unitPrice;
                
                if ($pricing->tipe == 1) {
                    $etalase = (1 - $pricing->amount / 100) * $unitPrice;
                } elseif ($pricing->tipe == 2) {
                    $etalase -= $pricing->amount;
                }

                $unitPrice = $etalase . ".00";
            } */

            $maxQty = MaxQty::where('product_id', $orProduct->product_id)->active()->first();

            if ($maxQty && $mapProducts[$orProduct->product_id]['qty'] > $maxQty->max_qty) {
                // $maxQtyDiff[] = $orProduct->title;
                abort(200, 'Maksimal pembelian ' . $orProduct->title . ' adalah ' . $maxQty->max_qty . ' item.');
            }

            if ($unitPrice != $mapProducts[$orProduct->product_id]['harga']) {
                $priceDiff[] = $orProduct->title;
            }
        }

        if (count($priceDiff) > 0 || count($maxQtyDiff) > 0) {
            //$myMessage = "Terjadi perubahan harga pada " . implode(', ', $priceDiff);
            $myMessage = "Terjadi perubahan harga pada produk kami. Silahkan pastikan kembali pesanan Anda.";
            return Format::response([
                'status' => 'Success',
                'data' => null,
                'base_url' => env('BASE_URL_BANK', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
                'base_url_payment_step' => env('BASE_URL_PAYMENT_STEP', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
                'is_refresh' => [
                    'status' => 1,
                    'message' => $myMessage,
                ]
            ]);
        }

        /* Check Product's Kelengkapan */
        $kelengkapanOfOrderedProducts = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->select(
                'products_categories.product_id',
                'tm_kelengkapan.product_id AS kelengkapan_id'
            )
            ->leftJoin('products_categories', 'products_categories.category_id', '=', 'tm_kelengkapan.category_id')
            ->whereIn('products_categories.product_id', $productIds)
            ->distinct()
            ->get();

        var_dump($kelengkapanOfOrderedProducts);die();
        // dd($checkAttribute);
        $kelengkapanQty = [];
        foreach ($kelengkapanOfOrderedProducts as $item) {
            if (isset($kelengkapanQty[$item->kelengkapan_id])) {
                $kelengkapanQty[$item->kelengkapan_id] += $mapProducts[$item->product_id]['qty'];
            } else {
                $kelengkapanQty[$item->kelengkapan_id] = $mapProducts[$item->product_id]['qty'];
            }
        }

        // check if user attempt to buy kelengkapan
        $orderedKelengkapanProducts = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->whereIn('product_id', $productIds)
            ->distinct()
            ->get();

        foreach ($orderedKelengkapanProducts as $item) {
            if (!array_key_exists($item->product_id, $kelengkapanQty)) {
                $myKelengkapan = Product::select('id', 'title')->find($item->product_id);
                $myCategory = DB::connection('mysql_cdb')
                    ->table('categories_mapping')
                    ->select('id', 'name')
                    ->where('categories_id', $item->category_id)
                    ->first();

                abort(200, "Tidak dapat melakukan pembelian $myKelengkapan->title saja tanpa produk $myCategory->name.");
            }
        }

        foreach ($mapProducts as $item) {

            if (array_key_exists($item['id'], $kelengkapanQty)) {
                $parentQty = $kelengkapanQty[$item['id']];

                if ($item['qty'] > $parentQty) {
                    $kelengkapan = Product::select('id', 'title')->find($item['id']);
                    $kelengkapanTitle = $kelengkapan ? $kelengkapan->title : 'Styrofoam';

                    $myCategory = DB::connection('mysql_cdb')
                        ->table('categories_mapping')
                        ->select('categories_mapping.name')
                        ->join('tm_kelengkapan', 'tm_kelengkapan.category_id', '=', 'categories_mapping.categories_id')
                        ->where('tm_kelengkapan.product_id', $item['id'])
                        ->first();
                    $myCategoryTitle = $myCategory ? $myCategory->name : 'Frozen';

                    abort(200, "Pembelian $kelengkapanTitle tidak boleh lebih dari total item produk $myCategoryTitle.");
                }
            }

            /* if (in_array($item['id'], $kelengkapanIds)) {
                $kelengkapan = Product::select('id', 'title')->find($item['id']);
                $kelengkapanTitle = $kelengkapan ? $kelengkapan->title : 'Styrofoam';

                $parentProduct = Product::select('id', 'title')->find($item['parent_id']);
                $parentTitle = 'produk frozen';
                if ($parentProduct) {
                    $parentTitle .= " ($parentProduct->title)";
                }

                $parentItem = $mapProducts[$item['parent_id']];

                if (!$parentItem) {
                    abort(200, "Tidak dapat melakukan pembelian $kelengkapanTitle saja tanpa $parentTitle.");
                } elseif ($item['qty'] > $parentItem['qty']) {
                    abort(200, "Pembelian $kelengkapanTitle tidak boleh lebih dari item $parentTitle.");
                } elseif ($item['qty'] < $parentItem['qty']) {
                    abort(200, "Pembelian $kelengkapanTitle tidak boleh kurang dari item $parentTitle.");
                }
            } */
        }

        // Automatically use KSI Point Payment Method when the user is still selecting other payment method while the tagihan is more that Rp 0
        if ($request->payment_type != "0" && $actualPaymentAmount > 0) {
            $payment = PaymentType::find($request->payment_type);
        } else {
            $payment = PaymentType::find(52); // KSI Point Payment
        }

        // Get User's Address
        $address = Address::where('member_id', Auth::id())
            ->where('status', 1)
            ->where('publish', 1)
            ->find($request->alamat_id);

        if (!$address) {
            abort(200, 'Alamat tidak valid.');
        }

        
        // Area Coverage Validation
        $errMessage = 'Jarak delivery belum dapat kami layani.';
        $coverageDetail = CoverageDetail::where('kota_kabupaten', $address->adm_area_level_2)->first();
        

        if (!$coverageDetail) {
            abort(200, $errMessage);
        }

        /* // Distance Validation
        $distance = Distance::where('member_id', Auth::id())
                ->where('dc_id', $request->dc_id)
                ->where('address_id', $address->id)
                ->first();

        // Check if Delivery Address is available
        $wilayah = Wilayah::find($request->dc_id);
        $wilayah->radius = 50; //

        if (!$distance) {
            abort(200, $errMessage);
        } elseif ($distance && $distance->jarak > $wilayah->radius) {
            $errMessage .= ' Jarak pengiriman ke alamat Anda: ' . $distance->jarak . ' km, maksimal jarak pengiriman: ' .$wilayah->radius . ' km.';
            abort(200, $errMessage);
        } elseif ($distance && $distance->jarak <= 0) {
            abort(200, $errMessage);
        } */

        // Check if user is using KSI point
        $userPoint = Point::where('member_id', Auth::id())->first();
        if (!$userPoint && $poin > 0) {
            abort(200, 'Anda tidak memiliki KSI Poin.');
        } elseif ($userPoint && $userPoint->point < $poin) {
            abort(200, 'KSI Poin Anda kurang.');
        } elseif (
            $userPoint
            && $userPoint->point > $poin
            && $poin > ($hargaTotal + $ongkir)) {
            $poin = $hargaTotal + $ongkir;
        }

        // Reassign actualPaymentAmount
        $actualPaymentAmount = $hargaTotal + $ongkir - $poin - $nominalVoucher;

        // Response Builder...
        $error = true;
        $status = 'Error';
        $message = 'Maaf. Terdapat kesalahan dalam proses pembuatan Order Anda.';
        $returnData = null;
        $orderStatusCode = 'OS01';

        // START THE DB TRANSACTION
        DB::beginTransaction();

        // Attempt to store Order
        $order = self::storeOrder($request, $address, $payment, $hargaTotal, $ongkir);

        if ($order) {

            // Reduce user's KSI Point.
            if ($poin > 0) {
                $userPoint->point -= $poin;
                $userPoint->created_by = Auth::id();
                $userPoint->updated_by = Auth::id();
                $userPoint->save();

                // point history
                PointHistory::create([
                    'member_id' => Auth::id(),
                    'order_id' => $order->id,
                    'used_point' => $poin,
                    'transfered_type' => 2,
                    'remark' => 'Point is used to pay Order ' . $order->code,
                    'created_by' => Auth::id(),
                    'created_at' => Carbon::now()
                ]);
            }

            // Check if the user still has to pay certain amount of money...
            if ($actualPaymentAmount > 0) {

                try {

                    // $isDirect = $payment->is_direct == 1 && ($payment->url_title == "gopay" || $payment->url_title == "ovo");
                    $isDirect = $payment->is_direct == 1;
                    $resPayment = $isDirect
                        ? self::processDirectPayment($payment, $request, $order, $actualPaymentAmount)
                        : self::processDokuPayment($payment, $request, $order, $actualPaymentAmount);

                    // Check if the payment is success
                    if (isset($resPayment['status']) && $resPayment['status'] == 'success') {
                        $error = false;
                        $message = ucfirst($resPayment['message']);
                        $status = ucfirst($resPayment['status']);
                        $orderStatusCode = 'OS04';
                    } else {
                        $error = true;
                        $message = 'Terjadi kesalahan dalam memproses metode pembayaran yang Anda pilih: ' . ucfirst($resPayment['message']);
                    }

                } catch (Exception $error) {
                    $error = true;
                    $message = 'Gagal memproses pesanan Anda';
                }

                /* $getCashback = false;
                $paymentDate = date('Y-m-d');
                $paymentDate = date('Y-m-d', strtotime($paymentDate));
                $contractDateBegin = date('Y-m-d', strtotime('2020-06-18'));
                $contractDateEnd = date('Y-m-d', strtotime('2020-06-30'));

                if (($paymentDate >= $contractDateBegin) && ($paymentDate <= $contractDateEnd)) {

                    $oldPaidOrders = 0;
                    $oldPaidOrders = Order::select('id')
                        ->whereDate('created', '<', $contractDateBegin)
                        ->where('member_id', Auth::id())
                        ->whereNotIn('order_status_code', ['OS01', 'OS04', 'OS00'])
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
                    }

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
                            $resPayment['data']['payment_description'] = $pot_cashback . $resPayment['data']['payment_description'];
                        }
                    }

                    if ($getCashback) {
                        $pot_cashback = '<p style="width:100%;font-size:12px!important;text-align:center;line-height:1.25;color:#ff8036"><strong>Anda mendapatkan potensi cashback maksimal Rp 50.000 untuk belanja pertama kalinya.</strong><br /></p>';
                        $resPayment['data']['payment_description'] = $pot_cashback . $resPayment['data']['payment_description'];
                    }
                } */




                /* if ($isDirect) {

                    if (isset($resPayment['token']) && ($resPayment['token'] != null || $resPayment['token'] != '')) {
                        $error = false;
                        $message = 'Pembayaran menggunakan Gopay berhasil dipilih';
                        $status = 'Success';
                        $orderStatusCode = 'OS04';
                    } else {
                        $error = true;
                        $message = 'Gagal menggunakan Gopay';
                    }

                } else {

                    // Check if the payment is success
                    if (isset($resPayment['status']) && $resPayment['status'] == 'success') {
                        $error = false;
                        $message = ucfirst($resPayment['message']);
                        $status = ucfirst($resPayment['status']);
                        $orderStatusCode = 'OS04';
                    } else {
                        $error = true;
                        $message = 'Terjadi kesalahan dalam memproses metode pembayaran yang Anda pilih: ' . ucfirst($resPayment['message']);
                    }
                } */

            } else {
                $resPayment = null;
                $error = false;
                $status = 'Success';
                $message = 'Pembayaran berhasil menggunakan dengan KSI Poin.';
                $orderStatusCode = 'OS17';
            }

            if ($error == false) {
                /* Log Order History... */
                self::storeOrderHistory($order, 'OS01', 'Order created by ' . Auth::user()->fullname);
                self::storeOrderHistory($order, 'OS04', 'Order status changed by System.');

                if ($orderStatusCode == 'OS17') {
                    self::storeOrderHistory($order, 'OS17', 'Order status changed by System.');
                    // self::storeOrderHistory($order, 'OS18', 'Order status changed by System.');
                }

                /* Attempt to Store Order Details */
                // Prepare User's Order Details
                $sumQuantity = 0;
                $totalAmountInCart = 0;
                $totalPromoItem = 0;
                $wilayahId = $request->dc_id;

                $purchasedProductIds = [];

                foreach ($request->produk as $product) {

                    $productCart = [
                        'price' => $product['harga'],
                        'qty' => $product['qty'],
                        'parent_id' => $product['parent_id'] ?? 0
                    ];

                    $productDetail = Product::select(
                            'id',
                            'satuan_id',
                            'kemasan_id',
                            'sku',
                            'title',
                            'berat_kemasan',
                            'jumlah_kemasan'
                        )
                        ->with('kemasan')
                        ->with('satuan')
                        ->with(['hargas' => function ($query) use ($wilayahId) {
                            $query->select(
                                    'hargas.id', 
                                    'hargas.product_id', 
                                    'hargas.normalprice',
                                    'hargas.unitprice_ts',
                                    'hargas.unitprice',
                                    'hargas.index_ts_profit',
                                    'harga_grades.title AS grade_title' // GRADE
                                )
                                ->leftJoin('harga_grades', 'harga_grades.id','hargas.grade')
                                ->where('hargas.wilayah_id', $wilayahId)
                                ->where('hargas.unitprice', '>', 0)
                                ->where('hargas.min_qty', '>', 0)
                                ->where('hargas.status', 1);
                        }])
                        ->with(['indexHargas' => function ($query) use ($wilayahId) {
                            $query->select(
                                    'id', 
                                    'product_id', 
                                    'index_ts',
                                    'index_cust'
                                )
                                ->where('wilayah_id', $wilayahId);
                        }])
                        ->with(['pictures' => function ($query) use ($wilayahId) {
                            $query->select(
                                    'id', 
                                    'product_id', 
                                    'title'
                                )
                                ->where('status', 1);
                        }])
                        ->find($product['id']);

                    $errorProduct = false;
                    $errorProductArr = [];
                    if (count($productDetail->hargas) == 0 || count($productDetail->indexHargas) == 0 ) {
                        $errorProduct = true;
                        $errorProductArr[] = $productDetail->title;
                    }

                    if ($errorProduct === false) {
                        // Actually Storing Order Details
                        $orderDetail = self::storeOrderDetail($order, $productDetail, $productCart);

                        $totalAmountInCart += ($orderDetail->product_unitprice * $orderDetail->product_qty);
                        $totalPromoItem += ($orderDetail->potongan_harga_item * $orderDetail->product_qty);
                        
                        // Sum the quantity
                        $sumQuantity += $product['qty'];
                        $purchasedProductIds[] = $product['id'];
                    }
                }

                if (count($errorProductArr)) {
                    $error = true;
                    $message = implode(', ', $errorProductArr) . " tidak dapat dibeli di area yang kamu pilih.";
                }

                $expiredOrder = new Carbon($order->created);

                $expiredOrder = $resPayment && isset($resPayment['data']['diffInMinutes'])
                    ? $expiredOrder->addMinutes($resPayment['data']['diffInMinutes'])
                    : $expiredOrder->addMinutes(120);
                
                // Expired Order
                $order->order_expired = $expiredOrder->format('Y-m-d H:i:s');
                // Set Order Status Code
                $order->order_status_code = $orderStatusCode;
                // Recalculate Totals
                $order->totalAmountInCart = $totalAmountInCart;
                $order->totalAmountActual = $totalAmountInCart;
                $order->totalPromoItem = $totalPromoItem;
                // Update Order
                $order->save();

                // Store to Order Mapping
                OrderMapping::updateOrCreate([
                    'order_id' => $order->id,
                    'category_id' => 1,
                ], [
                    'member_id' => $order->member_id,
                    'address_id' => $order->alamat_id,
                    'product_ids' => implode(',', $purchasedProductIds)
                ]);

                /* if ($hargaTotal != ($totalAmountInCart - $totalPromoItem)) {
                    $message = 'Terdapat kesalahan dalam membuat order Anda.';
                    // $message .= "\n HargaTotalCart: $hargaTotal, HargaTotalSystem: $totalAmountInCart, TotalPromoItem: $totalPromoItem";
                    $error = true;
                } */

                $orderStatus = OrderStatus::where('code', $orderStatusCode)->first();

                /* $returnData = $isDirect
                    ? $resPayment
                    : self::responseDataBuilder($order, $orderStatus, $sumQuantity, $resPayment, $payment, $actualPaymentAmount); */

                $returnData = self::responseDataBuilder($order, $orderStatus, $sumQuantity, $resPayment, $payment, $actualPaymentAmount);

                // $returnData['total_discount'] = $totalPromoItem;
            }
        }

        if ($error == false) {
            DB::commit();

            $responseData = [
                'status' => $status,
                'message' => $message,
                'base_url' => env('BASE_URL_BANK', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
                'base_url_payment_step' => env('BASE_URL_PAYMENT_STEP', 'http://devs.kedaisayur.com/sayur/files/payment_type/'),
                'data' => $returnData,
                'is_refresh' => [
                    'status' => 0,
                    'message' => $message,
                ]
            ];

            // Remove base_url_payment_step if user is using IOS
            if (
                $request->has('device_info') 
                && Str::contains($request->device_info, 'iOS') 
                || $request->has('phone_type') 
                && Str::contains($request->phone_type, 'iPhone') 
            ) {
                unset($responseData['base_url_payment_step']);
            }

            // Send Waiting for Payment Notification
            if (Auth::user()->player_id) {
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

            return Format::response($responseData);
        } else {
            DB::rollBack();
            abort(200, $message);
        }
    }

    public static function orderCodeGenerator()
    {
        $faker = Factory::create();
        $orderCode = strtoupper($faker->bothify('EC1??#####'));

        $check = Order::where('code', $orderCode)->count();

        if ($check) {
            self::orderCodeGenerator();
        }

        return $orderCode;
    }

    public static function storeOrder($request, $address, $payment, $hargaTotal, $ongkir)
    {
        $nominalVoucher = 0;
        $voucherCode = null;

        if ($request->has('nominal_voucher')) {
            $nominalVoucher = ($request->nominal_voucher != null || $request->nominal_voucher != '')
                ? $request->nominal_voucher
                : 0;
        }

        if ($request->has('voucher_code')) {
            $voucherCode = ($request->voucher_code != null || $request->voucher_code != '')
                ? $request->voucher_code
                : null;
        }

        if ($voucherCode && $nominalVoucher > 0) {
            $voucherCode = $request->voucher_code;
            Voucher::where('code', $request->voucher_code)->increment('pemakaian_quota');
        } elseif (!$voucherCode && $nominalVoucher > 0) {
            $voucherCode = null;
            $nominalVoucher = 0;
        }

        return Order::create([
            'code' => self::orderCodeGenerator(),
            'wilayah_id' => $request->dc_id,
            'wilayah_detail_id' => $request->ts_id,
            'wilayah_address_detail_id' => $request->lapak_id,
            'pool_id' => $request->pool_id,
            'member_id' => Auth::id(),
            'alamat_id' => $address->id, // new
            'alamat_notes' => $address->notes, // new
            'cust_addr_title' => $address->title,
            'cust_addr_email' => $address->email, // new
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
            'cust_addr_distance' => $request->jarak_pengiriman,
            'ongkir' => $ongkir,
            // 'ongkir_user' => 0,
            // 'index_ongkir_ts' => 0,
            // 'ongkir_ts' => 0,
            // 'totalPromoItem' => 0,
            'totalAmountInCart' => $hargaTotal,
            'totalPayment' => $hargaTotal,
            'totalAmountActual' => $hargaTotal,
            'payment_type_id' => $payment ? $payment->id : null,
            'payment_channel' => $payment ? $payment->channel_name : null,
            'order_status_code' => 'OS01',
            'order_expired' => Carbon::now()->addHours(2),
            'expected_delivery_date' => $request->tgl_pengiriman . " 08:00:00",
            'jarak_pengiriman' => $request->jarak_pengiriman,
            'remark' => $request->has('remark') ? $request->remark : null, // Catatan Order dari Customer
            /* Device Info */
            'imei' => $request->has('imei') ? $request->imei : null,
            'device_info' => $request->has('device_info') ? $request->device_info : null,
            'sdk_version' => $request->has('sdk_version') ? $request->sdk_version : null,
            'phone_type' => $request->has('phone_type') ? $request->phone_type : null,
            'channel_name' => 'KSI B2C',
            // Voucher / Promo Code
            'promo_code' => $voucherCode,
            'nominal_promo' => $nominalVoucher,
        ]);
    }

    public static function storeOrderDetail($order, $product, $productCart)
    {
        $productName = $product->title;

        $parentProduct = Product::select('id', 'title')->find($productCart['parent_id']);

        if ($parentProduct) {
            $productName .= " ($parentProduct->title)";
        }

        $unitPrice = $product->hargas[0]->unitprice;
        $markup =  $unitPrice;// unitprice from core's db

        $potonganHargaItem = 0;
        $pricing = Pricing::where('product_id', $product->id)->active()->first();

        if ($pricing) {

            $markup = $pricing->calculate($unitPrice);
            $potonganHargaItem = $markup - $unitPrice;
        }

        return OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_sku_code' => $product->sku,
            'product_name' => $productName,
            'product_berat_kemasan' => $product->berat_kemasan,
            'product_jumlah_kemasan' => $product->jumlah_kemasan,
            'productpicture_title' => count($product->pictures) ? $product->pictures[0]->title : null,
            'product_grade' => $product->hargas[0]->grade_title,
            'harga_id' => $product->hargas[0]->id,
            'product_normalprice' => $product->hargas[0]->normalprice,
            'product_price_ts' => $product->hargas[0]->unitprice_ts,
            'product_price_cust' => $unitPrice,
            'product_profit_ts' => $product->hargas[0]->index_ts_profit,
            'product_index_ts' => $product->indexHargas[0]->index_ts,
            'product_index_cust' => $product->indexHargas[0]->index_cust,
            'product_unitprice' => $markup, // harga markup / penipuan
            // 'profit_ts' => $product,
            'product_satuan_simbol' => $product->satuan ? $product->satuan->simbol : null,
            'product_satuan_title' => $product->satuan ? $product->satuan->title : null,
            'product_kemasan_title' => $product->kemasan ? $product->kemasan->title : null,
            'product_kemasan_simbol' => $product->kemasan ? $product->kemasan->simbol : null,
            'product_qty' => $productCart['qty'],
            'product_qty_awal' => $productCart['qty'],
            // 'product_catatan' => $product,
            // 'potongan_harga' => $product,
            'potongan_harga_item' => $potonganHargaItem,
            // 'margin_up_item' => $product,
            // 'status_detail' => $product,
            // 'status_catatan' => $product,
            // 'detail_catatan' => $product,
            // 'remain' => $product,
            // 'pod_detail_date' => $product,
        ]);
    }

    public static function storeOrderHistory($order, $status, $remark)
    {
        return OrderHistory::updateOrCreate([
            'order_id' => $order->id,
            'order_status_code' => $status,
        ], [
            'remark' => $remark,
            'status' => 1,
            'created_by' => Auth::id(),
            'modified_by' => Auth::id(),
        ]);
    }

    public static function responseDataBuilder($order, $orderStatus, $sumProduct, $resPayment, $payment, $actualPaymentAmount)
    {
        /* if ($resPayment) {
            $expiredOrder = $order->created->addMinutes($resPayment['data']['diffInMinutes'])->format('Y-m-d H:i:s');
        } else {
            $expiredOrder = $order->created->addDay()->format('Y-m-d H:i:s');
        } */

        $channelPaymentCode = '';
        if ($resPayment && isset($resPayment['data']['payment_code'])) {
            $channelPaymentCode = str_replace(' ', '', $resPayment['data']['payment_code']);
            $channelPaymentCode = chunk_split($channelPaymentCode, 4, ' ');
            $channelPaymentCode = trim($channelPaymentCode);
        }

        $data['is_paid'] = $actualPaymentAmount > 0 ? 0 : 1;
        $data['order_id'] = $order->id;
        $data['order_id_midtrans'] = $resPayment && isset($resPayment['data']['order_id_midtrans']) ? $resPayment['data']['order_id_midtrans'] : '';
        $data['created'] = $order->created->format('Y-m-d H:i:s');
        $data['code'] = trim(strrev(chunk_split(strrev($order->code),4, ' ')));
        $data['order_code'] = $order->code;
        $data['order_expired'] = $order->order_expired;
        $data['ts_title'] = $orderStatus ? $orderStatus->order_status_title : '';
        $data['sum_product'] = $sumProduct;
        $data['cust_addr_title'] = $order->cust_addr_title;
        $data['lat'] = $order->cust_addr_lat;
        $data['lng'] = $order->cust_addr_lng;
        $data['address'] = $order->cust_addr_detail;
        $data['g_route'] = $order->cust_addr_g_route;
        $data['totalAmountInCart'] = $actualPaymentAmount;
        $data['phone'] = $order->cust_addr_phone;
        $data['order_status_code'] = $orderStatus ? $orderStatus->code : '';
        $data['payment_type_id'] = $resPayment && isset($resPayment['data']['payment_type_id']) ? $resPayment['data']['payment_type_id'] : '';
        $data['payment_description'] = $resPayment && isset($resPayment['data']['payment_description']) ? $resPayment['data']['payment_description'] : null;
        $data['image'] = $resPayment && isset($resPayment['data']['image']) ? $resPayment['data']['image'] : '';
        $data['app_code'] = 1;
        $data['channel_names'] = $payment ? $payment->short_title . ' - ' .$payment->channel_name : '';
        $data['transaction_time'] = date('Y-m-d H:i:s');
        $data['transaction_status'] = $resPayment && isset($resPayment['data']['detail_res']) ? $resPayment['data']['detail_res']['res_response_msg'] : '';
        $data['payment_type'] = $payment ? $payment->payment_channel : '';
        $data['payment_code'] = $resPayment && isset($resPayment['data']['detail_res']) ? $resPayment['data']['detail_res']['res_payment_code'] : '';
        $data['status_code'] = $resPayment && isset($resPayment['data']['detail_res']) ? $resPayment['data']['detail_res']['res_response_code'] : '';
        $data['sisa_waktu_order'] = 0;
        $data['payment_channel'] = $payment ? $payment->title : '';
        $data['channel_payment_code'] = $channelPaymentCode;
        $data['diffInMinutes'] = $resPayment && isset($resPayment['data']['diffInMinutes']) ? $resPayment['data']['diffInMinutes'] : "0";

        $deilveryTime = new Carbon($order->expected_delivery_date);
        $deilveryTime->locale('id_ID');
        $data['delivery_time'] = $deilveryTime ? $deilveryTime->format('Y-m-d H:i:s') : '';
        $data['tgl_kirim'] = $deilveryTime ? $deilveryTime->translatedFormat('l, j F Y') : '';
        $data['eta'] = 'ETA 08.00 - 12.00';

        $data['direct_url'] = $resPayment && isset($resPayment['data']['direct_url']) ? $resPayment['data']['direct_url'] : ""; 

        return $data;
    }
    

    public static function processDirectPayment($payment, $request, $order, $actualPaymentAmount)
    {
        $baseUrlPayment = env('BASE_URL_PAYMENT');
        if (Str::endsWith($baseUrlPayment, 'v1/') || Str::endsWith($baseUrlPayment, 'v2/')) {
            $baseUrlPayment = substr($baseUrlPayment, 0, -3);
        }

        $defaultTimeout = 120;
        $expiredMinute = Param::where('param_code', 'EC_ORDER_EXPIRED')->first();
        $expiredMinute = $expiredMinute && $expiredMinute->param_value != null && $expiredMinute->param_value != ''
            ? (int) $expiredMinute->param_value
            : $defaultTimeout;

        if ($expiredMinute <= 0) {
            $expiredMinute = $defaultTimeout;
        }

        $resPayment = Http::post($baseUrlPayment. 'v1/Payment/directPayment', [
            'app_code' => 1,
            'url_title' => $payment->url_title,
            'channel_name' => $payment->channel_name ? $payment->channel_name : 'doku',
            'amount' => number_format($actualPaymentAmount, 2, '.', ''),
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

        $resPayment = $resPayment->json() ?? [];
        
        if (count($resPayment)) {
            $resPayment['order_id'] = $order->id;
            $resPayment['order_code'] = $order->code;
        }

        return $resPayment;
    }
    
    private static function processDokuPayment($payment, $request, $order, $actualPaymentAmount)
    {
        $defaultTimeout = 120;
        $expiredMinute = Param::where('param_code', 'EC_ORDER_EXPIRED')->first();
        $expiredMinute = $expiredMinute && $expiredMinute->param_value != null && $expiredMinute->param_value != ''
            ? (int) $expiredMinute->param_value
            : $defaultTimeout;

        if ($expiredMinute <= 0) {
            $expiredMinute = $defaultTimeout;
        }

        // Make a request to vaGenerate in Payment API
        $baseUrlPayment = env('BASE_URL_PAYMENT');
        $paymentVersion = $request->segment(2);
                
        if (Str::endsWith($baseUrlPayment, 'v1/') || Str::endsWith($baseUrlPayment, 'v2/')) {
            $baseUrlPayment = substr($baseUrlPayment, 0, -3);

            $paymentVersion = 'v2';

            /* if (
                    $request->has('device_info') 
                    && Str::contains($request->device_info, 'iOS') 
                    || $request->has('phone_type') 
                    && Str::contains($request->phone_type, 'iPhone') 
                ) {
                    $paymentVersion = 'v1';
                } else {
                    $paymentVersion = 'v2';
                } */
        }

        $baseUrlPayment .= $paymentVersion;

        $resPayment = Http::post($baseUrlPayment. '/Payment/vaGenerate', [
            'app_code' => 1,
            'url_title' => $payment->url_title,
            'channel_name' => $payment->channel_name ? $payment->channel_name : 'doku',
            'amount' => $actualPaymentAmount,
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

        $resPayment = $resPayment->json();
        
        return $resPayment;
    }
}
