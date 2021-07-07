<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function getUrlPage($title)
    {
        switch ($title) {
            case 'faq':
                $id = 16;
                break;
            case 'pemberitahuan':
                $id = 17;
                break;
            case 'about':
                $id = 19;
                break;
            case 'syarat':
                $id = 5;
                break;
            case 'bantuan':
                $id = 16;
                break;
            default:
                $id = $title;
                break;
        }

        $page = DB::connection('mysql_cdb')
            ->table('pages')
            ->select('url_title')
            ->where('id', $id)
            ->first();

        if (!$page) {
            abort(404, 'Not Found! The specific API could not be found.');
        }

        return Format::response([
            'data' => [
                'url' => env('BASE_URL_KS') . $page->url_title
            ]
        ]);
    }
}
