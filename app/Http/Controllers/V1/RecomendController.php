<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\Widget\Widget;
use App\Models\Recomend\Recomend;
use App\Product\Category;
use App\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecomendController extends Controller
{
    public function index(Request $request)
    {
        $pageId = $request->has('widget_page_id') && $request->widget_page_id
            ? $request->widget_page_id
            : 1; // Hardcoded HomePage

        $widgets = Recomend::select(
                'tm_recomend.id',
                'tm_recomend.name',
                'tm_recomend.start_date',
                'tm_recomend.end_date',
                'detail_type'
            )
                        
            ->whereRaw('start_date <= NOW()')
            ->whereRaw('end_date >= NOW()')            
            ->where('status',1)
            ->where('deleted_at',null)
            ->get();

        $baseUrl = env('BASE_URL_EC_API', url('api/v1/'));
        $segtmentUrl = 'recomenddetail/';

        if (substr($baseUrl, -1) != '/')
            $baseUrl .= '/';

        foreach ($widgets as $widget) {
            if ($widget->api == null && $widget->content == 1) {                
                $widget->api = $baseUrl . $segtmentUrl . $widget->id;
            }
        }

        $baseUrlRecomend = env('BASE_URL', url('ec-api/api/v1/'));

        foreach ($widgets as $datawidget) {
            if($datawidget->detail_type == 1){
                $widget['type'] = 'product';
            }elseif($datawidget->detail_type == 2){
                $widget['type'] = 'category';
            }elseif($datawidget->detail_type == 3){
                $widget['type'] = 'tags';
            }
        }

        return Format::response([
            'total' => count($widgets),
            'base_url_recomend'=> $baseUrlRecomend,
            'data' => $widgets,
        ]);
    }

    public function show(Request $request)
    {
        $category = $request->category_id;

        // var_dump($category);die();
        $tags = $request->tags;
        // $isNull = $request->isNull;

        $this->validate($request, [
            // 'widget_id' => 'required|exists:tm_widgets,id',
            'show_all' => 'nullable|numeric'
        ]);

        // $widgetId = $request->widget_id;
        $pageId = $request->has('widget_page_id') && $request->widget_page_id
            ? $request->widget_page_id
            : 1; // Hardcoded HomePage
        $showAll = $request->has('show_all') && $request->show_all == 0
            ? false
            : true;

        $widget = Recomend::select('tm_recomend.*')
            ->with(['details' => function ($query) {
                $query->where(function ($subQ) {
                    $subQ->whereNotNull('detail_id')
                        ->where('detail_id', '!=', '');
                })->orWhere(function ($subQ) {
                    $subQ->whereNotNull('detail_name')
                        ->where('detail_name', '!=', '');
                });
            }])
            // ->findOrFail($widgetId);
            ->first();

            $datawidget = Recomend::select(
                'tm_recomend.id',
                'tm_recomend.name',
                'tm_recomend.start_date',
                'tm_recomend.end_date',
                'detail_type'
            )
                        
            ->whereRaw('start_date <= NOW()')
            ->whereRaw('end_date >= NOW()')            
            ->where('status',1)
            ->where('deleted_at',null)
            ->get();
            if(count($datawidget) > 0){
                if($widget){
                    $hasProduct = count($widget->details) ? true : false;
                
        
                $data = [
                    'id' => (int) $widget->id,
                    'name' => $widget->name,
                ];
               
        
                if ($hasProduct) {
                    
                    $response['base_url_product'] = env('BASE_URL_PRODUCT');
        
                    $products = self::fetchWidgetProducts($widget, $showAll, $category, $tags);
                    $data['products'] = $products;
                }
        
                $response['data'] = $data;
        
                return Format::response($response);
                }else{
                    $data = [
                        'products' =>[]
                    ];
                $response['data'] = $data;
        
                return Format::response($response);
                }
            }else{
                $data = [
                    'products' =>[]
                ];
            $response['data'] = $data;
    
            return Format::response($response);
            }

            die();
       
    }

    public static function fetchWidgetProducts(Recomend $widget, $showAll = false, $category = null, $tags = null)
    {
        $mapWidgetDetailIds = [];
        $mapWidgetDetailNames = [];

        foreach ($widget->details as $item) {
            if ($item->detail_id) {
                $mapWidgetDetailIds[] = $item->detail_id;
            }
            if ($item->detail_name) {
                $mapWidgetDetailNames[] = $item->detail_name;
            }
        }

        $products = Product::withMinimalDetailWhere(12);

        if ($widget->detail_type == 1) {
            
            $products = $products->whereIn('products.id', $mapWidgetDetailIds);

        } elseif ($widget->detail_type == 2) {

            $products = $products->whereIn('categories_mapping.categories_id', $mapWidgetDetailIds)
            ->where('categories_mapping.categories_id',$category);

        } elseif ($widget->detail_type == 3 && count($mapWidgetDetailNames)) {

            $products = $products->where(function ($query) use ($mapWidgetDetailNames) {
                $query->where('products.tags', 'like', '%' . $mapWidgetDetailNames[0] .'%');
                unset($mapWidgetDetailNames[0]);
    
                if (count($mapWidgetDetailNames)) {
                    foreach ($mapWidgetDetailNames as $name) {
                        $query->orWhere('products.tags', 'like', '%' . $name .'%');
                    }
                }
            });
        }

        if (!$showAll) {
            $products = $products->limit(10);
        }
        
        $products = $products->orderBy('products.title')->get();

        $productIds = [];
        $favoriteIds = [];
        $productContainer = [];

        if (Auth::check()) {
            $usersfavoriteIds = Auth::user()
                ->favorites()
                ->active()
                ->get();

            if (count($usersfavoriteIds)) {
                foreach ($usersfavoriteIds as $item) {
                    $favoriteIds[] = $item->product_id;
                }
            }
        }

        foreach ($products as $product) {
            if (!in_array($product->product_id, $productIds)) {
                
                $productIds[] = $product->product_id;
                $isFav = in_array($product->product_id, $favoriteIds);
                
                $newProduct = HomeController::formatProduct($product, $isFav);
                $newProduct->child = [];                
                $productContainer[] = $newProduct;
            }
        }

       
        return $productContainer;
       
    }
}
