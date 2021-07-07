<?php

namespace App\Http\Controllers\V2;

use App\Helpers\Format;
use App\Member\Address;
use App\Member\Point;
use App\Models\Param;
use App\Models\Wilayah\CoverageDetail;
use App\Order\DeliveryFee;
use App\Order\Order;
use App\Order\Voucher;
use App\Product\MaxQty;
// use App\Product\Pricing;
use App\Product\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubmitOrderValidation
{
    public static function runValidation(Request $request)
    {
        $messages = [];

        self::openOrderValidation();
        self::deliveryDateValidation($request);
        self::areaCoverageValidation($request);
        self::subtotalValidation($request);
        self::minimumOrderValidation($request);
        self::minimumPaymentValidation($request);

        $messages[] = self::deliveryFeeValidation($request);
        
        self::pointValidation($request);
        self::voucherValidation($request);
        
        $messages[] = self::productValidation($request);

        $messages = array_filter($messages);

        // Implement Mobile's Trigger
        return count($messages)
            ? implode('.', $messages)
            : null;
    }

    private static function openOrderValidation()
    {
        $orderTime = Param::where('param_code', 'EC_ORDER_TIME')->first();

        if ($orderTime && $orderTime->param_value != null && $orderTime->param_value != '') {
            
            $startOrderTime = Carbon::createFromFormat('H:i', $orderTime->param_value);
            $currentTime = Carbon::now();

            if ($currentTime->lt($startOrderTime)) {
                abort(200, 'Pesanan baru dibuka pukul ' . $startOrderTime->format('H:i'));
            }
        }
    }
    
    private static function deliveryDateValidation(Request $request)
    {
        // user's delivery date
        $deliveryDate = $request->tgl_pengiriman;

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

        $earliestDeliveryDate = Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' 00:00:00')
            ->addDays($earliestDeliveryDate)
            ->locale('id_ID');

        $dayOffs = Param::where('param_code', 'EC_DAYOFFS')->first();
        $dayOffs = $dayOffs && $dayOffs->param_value != null && $dayOffs->param_value != ''
            ? explode(',', $dayOffs->param_value)
            : [];

        $myDay = (string) $earliestDeliveryDate->format('N');

        while (in_array($myDay, $dayOffs)) {
            $earliestDeliveryDate->addDay();
            $myDay = (string) $earliestDeliveryDate->format('N');
        }
    
        $userDeliveryDate = new Carbon($deliveryDate);
        $userDeliveryDate->locale('id_ID');

        $myUserDay = (string) $userDeliveryDate->format('N');

        if ($userDeliveryDate->lt($earliestDeliveryDate)) {
            abort(200, 'Tanggal pengiriman paling cepat adalah ' . $earliestDeliveryDate->translatedFormat('l, j F Y'));
        }

        if (in_array($myUserDay, $dayOffs)) {
            abort(200, 'Pengiriman tidak bisa dilakukan di hari ' . $userDeliveryDate->translatedFormat('l'));
        }
    }

    private static function areaCoverageValidation(Request $request)
    {
        $addressIds = [];

        foreach ($request->keranjang as $item) {
            $addressIds[] = $item['alamat_id'];
        }

        $addressIds = array_unique($addressIds);

        $cities = Address::select('id', 'adm_area_level_3')
            ->where('member_id', Auth::id())
            ->where('status', 1)
            ->where('publish', 1)
            ->whereIn('id', $addressIds)
            ->pluck('adm_area_level_3')
            ->toArray();

        
            
            if (count($cities) <= 0) {
                abort(200, 'Alamat yang dipilih tidak valid.');
        } elseif (count($cities) != count($addressIds)) {
            abort(200, 'Beberapa alamat yang dipilih tidak valid.');
        }
        
        $cities = array_unique($cities);
        
        $coverageDetail = CoverageDetail::whereIn('kecamatan', $cities)->count();
        if ($coverageDetail < count($cities)) {
            abort(200, 'Jarak delivery belum dapat kami layani.');
        }
    }

    private static function subtotalValidation(Request $request)
    {
        $subtotal = 0;

        foreach ($request->keranjang as $item) {
            foreach ($item['produk'] as $product) {
                $subtotal += ($product['harga'] * $product['qty']);
            }
        }

        if ($subtotal != $request->hargatotal) {
            abort(200, 'Perhitungan subtotal keranjang tidak valid.');
        }
    }

    private static function minimumOrderValidation(Request $request)
    {
        $sysMinOrder = 15000; // 100,000 => minimum subtotal
        
        $minOrderParam = Param::where('param_code', 'MIN_ORDER')->first();

        if ($minOrderParam && $minOrderParam->param_value != null && $minOrderParam->param_value != '') {
            $sysMinOrder = (int) $minOrderParam->param_value;
        }

        if ($request->hargatotal <= $sysMinOrder) {
            abort(200, 'Minimal belanja ' . Format::rupiah($sysMinOrder));
        }
    }
    
    private static function minimumPaymentValidation(Request $request)
    {
        $sysMinOrder = 10000; // 100,000 => minimum subtotal

        $minOrderParam = Param::where('param_code', 'MIN_PAYMENT')->first();

        if ($minOrderParam && $minOrderParam->param_value != null && $minOrderParam->param_value != '') {
            $sysMinOrder = (int) $minOrderParam->param_value;
        }
        
        $grandTotal = $request->hargatotal
            + $request->ongkir
            - ($request->poin ? $request->poin : 0)
            - ($request->nominal_voucher ? $request->nominal_voucher : 0);
        
        if ($grandTotal < $sysMinOrder) {
            abort(200, 'Minimal pembayaran ' . Format::rupiah($sysMinOrder));
        }
    }
    
    private static function pointValidation(Request $request)
    {
        $point = Point::firstOrCreate([
            'member_id' => Auth::id()
        ]);

        $currentPoint = $point->point ?? 0;

        if ($currentPoint < $request->poin) {
            abort(200, 'Poin yang Anda miliki tidak cukup.');
        }
    }
    
    private static function voucherValidation(Request $request)
    {
        if ($request->voucher_code && $request->nominal_voucher) {
            
            $voucher = Voucher::where('code', $request->voucher_code)->active()->first();

            if (!$voucher) {
                abort(200, 'Voucher tidak ditemukan atau sudah tidak aktif.');
            }

            if ($voucher->pemakaian_quota >= $voucher->jumlah_quota) {
                abort(200, 'Voucher habis terpakai.');
            }

            $usedVoucherTimes = Order::where('order_status_code', '!=', 'OS00')
                ->where('member_id', Auth::id())
                ->where('promo_code', $voucher->code)
                ->count();

            if ($usedVoucherTimes >= $voucher->maks_penggunaan) {
                abort(200, 'Anda telah melebihi batas penggunaan voucher ini.');
            }
        }
    }

    private static function productValidation(Request $request)
    {
        $productMaps = [];

        foreach ($request->keranjang as $item) {

            foreach ($item['produk'] as $product) {
                $productMaps[$product['id']] = $product;
                $productMaps[$product['id']]['parent_id'] = $item['parent_id'];
            }
        }

        // dd($productMaps);

        $orderedProducts = Product::select([
                'products.id',
                'products.title',
                'hargas.unitprice'
            ])
            ->join('product_wilattrs', 'product_wilattrs.product_id', 'products.id')
            ->join('hargas', 'hargas.product_id', 'products.id')
            ->whereIn('products.id', array_keys($productMaps))
            ->where('products.status', 1)
            ->where('products.publish', 1)
            ->where('product_wilattrs.publish', 1)
            ->where('product_wilattrs.wilayah_id', $request->dc_id)
            ->where('hargas.wilayah_id', $request->dc_id)
            ->where('product_wilattrs.is_cust', 1)
            ->where('hargas.unitprice', '>', 0)
            ->where('hargas.min_qty', '>', 0)
            ->where('hargas.status', 1)
            ->get();

        
        // dd($orderedProducts);

        /* Product Availability */
        if (count($orderedProducts) < count($productMaps)) {
            return 'Beberapa produk sudah tidak tersedia. Silahkan pastikan kembali pesanan Anda.';
        }

        /* Price and Quantity */
        /* $pricings = Pricing::select([
                'id',
                'wilayah_id',
                'product_id',
                'tipe',
                'amount',
            ])
            ->whereIn('product_id', array_keys($productMaps))
            ->where('wilayah_id', $request->dc_id)
            ->active()
            ->get(); */

        $maxqtys = MaxQty::select([
                'id',
                'wilayah_id',
                'product_id',
                'max_qty',
            ])
            ->whereIn('product_id', array_keys($productMaps))
            ->where('wilayah_id', $request->dc_id)
            ->active()
            ->get();

        // dd($maxqtys);

        foreach ($orderedProducts as $product) {

            $unitprice = $product->unitprice;

            // $pricing = $pricings->where('product_id', $product->id)->first();
            $maxqty = $maxqtys->where('product_id', $product->id)->first();

            // dd($unitprice);

            /* if ($pricing) {
                $unitprice = $pricing->calculate($unitprice);
            } */

            // dd($unitprice);

            // Product Price Difference
            if ($unitprice != $productMaps[$product->id]['harga']) {
                return "Terjadi perubahan harga pada produk kami. Silahkan pastikan kembali pesanan Anda.";
                break;
            }

            // Product's Max Quantity
            if ($maxqty && ($productMaps[$product->id]['qty'] > $maxqty->max_qty)) {
                abort(200, 'Maksimal pembelian ' . $product->title . ' adalah ' . $maxqty->max_qty . ' item.');
            }
        }

        /* Additional Products e.g. Styrofoam */
        $kelengkapanOfOrderedProducts = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->select(
                'products_categories.product_id',
                'tm_kelengkapan.product_id AS kelengkapan_id'
            )
            ->leftJoin('products_categories', 'products_categories.category_id', '=', 'tm_kelengkapan.category_id')
            ->whereIn('products_categories.product_id', array_keys($productMaps))
            ->distinct()
            ->get();

        // dd($checkAttribute);
        $kelengkapanQty = [];
        foreach ($kelengkapanOfOrderedProducts as $item) {
            if (isset($kelengkapanQty[$item->kelengkapan_id])) {
                $kelengkapanQty[$item->kelengkapan_id] += $productMaps[$item->product_id]['qty'];
            } else {
                $kelengkapanQty[$item->kelengkapan_id] = $productMaps[$item->product_id]['qty'];
            }
        }

        // check if user attempt to buy kelengkapan
        $orderedKelengkapanProducts = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->whereIn('product_id', array_keys($productMaps))
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

        foreach ($productMaps as $item) {

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
        
        }
        
        return null;
    }

    private static function deliveryFeeValidation(Request $request)
    {
        $defaultDeliveryFee = 20000;
        $deliveryFee = DeliveryFee::where('min_order', '<=', $request->hargatotal)->active()->first();
        
        if ($deliveryFee) {
            $defaultDeliveryFee = $deliveryFee->delivery_fee;
        }
        
        if ($defaultDeliveryFee != $request->ongkir) {
            return 'Ada perubahan pada ongkir yang dikirim';
        }

        return null;
    }
}
