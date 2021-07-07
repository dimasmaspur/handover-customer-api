<?php

namespace App\Http\Controllers\V1;

// use App\Helpers\Format;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\Param;
// use App\Models\Banner;
use App\Order\Voucher;
use App\Product\Category;
use App\Product\CategoryMapping;
use App\Product\MaxQty;
use App\Product\Pricing;
use App\Product\Product;
use Carbon\Carbon;
use App\Models\Widget\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function timeCoverage()
    {
        $timeDelivery = Carbon::now()->locale('id_ID');
        $cutOffTime = Param::where('param_code', 'EC_CUTOFF_TIME')->first();
        $cutOffTime = $cutOffTime && $cutOffTime->param_value != null && $cutOffTime->param_value != ''
            ? Carbon::createFromFormat('H:i', $cutOffTime->param_value)
            : Carbon::createFromFormat('H:i', '17:00');

        $orderDay = Param::where('param_code', 'EC_ORDER_DAY')->first();
        $orderDay = $orderDay && $orderDay->param_value
            ? (int) $orderDay->param_value
            : 1;

        $start = $cutOffTime->lt(Carbon::now()) 
            ? $orderDay + 1
            : $orderDay;

        $dayOffs = Param::where('param_code', 'EC_DAYOFFS')->first();
        $dayOffs = $dayOffs && $dayOffs->param_value != null && $dayOffs->param_value != ''
            ? explode(',', $dayOffs->param_value)
            : [];

        for ($i = 0; $i < 3; $i++) {
            $timeDelivery = Carbon::now()->locale('id_ID');
            $timeDelivery->addDays($start);

            $myDay = (string) $timeDelivery->format('N');

            while (in_array($myDay, $dayOffs)) {
                $timeDelivery->addDay();
                $start++;
                $myDay = (string) $timeDelivery->format('N');
            }

            $times[] = [
                'title' => $timeDelivery->translatedFormat('l, j F Y'),
                'value' => $timeDelivery->format('Y-m-d'),
            ];
            
            $start++;
        }

        return responseArray([
            'visible' => 1,
            'data' => [
                'id' => 12,
                'title' => 'Jabodetabek',
                'time' => $times
            ]
        ]);
    }

    public function banners()
    {
        $banners = Voucher::select(
                'id',
                'code AS voucher_code',
                'title AS name',
                'image',
                'start_date',
                'end_date',
                'url_terkait AS url_target',
                'is_banner',
            )
            // ->publish()
            ->where('status',1)
            ->where('publish',1)
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($banners as $banner) {
            
            if ($banner->url_target == '' || $banner->url_target == null || $banner->url_target == '-') {
                $clickable = 0;
                $banner->url_target = "";
            } else {
                $clickable = 1;
            }

            $banner->is_banner = (int) $banner->is_banner;
            $banner->is_click = $clickable;
        }

        return responseArray([
            'count' => count($banners),
            'base_url' => env('BASE_URL_VOUCHER'),
            'data' => $banners,
        ]);
    }

    public function notificationInfo(Request $request)
    {

        $widget =  Widget::select(
            'tm_widgets.id',
            'tm_widgets.name',
            'tm_widgets.tagline',
            'tm_widgets.subtagline',
        )->where('type_id',6)->where('deleted_at',NULL)->where('status',1)->first();

        if(!$widget){
            return Format::response([
                'data' => null,
            ]);    
        }
        return Format::response([
            'data' => $widget->tagline,
        ]);
     
        
    }

    public function iconCategory()
    {
        $categories = CategoryMapping::select(
                'categories_mapping.categories_id AS id',
                'categories_mapping.name', 
                'categories_mapping.picture AS image',
                'categories_mapping.description'
            )
            ->active()
            ->orderBy('categories_mapping.level')
            ->get();

        $containers[] = [
            'id' => 0,
            'name' => 'Semua Produk',
            'image' => 'cust-all.png',
            'description' => null,
        ];

        foreach ($categories as $item) {

            $containers[] = $item;
        }

        return responseArray([
            'count' => count($containers),
            'base_url' => env('BASE_URL_CATEGORY'),
            'data' => $containers,
        ]);
    }

    public function productSlide(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
        ]);

        $dcId = $request->has('dc_id') ? $request->dc_id : 12;

        $categories = CategoryMapping::select([
                'categories_mapping.categories_id AS id',
                'categories_mapping.name AS title',
                'categories_mapping.description',
            ])
            ->active()
            ->orderBy('categories_mapping.level')
            ->get();

        // Favorite Products
        $favoriteIds = [];
        if (Auth::check()) {
            $favoriteIds = Auth::user()
                ->favorites()
                ->select('product_id')
                ->active()
                ->get()
                ->pluck('product_id')
                ->toArray();
        }

        // Kelengkapan
        $kelengkapanIds = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->pluck('product_id');

        $kelengkapan = Product::withMinimalDetailWhere($dcId)
            ->whereIn('products.id', $kelengkapanIds)
            ->where('categories.id', 27)
            ->first();


        if ($kelengkapan) {
            $isFav = in_array($kelengkapan->product_id, $favoriteIds);
            $styrofoam = HomeController::formatProduct($kelengkapan, $isFav);
        } else {
            $styrofoam = null;
        }

        foreach ($categories as $keyCat => $category) {

            // tambah anak2 dari category yang dipilih ini...
            $childCategoryIds = Category::select('id')
                ->where('parent_id', $category->id)
                ->pluck('id')
                ->toArray();

            $childCategoryIds[] = $category->id;

            // dd($childCategoryIds);

            $products = Product::withMinimalDetailWhere($dcId)
                ->whereIn('categories.id', $childCategoryIds)
                ->orderBy('products.title')
                ->limit(10)
                ->get();

            if (count($products)) {
                
                $productIds = [];
                $productContainers = [];

                foreach ($products as $product) {
                    if (!in_array($product->product_id, $productIds)) {
    
                        $productIds[] = $product->product_id;
    
                        $isFav = in_array($product->product_id, $favoriteIds);
                        
                        $newProduct = HomeController::formatProduct($product, $isFav);
    
                        if ($newProduct->category_id == 49 && $styrofoam) {
                            $newProduct->is_attribute = 1;
                            $newProduct->child = [$styrofoam];
                        } else {
                            $newProduct->is_attribute = 0;
                            $newProduct->child = [];
                        }
    
                        $productContainers[] = $newProduct;
                    }
                }
    
                usort($productContainers, function ($item1, $item2) {
                    $discount1 = (int) str_replace('%', '', $item1->discount);
                    $discount2 = (int) str_replace('%', '', $item2->discount);
                    return $discount2 <=> $discount1;
                });
    
                $category->items = $productContainers;

            } else {
                unset($categories[$keyCat]);
            }
        }

        $map = [];
        foreach ($categories as $category) {
            $map[] = $category;
        }

        return responseArray([
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => $map
        ]);
    }

    public function productsByCategory(Request $request)
    {
        $this->validate($request, [
            'kategori_id' => 'required|numeric',
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
            'offset' => 'nullable|numeric|min:1',
            'limit' => 'nullable|numeric|min:1',
        ]);

        $dcId = $request->has('dc_id') ? $request->dc_id : 12;
        $products = Product::withMinimalDetailWhere($dcId);

        // Favorite Products
        $favoriteIds = [];
        if (Auth::check()) {
            $favoriteIds = Auth::user()
                ->favorites()
                ->select('product_id')
                ->active()
                ->get()
                ->pluck('product_id')
                ->toArray();
        }

        // Kelengkapan
        $kelengkapanIds = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->pluck('product_id');

        $kelengkapan = Product::withMinimalDetailWhere($dcId)
            ->whereIn('products.id', $kelengkapanIds)
            ->where('categories.id', 27)
            ->first();

        if ($kelengkapan) {
            $isFav = in_array($kelengkapan->product_id, $favoriteIds);
            $styrofoam = HomeController::formatProduct($kelengkapan, $isFav);
        } else {
            $styrofoam = null;
        }

        if ($request->kategori_id != 0) {
            $category = Category::find($request->kategori_id);

            if (!$category) {
                abort(200, "Kategori tidak ditemukan.");
            }

            $childCategoryIds = Category::select('id')
                ->where('parent_id', $request->kategori_id)
                ->pluck('id')
                ->toArray();

            $childCategoryIds[] = $request->kategori_id;

            $products = $products->whereIn('categories.id', $childCategoryIds);
        }
        
        $products = $products->orderBy('products.title')->get();

        $productIds = [];
        $productContainers = [];
        foreach ($products as $product) {
            if (!in_array($product->product_id, $productIds)) {
                $productIds[] = $product->product_id;

                $isFav = in_array($product->product_id, $favoriteIds);
                $newProduct = HomeController::formatProduct($product, $isFav);

                if ($newProduct->category_id == 49 && $styrofoam) {
                    $newProduct->is_attribute = 1;
                    $newProduct->child = [$styrofoam];
                } else {
                    $newProduct->is_attribute = 0;
                    $newProduct->child = [];
                }

                $productContainers[] = $newProduct;
            }
        }

        usort($productContainers, function ($item1, $item2) {
            $discount1 = (int) str_replace('%', '', $item1->discount);
            $discount2 = (int) str_replace('%', '', $item2->discount);
            return $discount2 <=> $discount1;
        });

        return responseArray([
            'count' => count($productContainers),
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => $productContainers
        ]);
    }

    public static function formatProduct($product, $isFav = false)
    {
        // New Attributes
        $product->use_max_qty = 0;
        $product->max_qty = 0;
        $product->is_discount = 0;
        $product->harga_coret = $product->unitprice;
        $product->discount = "0%";

        $maxQty = MaxQty::where('product_id', $product->product_id)->active()->first();
        $pricing = Pricing::where('product_id', $product->product_id)->active()->first();

        if ($maxQty) {
            $product->use_max_qty = 1;
            $product->max_qty = (int) $maxQty->max_qty;
        }

        if ($pricing) {

            $product->is_discount = 1;
            $markup = $pricing->calculate($product->unitprice);
            
            if ($pricing->tipe == 1) {
                $product->discount = number_format($pricing->amount) . "%";
            } elseif ($pricing->tipe == 2) {
                // $product->discount = number_format(($pricing->amount * 100 / $pricing->markup)) . "%";
                $product->discount = number_format(($pricing->amount * 100 / $markup)) . "%";
            }

            // $product->harga_coret = $pricing->markup;
            $product->harga_coret = (string) $markup;
        }

        $product->product_id = (int) $product->product_id;
        $product->min_qty = (int) $product->min_qty;
        $product->grade = (int) $product->grade;

        $percentage = rand(31, 100) / 100;

        if ($percentage < 0) {
            $stock = "3";
            $label = "Habis Terjual";
        } elseif ($percentage > 0 && $percentage <= 0.3) {
            $stock = "2";
            $label = "Hampir Habis";
        } elseif ($percentage > 0.3) {
            $stock = "1";
            $label = "Stok Tersedia";
        }

        if ($maxQty) {
            $label = "Stok Terbatas";
        }

        $product->stock = $stock;
        $product->label = $label;
        $product->is_favorite = $isFav ? 1 : 0;

        // Bunga
        if (Str::contains(strtolower($product->category), 'bunga')) {
            $product->parent_id = 2;
            $product->parent_title = "Kedai Bunga";
        } else {
            $product->parent_id = 1;
            $product->parent_title = "Kedai Sayur";
        }

        return $product;
    }
}
