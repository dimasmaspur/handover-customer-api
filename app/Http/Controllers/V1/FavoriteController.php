<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Member\Favorite;
use App\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $this->validate($request, [
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
            'is_grouped' => 'nullable|boolean',
        ]);

        $dcId = $request->has('dc_id') && ($request->dc_id != null ||$request->dc_id != '')
            ? $request->dc_id 
            : 12;

        $isGrouped = $request->has('is_grouped') && ($request->is_grouped != null ||$request->is_grouped != '')
            ? $request->is_grouped 
            : 1;

        $favoriteIds = Auth::user()
            ->favorites()
            ->select('product_id')
            ->active()
            ->get()
            ->pluck('product_id')
            ->toArray();

        $result = [];
        $productContainers = [];

        if (count($favoriteIds)) {

            $products = Product::withMinimalDetailWhere($dcId)
                ->whereIn('products.id', $favoriteIds)
                ->orderBy('categories_mapping.level')
                ->orderBy('products.title')
                ->groupBy('products.id')
                ->get();


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

            // $productIds = [];

            foreach ($products as $product) {

                /* if (!in_array($product->product_id, $productIds)) {
                    $productIds[] = $product->product_id;

                    $newProduct = HomeController::formatProduct($product, true);
                    if ($newProduct->category_id == 49 && $styrofoam) {
                        $newProduct->child = [$styrofoam];
                    } else {
                        $newProduct->child = [];
                    }
                    $productContainers[] = $newProduct;

                } */

                $newProduct = HomeController::formatProduct($product, true);
                if ($newProduct->category_id == 49 && $styrofoam) {
                    $newProduct->child = [$styrofoam];
                } else {
                    $newProduct->child = [];
                }
                $productContainers[] = $newProduct;
            }


            // usort($productContainers, function ($item1, $item2) {
            //     $discount1 = (int) str_replace('%', '', $item1->discount);
            //     $discount2 = (int) str_replace('%', '', $item2->discount);
            //     return $discount2 <=> $discount1;
            // });

            if ($isGrouped) {
            
                foreach ($productContainers as $product) {
                    $result[$product->category_id]['id'] = (int) $product->category_id;
                    $result[$product->category_id]['title'] = $product->category;
                    $result[$product->category_id]['items'][] = $product;
                    
                    // if (!isset($result[$product->category_id]['items'])
                    //     || count($result[$product->category_id]['items']) < 10) {
                    //     $result[$product->category_id]['items'][] = $product;
                    // }
                }

                foreach ($result as $key1 => $category) {
                    $items = $category['items'];

                    usort($items, function ($item1, $item2) {
                        $discount1 = (int) str_replace('%', '', $item1->discount);
                        $discount2 = (int) str_replace('%', '', $item2->discount);
                        return $discount2 <=> $discount1;
                    });

                    $result[$key1]['items'] = $items;

                    foreach ($result[$key1]['items'] as $key2 => $product) {
                        if ($key2 >= 10) {
                            unset($result[$key1]['items'][$key2]);
                        }
                    }
                }
        
                $result = array_values($result);
            } else {
                $result = $productContainers;
            }
        }

        return responseArray([
            'count' => count($productContainers),
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => $result
        ]);
    }
    
    public function indexByCategory(Request $request)
    {
        $this->validate($request, [
            'kategori_id' => 'required|numeric',
            'dc_id' => 'nullable|exists:App\Wilayah\Wilayah,id',
        ]);

        $categoryId = $request->kategori_id;
        $dcId = $request->has('dc_id') && ($request->dc_id != null ||$request->dc_id != '')
            ? $request->dc_id 
            : 12;

        $favoriteIds = Auth::user()
            ->favorites()
            ->select('product_id')
            ->active()
            ->get()
            ->pluck('product_id')
            ->toArray();

        if (count($favoriteIds)) {

            $products = Product::withMinimalDetailWhere($dcId)
                ->where('categories_mapping.categories_id', $categoryId)
                ->whereIn('products.id', $favoriteIds)
                ->orderBy('products.title')
                ->get();

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

            $productIds = [];
            $productContainers = [];
            // $result = [];
            foreach ($products as $product) {

                if (!in_array($product->product_id, $productIds)) {
                    $productIds[] = $product->product_id;

                    $newProduct = HomeController::formatProduct($product, true);
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

        } else {

            $productContainers = [];
        }

        return responseArray([
            'count' => count($productContainers),
            'base_url' => env('BASE_URL_PRODUCT'),
            'data' => $productContainers
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'product_id' => 'required|exists:App\Product\Product,id'
        ]);

        $store = Favorite::updateOrCreate([
            'member_id' => Auth::id(),
            'product_id' => $request->product_id,
        ], [
            'status' => 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return responseArray([
            'message' => $store ? 'Berhasil ditambahkan ke daftar favorite' : 'Gagal menambahkan produk ke daftar favorite'
        ]);
    }
    
    public function destroy(Request $request)
    {
        $this->validate($request, [
            'product_id' => 'required|exists:App\Product\Product,id'
        ]);

        $destroy = Favorite::where([
            'member_id' => Auth::id(),
            'product_id' => $request->product_id,
        ])->update([
            'status' => 0
        ]);

        return responseArray([
            'message' => $destroy ? 'Berhasil menghapus produk dari daftar favorite' : 'Gagal menghapus produk dari daftar favorite'
        ]);
    }
}
