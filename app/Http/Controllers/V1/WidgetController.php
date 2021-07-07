<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\Widget\Widget;
use App\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WidgetController extends Controller
{
    public function index(Request $request)
    {
        $pageId = $request->has('widget_page_id') && $request->widget_page_id
            ? $request->widget_page_id
            : 1; // Hardcoded HomePage

        $widgets = Widget::select(
                'tm_widgets.id',
                'tm_widgets.name',
                'tm_widget_types.slug',
                'tr_widget_orders.order AS position',
                'tm_widget_types.title AS tipe_widget',
                'tm_widgets.source AS content',
                'tm_widget_types.api_url AS api',
            )
            ->leftJoin('tr_widget_orders', 'tr_widget_orders.widget_id', 'tm_widgets.id')
            ->leftJoin('tm_widget_types', 'tm_widget_types.id', 'tm_widgets.type_id')
            ->active()
            ->where('tr_widget_orders.widget_page_id', $pageId)
            ->orderBy('tr_widget_orders.order')
            ->get();

        $baseUrl = env('BASE_URL_EC_API', url('api/v1/'));
        $segtmentUrl = 'widgetdetail/';

        if (substr($baseUrl, -1) != '/')
            $baseUrl .= '/';

        foreach ($widgets as $widget) {
            if ($widget->api == null && $widget->content == 1) {                
                $widget->api = $baseUrl . $segtmentUrl . $widget->id;
            }
        }

        return Format::response([
            'total' => count($widgets),
            'data' => $widgets,
        ]);
    }

    public function show(Request $request, $widgetId)
    {
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

        $widget = Widget::select('tm_widgets.*', 'tr_widget_orders.order')
            ->leftJoin('tr_widget_orders', 'tr_widget_orders.widget_id', 'tm_widgets.id')
            ->where('tr_widget_orders.widget_page_id', $pageId)
            ->with('type')
            ->with(['details' => function ($query) {
                $query->where(function ($subQ) {
                    $subQ->whereNotNull('detail_id')
                        ->where('detail_id', '!=', '');
                })->orWhere(function ($subQ) {
                    $subQ->whereNotNull('detail_name')
                        ->where('detail_name', '!=', '');
                });
            }])
            ->with(['images' => function ($query) {
                $query->whereNotNull('filename')
                    ->where('filename', '!=', '')
                    ->orderBy('id','DESC')
                    ->limit(1);
            }])
            ->findOrFail($widgetId);

        $hasProduct = count($widget->details) ? true : false;
        $hasImage = count($widget->images) ? true : false;

        $data = [
            'id' => (int) $widget->id,
            'name' => $widget->name,
            'tagline' => $widget->tagline,
            'subtagline' => $widget->subtagline,
            'slug' => $widget->type->slug,
            'position' => $widget->order,
            'tipe_widget' => $widget->type->title,
            'content' => $widget->source,
            'api' => $widget->url_button,
        ];

        if ($hasImage) {

            $response['base_url'] = env('BASE_URL_WIGDET_IMAGE', url('/') . '/');

            $images = [];

            foreach ($widget->images as $image) {
                $images[] = $image->filename;
            }

            $data['use_image'] = count($images) ? 1 : 0;
            $data['total_images'] = count($images);
            $data['images'] = $images;
        }

        if ($hasProduct) {
            
            $response['base_url_product'] = env('BASE_URL_PRODUCT');

            $products = self::fetchWidgetProducts($widget, $showAll);

            $data['use_product'] = count($products) ? 1 : 0;
            $data['total_products'] = count($products);
            $data['products'] = $products;
        }

        $response['data'] = $data;

        return Format::response($response);
    }

    public static function fetchWidgetProducts(Widget $widget, $showAll = false)
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

            $products = $products->whereIn('categories_mapping.categories_id', $mapWidgetDetailIds);

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
