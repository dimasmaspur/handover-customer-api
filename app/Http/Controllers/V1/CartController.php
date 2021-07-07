<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Order\Cart;
use App\Helpers\Calculate;
use App\Helpers\Format;
use App\Member\Address;
use App\Member\Distance;
use App\Member\Point;
use App\Models\Param;
use App\Order\DeliveryFee;
use App\Product\MaxQty;
use App\Product\Pricing;
use App\Product\Product;
use App\Wilayah\Wilayah;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public static function gDistance($oLat, $oLng, $dLat, $dLng)
    {
        $client = new Client([
            'base_uri' => env('BASE_URL_GMAPS')
        ]);

        $response = $client->get('distancematrix/json', [
            'query' => [
                'origins' => $oLat . ',' . $oLng,
                'destinations' => $dLat . ',' . $dLng,
                'key' => env('GMAPS_KEY'),
            ]
        ]);

        $response = json_decode($response->getBody());
        // dd($response);

        return $response->rows ? $response->rows[0]->elements[0]->distance : null;
    }

    public function storeCart(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'required|exists:App\Wilayah\Wilayah,id',
            'lapak_id' => 'required|exists:App\Wilayah\WilayahAddressDetail,id',
            'pool_id' => 'required|exists:App\Wilayah\Pool,id',
            'produk_id' => 'required|exists:App\Product\Product,id',
            'imei' => 'required|string',
            'qty' => 'required|numeric|min:1',
        ]);

        $cart = Cart::insert([
            'member_id' => Auth::id(),
            'imei' => $request->imei,
            'dc_id' => $request->dc_id,
            'lapak_id' => $request->lapak_id,
            'pool_id' => $request->pool_id,
            'product_id' => $request->produk_id,
            'qty' => $request->qty,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if ($cart) {
            return responseSuccess([
                'message' => 'Produk berhasil ditambahkan ke keranjang belanja'
            ]);
        } else {
            abort(200, 'Gagal menambahkan produk ke keranjang belanja');
        }
    }

    public function shop(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'required|exists:App\Wilayah\Wilayah,id',
            'alamat_id' => 'nullable|exists:App\Member\Address,id',
            'cart.*.product_id' => 'required|exists:App\Product\Product,id',
            'cart.*.price' => 'required|numeric',
            // 'cart.*.qty' => 'required|numeric',
        ]);

        $wilayah = Wilayah::find($request->dc_id);
        $user = Auth::user();

        if ($request->has('alamat_id')) {
            $address = Address::where('member_id', $user->id)->find($request->alamat_id);
        } else {
            $address = Address::where('member_id', $user->id)->primary()->first();
        }

        if ($address) {

            $address->phone = Format::castPhoneNumber($address->phone);
            $distance = Distance::where('member_id', Auth::id())
                ->where('dc_id', $request->dc_id)
                ->where('address_id', $address->id)
                ->first();
        }

        // Favorite Products
        $favoriteCollections = [];
        if (Auth::check()) {
            $rawfavoriteIds = Auth::user()
                ->favorites()
                ->active()
                ->get();

            if (count($rawfavoriteIds)) {
                foreach ($rawfavoriteIds as $item) {
                    $favoriteCollections[] = $item->product_id;
                }
            }
        }

        $myCart = [];
        $productIds = [];
        foreach ($request->cart as $cart) {
            $productIds[] = $cart['product_id'];
            $myCart[$cart['product_id']] = $cart;
        }

        // Assign new empty Arrays
        $imageProducts = [];
        $checkProductIds = [];
        $productContainers = [];
        $attributeIds = [];

        // Get all product from user's cart
        $products = Product::withMinimalDetailWhere($wilayah->id)
            ->whereIn('products.id', $productIds)
            ->get();

        foreach ($products as $product) {
            if (!in_array($product->product_id, $checkProductIds)) {
                $checkProductIds[] = $product->product_id;
                $formattedProduct = self::formatLocalProduct($product, 0, $favoriteCollections);
                $productContainers[] = $formattedProduct;
                $imageProducts[] = [
                    'id' => (int) $product->product_id,
                    'title' => $product->title,
                    'image' => $product->image,
                    'price' => $formattedProduct['unitprice']
                ];
            }
        }

        $subtotalCart = 0;
        $localItemContainer = [];
        $localAttributeContainer = [];

        foreach ($productContainers as $product) {

            $kelengkapanIds = DB::connection('mysql_cdb')
                ->table('tm_kelengkapan')
                ->select(
                    'tm_kelengkapan.product_id AS kelengkapan_id',
                    'products_categories.product_id',
                )
                ->leftJoin('products_categories', 'products_categories.category_id', '=', 'tm_kelengkapan.category_id')
                ->where('products_categories.product_id', $product['product_id'])
                ->distinct()
                ->get();

            // dd($kelengkapanIds);

            $checkKelengkapanIds = [];
            $kelengkapanContainer = [];

            if (count($kelengkapanIds)) {
                $myKelengkapans = Product::withMinimalDetailWhere($wilayah->id)
                    ->where('products.id', $kelengkapanIds->pluck('kelengkapan_id'))
                    ->get();

                foreach ($myKelengkapans as $kelengkapan) {
                    if (!in_array($kelengkapan->product_id, $checkKelengkapanIds)) {
                        $checkKelengkapanIds[] = $kelengkapan->product_id;
                        $kelengkapanContainer[] = self::formatLocalProduct($kelengkapan, 1, $favoriteCollections);
                        $attributeIds[] = $kelengkapan->product_id;
                    }

                    if (!in_array($kelengkapan->product_id, $checkProductIds)) {
                        $checkProductIds[] = $kelengkapan->product_id;
                        $productContainers[] = self::formatLocalProduct($kelengkapan, 1, $favoriteCollections);

                        $localAttributeContainer[] = [
                            'paren_id' => null,
                            'qty' => 1,
                            'product' => self::formatLocalProduct($kelengkapan, 1, $favoriteCollections)
                        ];
                    }
                }
            }

            $product['child'] = $kelengkapanContainer;
            $product['is_attribute'] = count($productContainers) ? 1 : 0;

            $productQty = isset($myCart[$product['product_id']]['qty']) 
                ? $myCart[$product['product_id']]['qty'] 
                : 1;

            $subtotalCart += ($product['unitprice'] * $productQty);

            $localItemContainer[] = [
                'paren_id' => null,
                'qty' => isset($myCart[$product['product_id']]['qty']) ? $myCart[$product['product_id']]['qty'] : 1,
                'product' => $product
            ];
        }

        $attribute = [];
        
        if (count($attributeIds)) {
            $kelengkapanProducts = Product::select(
                    'id',
                    'title',
                    'viewer',
                    'berat_kemasan',
                    'kemasan_id',
                    'satuan_id',
                )
                ->with('kemasan:id,title,simbol')
                ->with('satuan:id,title,simbol')
                ->with(['hargas' => function ($query) use ($wilayah) {
                    $query->select(
                            'hargas.id',
                            'hargas.product_id',
                            'hargas.wilayah_id',
                            'hargas.min_qty',
                            'hargas.unitprice',
                            'hargas.grade',
                            'harga_grades.title AS grade_title',
                        )
                        ->leftJoin('harga_grades', 'hargas.grade', '=', 'harga_grades.id')
                        ->where('hargas.wilayah_id', $wilayah->id)
                        ->where('hargas.status', 1)
                        ->where('hargas.isdefault', 1)
                        ->limit(1);
                }])
                ->with(['pictures' => function ($query) {
                    $query->select('id', 'title', 'product_id')
                        ->where('status', 1)
                        ->limit(1);
                }])
                ->whereIn('products.id', $attributeIds)
                ->get();


            foreach ($kelengkapanProducts as $item) {

                $attribute[] = [
                    'product_id' => (int) $item->id,
                    'hargas_id' => $item->hargas
                        ? (int) $item->hargas[0]->id
                        : 0,
                    'title' => $item->title,
                    'image' => $item->pictures
                        ? 'sm' . $item->pictures[0]->title
                        : '',
                    'simbol' => $item->kemasan
                        ? $item->kemasan->simbol
                        : '',
                    'berat_kemasan' => (int) $item->berat_kemasan,
                    'wilayah_id' => (int) $wilayah->id,
                    'wilayah' => $wilayah->title,
                    'min_qty' => $item->hargas
                        ? (int) $item->hargas[0]->min_qty
                        : 0,
                    'grade' => $item->hargas
                        ? (int) $item->hargas[0]->grade
                        : 0,
                    'grade_title' => $item->hargas
                        ? $item->hargas[0]->grade_title
                        : "Fresh",
                    'unitprice' => $item->hargas
                        ? (string) $item->hargas[0]->unitprice
                        : "",
                    'delivery_time' => $wilayah->delivery_time,
                    'viewer' => (int) $item->viewer,
                    'stock' => "1",
                    'label' => "Stok tersedia",
                    'is_attribute' => 1
                ];
            }
        }

        /* User's KSI POINT */
        $userPoint = Point::where('member_id', Auth::id())->first();

        if (!$userPoint) {
            Point::create([
                'member_id' => Auth::id(),
                'point' => 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $myPoint = 0;
        } else {
            $myPoint = $userPoint->point;
        }


        $hasAttribute = 0;
        if (count($attribute) > 0) {
            foreach ($attribute as $item) {
                if (!isset($myCart[$item['product_id']])) {
                    $hasAttribute = 1;
                }
            }
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
            $unavailableProductTitles[] = $item->title . " sudah tidak ada di etalase kami";
        }

        $minimalPurchase = $wilayah->minimal_belanja ? (string) $wilayah->minimal_belanja : "0";

        $paramMinPurchase = Param::where('param_code', 'MIN_ORDER')->first();
        if ($paramMinPurchase && $paramMinPurchase->param_value) {
            $minimalPurchase = $paramMinPurchase->param_value;
            $minimalPurchase = number_format($minimalPurchase, 2, '.', '');
        }

        $ongkir = 20000;
        $freeDelivery = 0;
        $freeDeliveryMinOrder = 150000;

        // Ongkir2 Manja + Gratis Ongkir check!
        $deliveryFee = DeliveryFee::where('min_order', '<=', $subtotalCart)->active()->first();
        if ($deliveryFee) {
            $ongkir = $deliveryFee->delivery_fee;

            if ($deliveryFee->delivery_fee == 0) {
                $freeDelivery = 1;
                $freeDeliveryMinOrder = (int) $deliveryFee->min_order;
            }
        }

        // Gratis Ongkir tidak Manja!
        /* $freeDeliveryModel = DeliveryFee::where('delivery_fee', 0)->active()->first();
        if ($freeDeliveryModel) {
            $freeDelivery = 1;
            $freeDeliveryMinOrder = (int) $freeDeliveryModel->min_order;
        } */

        /* $freeDeliveryParam = Param::where('param_code', 'EC_FREE_DELIVERY_ACTIVE')->first();
        $freeDeliveryMinOrderParam = Param::where('param_code', 'EC_FREE_DELIVERY_MIN_ORDER')->first();
        $freeDeliveryPeriodParam = Param::where('param_code', 'EC_FREE_DELIVERY_EXPIRED')->first();

        if ($freeDeliveryParam && $freeDeliveryMinOrderParam && $freeDeliveryPeriodParam) {

            $today = Carbon::now();
            $expiredOffer = new Carbon($freeDeliveryPeriodParam->param_value);

            $freeDeliveryMinOrder = number_format($freeDeliveryMinOrderParam->param_value, 2, '.', '');

            if (
                $freeDeliveryParam->param_value == 1
                && $subtotalCart >= $freeDeliveryMinOrder
                && $today->lte($expiredOffer)
            ) {
                $freeDelivery = 1;
            }
        } */

        return responseArray([
            'message' => 'Success',
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => [
                'gambar' => $imageProducts,
                'alamat' => $address ? [
                    'id' => $address->id,
                    'nama' => $address->title,
                    'alamat' => $address->fullAddress(),
                    'lat' => $address->lat,
                    'lng' => $address->lng,
                    'phone' => $address->phone,
                    'notes' => $address->notes,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                ] : null,
                'dc_id' => $wilayah->id,
                'pool_id' => isset($distance) ? (int) $distance->pool_id : 0,
                'lapak_id' => isset($distance) ? (int) $distance->lapak_id : 0,
                'member_id' => $user->id,
                'jarak_pengiriman' => isset($distance) ? $distance->jarak . " km" : '1 km',
                'ongkir' => isset($ongkir) ? (string) $ongkir : "0",
                'poin' => (string) $myPoint,
                'minimal_belanja' => $minimalPurchase,
                'minimal_va' => "10000",
                'has_attribute' => $hasAttribute,
                'attribute' => $attribute,
                'localCart' => [
                    'items' => $localItemContainer,
                    'attribute' => $localAttributeContainer,
                    'base_url' => env('BASE_URL_PRODUCT'),
                ],
                'miss_product' => $unavailableProductTitles,
                'cart_subtotal' => number_format($subtotalCart, 2, '.', ''),
                'free_delivery' => $freeDelivery,
                // 'free_delivery' => $freeDelivery ? true : false,
                'free_delivery_threshold' => number_format($freeDeliveryMinOrder, 2, '.', ''),
                // 'free_delivery_threshold' => $freeDeliveryMinOrder
            ]
        ]);
    }

    public static function formatLocalProduct($product, $isAttribute = 0, $favoriteCollections = [])
    {
        $label = "Stok Tersedia";
        $productId = is_array($product) ? $product['product_id'] : $product->product_id;
        $unitPrice = is_array($product) ? (string) $product['unitprice'] : (string) $product->unitprice;

        $rUseMaxQty = 0;
        $rMaxQty = 0;
        $rIsDiscount = 0;
        $rHargaCoret = $unitPrice;
        $rDiscount = "0%";

        $maxQty = MaxQty::where('product_id', $productId)->active()->first();
        $pricing = Pricing::where('product_id', $productId)->active()->first();

        if ($maxQty) {
            $rUseMaxQty = 1;
            $rMaxQty = (int) $maxQty->max_qty;
            $label = "Stok Terbatas";
        }

        if ($pricing) {

            $rIsDiscount = 1;
            $markup = $pricing->calculate($unitPrice);

            if ($pricing->tipe == 1) {
                $rDiscount = number_format($pricing->amount) . "%";
            } elseif ($pricing->tipe == 2) {
                // $rDiscount = number_format(($pricing->amount * 100 / $pricing->markup)) . "%";
                $rDiscount = number_format(($pricing->amount * 100 / $markup)) . "%";
            }

            // $rHargaCoret = $pricing->markup;
            $rHargaCoret = (string) $markup;
        }

        $isFav = in_array($productId, $favoriteCollections);

        if (is_array($product)) {
            return [
                'product_id' => (string) $product['product_id'],
                'title' => (string) $product['title'],
                'image' => (string) $product['image'],
                'min_qty' => (string) $product['min_qty'],
                'grade' => (string) $product['grade'],
                'grade_title' => (string) $product['grade_title'],
                'category' => (string) $product['category'],
                'unitprice' => $unitPrice,
                'deskripsi' => (string) $product['deskripsi'],
                'cara_penyimpanan' => (string) $product['cara_penyimpanan'],
                'manfaat' => (string) $product['manfaat'],
                'use_max_qty' => $rUseMaxQty,
                'max_qty' => $rMaxQty,
                'is_discount' => $rIsDiscount,
                'harga_coret' => $rHargaCoret,
                'discount' => $rDiscount,
                'type' => (string) $product['type'],
                'label' => $label,
                'child' => $product['child'],
                'is_attribute' => $isAttribute,
                'is_favorite' => $isFav ? 1 : 0,
                'tags' => $product['tags'],
                'label_produk' => $product['label_produk'],
                'parent_id' => Str::contains(strtolower($product['category']), 'bunga') ? 2 : 1,
                'parent_title' => Str::contains(strtolower($product['category']), 'bunga') ? 'Kedai Bunga' : 'Kedai Sayur',
            ];
        } else {
            return [
                'product_id' => (string) $product->product_id,
                'title' => (string) $product->title,
                'image' => (string) $product->image,
                'min_qty' => (string) $product->min_qty,
                'grade' => (string) $product->grade,
                'grade_title' => (string) $product->grade_title,
                'category' => (string) $product->category,
                'unitprice' => $unitPrice,
                'deskripsi' => (string) $product->deskripsi,
                'cara_penyimpanan' => (string) $product->cara_penyimpanan,
                'manfaat' => (string) $product->manfaat,
                'use_max_qty' => $rUseMaxQty,
                'max_qty' => $rMaxQty,
                'is_discount' => $rIsDiscount,
                'harga_coret' => $rHargaCoret,
                'discount' => $rDiscount,
                'type' => (string) $product->type,
                'label' => $label,
                'child' => $product->child,
                'is_attribute' => $isAttribute,
                'is_favorite' => $isFav ? 1 : 0,
                'tags' => $product->tags,
                'label_produk' => $product->label_produk,
                'parent_id' => Str::contains(strtolower($product->category), 'bunga') ? 2 : 1,
                'parent_title' => Str::contains(strtolower($product->category), 'bunga') ? 'Kedai Bunga' : 'Kedai Sayur',
            ];
        }
    }
}
