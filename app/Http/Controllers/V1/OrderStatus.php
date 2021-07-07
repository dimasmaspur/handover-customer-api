<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class OrderStatus extends Controller
{
    public $orderStatusCodes;

    public function __construct()
    {
        $response = Http::get(env('BASE_URL_MIGRATE') . 'master/status/get', [
            'select' => 'id,code,order_status_title',
            'sort' => 'code,asc'
        ])->json();

        if ($response['data']) {

            /* foreach ($response['data'] as $code) {

                $constantKey = str_replace(' ', '', $code['order_status_title']);
                $constantKey = strtoupper($constantKey);

                $this->orderStatusCodes[$constantKey]['key'] = $constantKey;
                $this->orderStatusCodes[$constantKey]['header'] = trim(ucwords($code['order_status_title']));
                $this->orderStatusCodes[$constantKey]['order_status_code'][] = $code['code'];
            }

            $orderKey = [
                'PENDING',
                'MENUNGGUPEMBAYARAN',
                'PEMBAYARANDITERIMA',
                'MENUNGGUDIPROSES',
                'SEDANGDIPROSES',
                'SEDANGDIKIRIM',
                'SELESAI',
                'DIBATALKAN'
            ];

            $newOrderStatus = [];

            foreach ($orderKey as $key) {
                $newOrderStatus[$key] = $this->orderStatusCodes[$key];
            }

            $this->orderStatusCodes = $newOrderStatus; */

            $this->orderStatusCodes = $response['data'];

        } else {

            $this->orderStatusCodes = null;
        }
    }

    public function searchStatusCode($statusCode)
    {
        $result = null;

        if (is_array($this->orderStatusCodes)) {

            foreach ($this->orderStatusCodes as $code) {

                /* $searchKey = array_search($statusCode, $code['order_status_code']);

                if ($searchKey !== false) {
                    $result = $code;
                } */

                if ($code['code'] == $statusCode) {
                    $result = $code;
                    break;
                }
            }
        }

        return $result;
    }
}
