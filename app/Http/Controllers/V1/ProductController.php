<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Helpers\Format;
use App\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
            'produk_id' => 'required|exists:App\Product\Product,id',
        ]);

        $request->dc_id = $request->dc_id ? $request->dc_id : 12;

        $product = Product::withDetail($request->dc_id)->find($request->produk_id);

        if (!$product) {
            abort(200, 'Produk tidak tersedia');
        }

        $grade = [
            'grade' => $product->grade,
            'grade_title' => $product->grade_title,
        ];
        $atribut = [
            [
                'key' => 'Deskripsi',
                'value' => $product->deskripsi,
            ],
            [
                'key' => 'Cara Penyimpanan',
                'value' => $product->cara_penyimpanan,
            ],
            [
                'key' => 'Manfaat',
                'value' => $product->manfaat,
            ],
        ];

        // Favorite
        $isFav = false;
        if (Auth::check()) {
            $checkFav = Auth::user()
                ->favorites()
                ->active()
                ->where('product_id', $product->product_id)
                ->first();

            if ($checkFav)
                $isFav = true;
        }

        $product = HomeController::formatProduct($product, $isFav);

        //unset($product->deskripsi);
        //unset($product->cara_penyimpanan);
        //unset($product->manfaat);

        return responseArray([
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => [
                'product' => $product,
                'grade' => $grade,
                'atribut' => $atribut,
            ]
        ]);
    }

    public function search(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
            'nama' => 'required|string|min:3',
            'limit' => 'nullable|numeric',
        ]);

        $request->dc_id = $request->dc_id ? $request->dc_id : 12;

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

        $nama =  $request->nama;
        $products = Product::withMinimalDetailWhere($request->dc_id)
            ->where(function ($query) use ($request) {
                $query->where('products.title', 'like', '' . $request->nama . '%')
                    ->orWhere('products.title', 'like', '%' . $request->nama . '%');
                    // ->orWhere('products.tags', 'like', '%' . str_replace(" ","_", $request->nama). '%');
            })
            // ->orderByRaw('FIELD(products.title,"Ikan Dori Fillet 1 Kg","Bumbu Racik Indofood Ikan Goreng 20 Gram")')
            // ->orderByRaw('CASE  WHEN products.title LIKE "'.$request->nama.'%" then 1
            //                     WHEN products.tags LIKE "%'.str_replace(" ","_", $request->nama).'%" then 2
            //                     WHEN products.title LIKE "%'.$request->nama.'%" then 3
            //                     ELSE 4
            //             END')
                ->orderByRaw('CASE  WHEN products.title LIKE "'.$request->nama.'%" then 1
                        WHEN products.title LIKE "%'.$request->nama.'%" then 2
                        ELSE 3
                END')
            ->orderBy('products.title','ASC')
            ->get();
            
        // Kelengkapan
        $kelengkapanIds = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->pluck('product_id');

        $kelengkapan = Product::withMinimalDetailWhere($request->dc_id)
            ->whereIn('products.id', $kelengkapanIds)
            ->where('categories.id', 27)
            ->first();

        if ($kelengkapan) {
            $isFav = in_array($kelengkapan->product_id, $favoriteIds);
            $styrofoam = HomeController::formatProduct($kelengkapan, $isFav);
        } else {
            $styrofoam = null;
        }

        $productIds = [];
        $productContainers = [];
        foreach ($products as $product) {

            if (!in_array($product->product_id, $productIds)) {
                $productIds[] = $product->product_id;

                $isFav = in_array($product->product_id, $favoriteIds);
                $newProduct = HomeController::formatProduct($product, $isFav);
                
                if ($newProduct->category_id == 49 && $styrofoam) {
                    $newProduct->child = [$styrofoam];
                } else {
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

    public function attribute(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
            'is_attribute' => 'nullable|boolean'
        ]);

        $request->dc_id = $request->dc_id ? $request->dc_id : 12;

        $kelengkapanIds = DB::connection('mysql_cdb')
            ->table('tm_kelengkapan')
            ->pluck('product_id');

        $kelengkapan = Product::withMinimalDetailWhere($request->dc_id)
            ->whereIn('products.id', $kelengkapanIds)
            ->where('categories.id', 27)
            ->get();

        $productIds = [];
        $productContainers = [];
        foreach ($kelengkapan as $item) {
            if (!in_array($item->product_id, $productIds)) {
                $productIds[] = $item->product_id;
                $productContainers[] = HomeController::formatProduct($item);
            }
        }

        return Format::response([
            'count' => count($productContainers),
            'data' => $productContainers,
        ]);
    }
}
