<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use App\Order\Order;
use App\Order\Voucher;
use App\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $this->validate($request, [
            'page' => 'nullable|numeric',
            'per_page' => 'nullable|numeric',
            'keyword' => 'nullable|string',
            'filter' => 'nullable',
            'date_filter.*.start' => 'nullable|date', 
            'date_filter.*.end' => 'nullable|date',
            'sort' => 'nullable|string',
            'sort_by' => 'nullable|string',
        ]);

        // $perPage = $request->has('per_page') ? $request->per_page : 10;
        // $sort = $request->has('sort') ? $request->sort : 'id';
        // $sortBy = $request->has('sort_by') ? $request->sort_by : 'desc';

        $data = Voucher::select(
                    'tm_voucher.*', 
                    'is_new_customer AS new_member',
                    'show_code AS using_voucher_code',
                )
                ->publish();
                /* ->where('end_date', '>=', Carbon::now())
                ->where('status', 1)
                ->where('publish', 1); */

        // check if there's any keyword
        if ($request->has('keyword') && ($request->keyword != null ||$request->keyword != '')) {
            $data = $data->where(function ($query) use ($request) {
                $query->where('code', 'like', '%' . $request->keyword .'%')
                    ->orWhere('title', 'like', '%' . $request->keyword .'%');
            });
        }

        // check if there are any filters
        if ($request->has('filter') && ($request->filter != null ||$request->filter != '')) {
            foreach ($request->filter as $columnName => $value) {
                if (is_array($value)) {
                    $data = $data->whereIn($columnName, $value);
                } else {
                    $data = $data->where($columnName, $value);
                }
            }
        }

        // check if there are any date filters
        if ($request->has('date_filter') && ($request->date_filter != null ||$request->date_filter != '')) {
            foreach ($request->date_filter as $columnName => $value) {
                $data = $data->whereBetween($columnName, $value);
            }
        }

        // sort the result
        $data = $data->orderBy('end_date');
        $data = $data->orderBy('start_date');
        // $data = $data->orderBy($sort, $sortBy);

        // get the result
        $totalRecord = $data->count();
        // $vouchers = $data->paginate($perPage);
        $vouchers = $data->get();

        // clean laravel's format
        $dataContainer = [];

        foreach ($vouchers as $voucher) {
            unset($voucher->is_new_customer);
            unset($voucher->show_code);
            unset($voucher->deleted_at);
            unset($voucher->deleted_by);
            
            $startDate = new Carbon($voucher->start_date);
            $endDate = new Carbon($voucher->end_date);

            $startDate->locale('id_ID');
            $endDate->locale('id_ID');
            
            $voucher->start_date = $startDate->translatedFormat('j M y');
            $voucher->end_date = $endDate->translatedFormat('j M y');
            $dataContainer[] = $voucher;
        }

        // $lastPage = ceil($totalRecord / $perPage);

        return Format::response([
            'count' => $totalRecord,
            'current_page' => $request->has('page') ? $request->page : 1,
            // 'last_page' => $lastPage,
            'last_page' => 0,
            'base_url' => env('BASE_URL_VOUCHER', null),
            'data' => $dataContainer
        ]);
    }

    public function show(Request $request)
    {
        $this->validate($request, [
            'voucher_code' => 'required|exists:App\Order\Voucher,code',
        ], [], [
            'voucher_code' => 'Kode Voucher'
        ]);

        $voucher = Voucher::select(
                'tm_voucher.*', 
                'is_new_customer AS new_member',
                'show_code AS using_voucher_code',
            )
            ->where('code', $request->voucher_code)
            ->first();
        
        if (!$voucher) {
            abort(200, 'Voucher sudah tidak aktif.');
        }

        unset($voucher->is_new_customer);
        unset($voucher->show_code);

        $startDate = new Carbon($voucher->start_date);
        $endDate = new Carbon($voucher->end_date);

        $startDate->locale('id_ID');
        $endDate->locale('id_ID');
        
        $voucher->start_date = $startDate->translatedFormat('j M y');
        $voucher->end_date = $endDate->translatedFormat('j M y');

        return Format::response([
            'base_url' => env('BASE_URL_VOUCHER', null),
            'data' => $voucher
        ], $voucher ? false : true);
    }

    public function checkVoucher(Request $request)
    {
        $this->validate($request, [
            'voucher_code' => 'required',
        ], [], [
            'voucher_code' => 'Kode Voucher'
        ]);

        // $perPage = $request->has('per_page') ? $request->per_page : 10;
        // $sort = $request->has('sort') ? $request->sort : 'id';
        // $sortBy = $request->has('sort_by') ? $request->sort_by : 'desc';


        $data = Voucher::select(
                    'tm_voucher.*', 
                    'is_new_customer AS new_member',
                    'show_code AS using_voucher_code',
                    'tm_voucher_image.filename AS image_voucher',
                )
                ->where('code',$request->voucher_code)
                // ->where('show_code',1)
                ->where('status', 1)
                ->where('end_date', '>=', Carbon::now())
                ->leftJoin('tm_voucher_image', 'tm_voucher_image.voucher_id', 'tm_voucher.id')
                ->orderBy('tm_voucher_image.id','DESC')
                ;
                // ->publish();
                /* ->where('end_date', '>=', Carbon::now())
                ->where('publish', 1); */



        // check if there's any keyword
        if ($request->has('keyword') && ($request->keyword != null ||$request->keyword != '')) {
            $data = $data->where(function ($query) use ($request) {
                $query->where('code', 'like', '%' . $request->keyword .'%')
                    ->orWhere('title', 'like', '%' . $request->keyword .'%');
            });
        }

        // check if there are any filters
        if ($request->has('filter') && ($request->filter != null ||$request->filter != '')) {
            foreach ($request->filter as $columnName => $value) {
                if (is_array($value)) {
                    $data = $data->whereIn($columnName, $value);
                } else {
                    $data = $data->where($columnName, $value);
                }
            }
        }

        // check if there are any date filters
        if ($request->has('date_filter') && ($request->date_filter != null ||$request->date_filter != '')) {
            foreach ($request->date_filter as $columnName => $value) {
                $data = $data->whereBetween($columnName, $value);
            }
        }

        // sort the result
        $data = $data->orderBy('end_date');
        $data = $data->orderBy('start_date');
        // $data = $data->orderBy($sort, $sortBy);

        // get the result
        $totalRecord = $data->count();
        // $vouchers = $data->paginate($perPage);
        $vouchers = $data->get();

        // clean laravel's format
        $dataContainer = [];
        
       
        foreach ($vouchers as $voucher) {
            unset($voucher->is_new_customer);
            unset($voucher->show_code);
            unset($voucher->deleted_at);
            unset($voucher->deleted_by);

            $usedVoucherTimes = Order::select('id')
            ->where('member_id', Auth::id())
            ->where('promo_code', $voucher->code)
            ->where('order_status_code', '!=', 'OS00')
            /* ->whereHas('histories', function (Builder $query) {
                $query->whereIn('order_status_code', ['OS17', 'OS18', 'OS05', 'OS03', 'OS12']);
            }) */
            
            ->count();
    
    
            if ($usedVoucherTimes >= $voucher->maks_penggunaan) {
                $voucher['status_used'] = 1;
            }else{
                $voucher['status_used'] = 0;
            }
            
            $startDate = new Carbon($voucher->start_date);
            $endDate = new Carbon($voucher->end_date);

            $startDate->locale('id_ID');
            $endDate->locale('id_ID');

            $dateNow = new DateTime(date("Y-m-d H:i:s"));
            $finalDate = new DateTime($voucher->end_date);
            $remaining = $dateNow->diff($finalDate);

            $voucher->start_date = $startDate->translatedFormat('Ymd H:i:s');
            $voucher->end_date = $endDate->translatedFormat('Ymd H:i:s');
            $voucher['count_day'] = $remaining->days;

            $dataContainer[] = $voucher;
        }

        // $lastPage = ceil($totalRecord / $perPage);

        return Format::response([
            'count' => $totalRecord,
            'current_page' => $request->has('page') ? $request->page : 1,
            // 'last_page' => $lastPage,
            'last_page' => 0,
            'base_url' => env('BASE_URL_VOUCHER', null),
            'data' => $dataContainer
        ]);
    }

    private static function calculateDiscount($voucher, $nominalOrder)
    {
        $discount = 0;

        if ($voucher->is_percentage) {
            $discount = $nominalOrder * ($voucher->nilai / 100);

            if ($discount > $voucher->maks_potongan) $discount = $voucher->maks_potongan;

        } else {
            $discount = $voucher->nilai;
        }

        return $discount;
    }

    public function voucher(Request $request)
    {
        $this->validate($request, [
            'page' => 'nullable|numeric',
            'per_page' => 'nullable|numeric',
            'keyword' => 'nullable|string',
            'filter' => 'nullable',
            'date_filter.*.start' => 'nullable|date', 
            'date_filter.*.end' => 'nullable|date',
            'sort' => 'nullable|string',
            'sort_by' => 'nullable|string',
        ]);

        // $perPage = $request->has('per_page') ? $request->per_page : 10;
        // $sort = $request->has('sort') ? $request->sort : 'id';
        // $sortBy = $request->has('sort_by') ? $request->sort_by : 'desc';

        $data = Voucher::select(
                    'tm_voucher.*', 
                    'is_new_customer AS new_member',
                    'show_code AS using_voucher_code',
                    'tm_voucher_image.filename AS image_voucher',
                )
                ->where('show_code',1)
                ->where('status', 1)
                ->leftJoin('tm_voucher_image', 'tm_voucher_image.voucher_id', 'tm_voucher.id')
                ->publish();
                // ->where('publish', 1); */

        // check if there's any keyword
        if ($request->has('keyword') && ($request->keyword != null ||$request->keyword != '')) {
            $data = $data->where(function ($query) use ($request) {
                $query->where('code', 'like', '%' . $request->keyword .'%')
                    ->orWhere('title', 'like', '%' . $request->keyword .'%');
            });
        }

        // check if there are any filters
        if ($request->has('filter') && ($request->filter != null ||$request->filter != '')) {
            foreach ($request->filter as $columnName => $value) {
                if (is_array($value)) {
                    $data = $data->whereIn($columnName, $value);
                } else {
                    $data = $data->where($columnName, $value);
                }
            }
        }

        // check if there are any date filters
        if ($request->has('date_filter') && ($request->date_filter != null ||$request->date_filter != '')) {
            foreach ($request->date_filter as $columnName => $value) {
                $data = $data->whereBetween($columnName, $value);
            }
        }

        // sort the result
        $data = $data->orderBy('end_date');
        $data = $data->orderBy('start_date');
        // $data = $data->orderBy($sort, $sortBy);

        // get the result
        $totalRecord = $data->count();
        // $vouchers = $data->paginate($perPage);
        $vouchers = $data->get();

        // clean laravel's format
        $dataContainer = [];

       

        foreach ($vouchers as $voucher) {
            unset($voucher->is_new_customer);
            unset($voucher->show_code);
            unset($voucher->deleted_at);
            unset($voucher->deleted_by);

            $usedVoucherTimes = Order::select('id')
            ->where('member_id', Auth::id())
            ->where('promo_code', $voucher->code)
            ->where('order_status_code', '!=', 'OS00')
            /* ->whereHas('histories', function (Builder $query) {
                $query->whereIn('order_status_code', ['OS17', 'OS18', 'OS05', 'OS03', 'OS12']);
            }) */
            
            ->count();
    
    
            if ($usedVoucherTimes >= $voucher->maks_penggunaan) {
                $voucher['status_used'] = 1;
            }else{
                $voucher['status_used'] = 0;
            }
            
            $startDate = new Carbon($voucher->start_date);
            $endDate = new Carbon($voucher->end_date);

            $startDate->locale('id_ID');
            $endDate->locale('id_ID');

            $dateNow = new DateTime(date("Y-m-d H:i:s"));
            $finalDate = new DateTime($voucher->end_date);
            $remaining = $dateNow->diff($finalDate);

            $voucher->start_date = $startDate->translatedFormat('Ymd H:i:s');
            $voucher->end_date = $endDate->translatedFormat('Ymd H:i:s');
            $voucher['count_day'] = $remaining->days;

            $dataContainer[] = $voucher;
        }

        // $lastPage = ceil($totalRecord / $perPage);

        return Format::response([
            'count' => $totalRecord,
            'current_page' => $request->has('page') ? $request->page : 1,
            // 'last_page' => $lastPage,
            'last_page' => 0,
            'base_url' => env('BASE_URL_VOUCHER', null),
            'data' => $dataContainer
        ]);
    }
    public function redeemVoucher(Request $request)
    {
        $this->validate($request, [
            'voucher_code' => 'required|exists:App\Order\Voucher,code',
            'nominal_order' => 'required|numeric',
        ], [], [
            'voucher_code' => 'Kode Voucher'
        ]);
        

        $voucher = Voucher::where('code', $request->voucher_code)
            // ->where('status', 1)
            // ->where('publish', 1)
            ->active()
            ->first();

        if ($voucher) {

            $now = Carbon::now();

            // check voucher active date
            if ($now->lt($voucher->start_date)) {
                abort(200, 'Promo voucher belum dimulai.');
            } elseif ($now->gt($voucher->end_date)) {

                $voucherEndDate = new Carbon($voucher->end_date);
                $voucherEndDate->locale('id_ID');

                $endDate = $voucherEndDate->translatedFormat('j F Y');
                $endTime = $voucherEndDate->translatedFormat('H:i');
                
                abort(200, "Promo voucher telah berakhir pada $endDate pukul $endTime.");
            }

            // check if this voucher has reached its usage limit
            if ($voucher->pemakaian_quota >= $voucher->jumlah_quota) {
                abort(200, 'Batas penggunaan voucher telah tercapai.');
            }

            // check minimum order
            if ($request->nominal_order < $voucher->min_order) {
                abort(200, "Minimal pembelian " . Format::rupiah($voucher->min_order));
            }

            // Check new member only
            if ($voucher->is_new_customer) {
                $validOrderCount = Order::select('id')
                    ->where('member_id', Auth::id())
                    ->whereHas('histories', function (Builder $query) {
                        $query->whereIn('order_status_code', ['OS17', 'OS18', 'OS05', 'OS03', 'OS12']);
                    })
                    ->count();

                if ($validOrderCount > 0)
                    abort(200, 'Promo voucher hanya berlaku untuk member baru.');
            }

            // Check if the member has used this voucher code before
            $usedVoucherTimes = Order::select('id')
                ->where('member_id', Auth::id())
                ->where('promo_code', $voucher->code)
                ->where('order_status_code', '!=', 'OS00')
                /* ->whereHas('histories', function (Builder $query) {
                    $query->whereIn('order_status_code', ['OS17', 'OS18', 'OS05', 'OS03', 'OS12']);
                }) */
                
                ->count();


            if ($usedVoucherTimes >= $voucher->maks_penggunaan) {
                abort(200, 'Batas penggunaan promo voucher Anda telah tercapai.');
            }

            // Check by Category

            // Calculate Discount
            $discount = self::calculateDiscount($voucher, $request->nominal_order);

            return Format::response([
                'nominal_voucher' => (float) $discount,
                'voucher' => $voucher,
            ]);

        } else {
            abort(200, 'Kode Voucher tidak ditemukan.');
        }
    }

}
