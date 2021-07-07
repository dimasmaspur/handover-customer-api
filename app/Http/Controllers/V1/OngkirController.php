<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Models\Ongkir;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OngkirController extends Controller
{
    public function index(Request $request)
    {

 
        $widgets = Ongkir::select(
                'tm_delivery_fees.id',
                'tm_delivery_fees.min_order',
                'tm_delivery_fees.start_date',
                'tm_delivery_fees.end_date',
                'tm_delivery_fees.delivery_fee AS ongkir',
            )
            // ->where('status',1)
            ->whereRaw('start_date <= NOW()')
            ->whereRaw('end_date >= NOW()')
            ->where('deleted_at',null)
            ->orderBy('tm_delivery_fees.min_order','asc')
            ->get();

            

        return Format::response([
            'data' => $widgets
        ]);
    }
}
