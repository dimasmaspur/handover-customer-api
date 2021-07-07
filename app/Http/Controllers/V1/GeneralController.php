<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\Widget\Widget;
use App\Models\Splashscreen;
use App\Product\Category;
use App\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GeneralController extends Controller
{
    public function index(Request $request)
    {
        $splash = Splashscreen::select(
            'tm_splashscreen.*'
        )
        ->orderBy('id','desc')
        ->limit(1)
        ->first();
 
        if($splash){
            $data = [
                'id' => $splash->id,
                'image' => $splash->image,
                'color' => $splash->color,
                'created_at' => $splash->created_at,
                'updated_at' => $splash->updated_at,
            ];
            return Format::response([
                'base_url' => env('BASE_URL_CMS').env('SPLASHSCREEN'),
                'data' => $data
            ]);
        }else{
            $data = [
                'id' => null,
                'image' => null,
                'color' => null,
                'created_at' => null,
                'updated_at' => null,
            ];
            return Format::response([
                'base_url' => env('BASE_URL_CMS').env('SPLASHSCREEN'),
                'data' => $data
            ]);
        }
    }
    public function onboarding(Request $request)
    {
        $screen1 = Onboarding::select(
            'tm_onboarding.*'
        )
        ->where('type',1)
        ->orderBy('id','desc')
        ->limit(1)
        ->first();

        $screen2 = Onboarding::select(
            'tm_onboarding.*'
        )
        ->where('type',2)
        ->orderBy('id','desc')
        ->limit(1)
        ->first();
        
        $screen3 = Onboarding::select(
            'tm_onboarding.*'
        )
        ->where('type',3)
        ->orderBy('id','desc')
        ->limit(1)
        ->first();

        $data = [];
        $data = [
            $screen1,
            $screen2,
            $screen3,
        ];

        return Format::response([
            'base_url' => env('BASE_URL_CMS').env('SPLASHSCREEN'),
            'data' => $data 
        ]);
    }

   
}
