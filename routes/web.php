<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
use Illuminate\Support\Facades\DB;

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) {
    $api->group(['prefix' => 'v1', 'namespace' => 'App\Http\Controllers\V1'], function ($api) {

        $api->get('version', function () {
            return response()->json(['status' => 'success', 'message' => env('APP_VERSION')], 200);
        });

        $api->get('dbTime', function () {
            $results = DB::select( "select date_format(now(),'%Y-%m-%d %H:%i:%s') db_time" );
            return response()->json(['status' => 'success', 'message' => $results[0]->db_time], 200);
        });

        // APPS
        $api->post('check_version_code', 'AppController@checkVersionCode');
        $api->post('maintenance', 'AppController@checkMaintenance');
        $api->post('phonecs', 'AppController@phoneCs');

        // AUTH
        $api->post('login', 'Auth\LoginController@login');
        $api->post('forgetpassword', 'Auth\ForgetPasswordController@resetPassword');
        $api->post('register', 'Auth\RegisterController@register');
        $api->post('activate', 'Auth\RegisterController@activateAccount');

        // HOMEPAGE
        $api->post('info', 'HomeController@notificationInfo');
        $api->post('timecoverage', 'HomeController@timeCoverage');
        $api->post('banner', 'HomeController@banners');
        $api->post('iconkategori', 'HomeController@iconCategory');
        $api->post('slideproduk', 'HomeController@productSlide');

        // PRODUCTS
        $api->post('produk', 'HomeController@productsByCategory');
        $api->post('detailproduk', 'ProductController@show');
        $api->post('cariproduk', 'ProductController@search');
        $api->post('produkattribute', 'ProductController@attribute');

        // PAYMENT
        $api->post('updatePaymentStatus', 'OrderController@paymentReceived');

        // ADDRESS
        $api->post('tipealamat', 'MemberAddressController@addressType');

        $api->group(['middleware' => 'auth'], function() use($api) {

            // PROFILE & LOGOUT
            $api->post('profil', 'AccountController@showProfile');
            $api->post('ubahpassword', 'AccountController@changePassword');
            $api->post('ubahprofil', 'AccountController@updateProfile');
            $api->post('logout', 'Auth\LoginController@logout');

            // FAVORITES
            $api->post('favorite', 'FavoriteController@index');
            $api->post('produkkategorifavorite', 'FavoriteController@indexByCategory');
            $api->post('addfavorite', 'FavoriteController@store');
            $api->post('removefavorite', 'FavoriteController@destroy');

            // SHOP
            $api->post('addcart', 'CartController@storeCart');
            $api->post('belanja', 'CartController@shop');

            // ADDRESS
            $api->post('listalamat', 'MemberAddressController@index');
            $api->post('addalamat', 'MemberAddressController@storeAddress');
            $api->post('updatealamat', 'MemberAddressController@updateAddress');
            $api->post('hapusalamat', 'MemberAddressController@destroyAddress');
            $api->post('primaryalamat', 'MemberAddressController@primaryAddress');
            $api->post('hitungjarak', 'MemberAddressController@calculateDirection');
            
            $api->post('detailalamat', 'MemberAddressController@detail');
            // ORDERS
            $api->post('listorder', 'OrderController@index');
            $api->post('detailorder', 'OrderController@show');
            $api->post('history', 'OrderHistoryController@trackingHistory');
            $api->post('submitorder', 'SubmitOrderController@submitOrder');
            $api->post('order/complete', 'OrderController@completeOrder');
            $api->post('order/cancel', 'OrderController@cancelOrder');

            // PAYMENT
            $api->post('pendingpayment', 'PaymentController@pendingPayment');

            // VOUCHERS
            $api->post('vouchercheck', 'VoucherController@checkVoucher');
            $api->post('redeemvoucher', 'VoucherController@redeemVoucher');

            // ONESIGNAL'S NOTIFICATION
            $api->post('setplayerid', 'NotificationController@setPlayerId');

                    // new
            $api->post('voucher', 'VoucherController@voucher');
            // new

        });

        // VOUCHERS
        $api->post('voucherlist', 'VoucherController@index');
        $api->post('voucherdetail', 'VoucherController@show');

        // WIDGETS
        $api->post('widgetlist', 'WidgetController@index');
        $api->post('widgetdetail/{id}', 'WidgetController@show');
        
        // RECOMEND
        $api->post('recomendlist', 'RecomendController@index');
        $api->post('recomenddetail', 'RecomendController@show');

        $api->post('splashscreen', 'GeneralController@index');
        $api->post('onboarding', 'GeneralController@onboarding');
        
        $api->post('cekongkir', 'OngkirController@index');
        // ORDER STATUS
        $api->post('order/statusmodify', 'OrderHistoryController@storeOrderHistory');

        // INBOX
        $api->post('inbox', 'NotificationController@inbox');

        // CRON
        $api->get('cron/updateorderstatus', 'OrderCronController@cancelOrders');
        $api->get('cron/updatecompleteorder', 'OrderCronController@updateCompleteOrder');

        // PAGES
        $api->post('{title}', 'PageController@getUrlPage');

    });

    $api->group(['prefix' => 'v2', 'namespace' => 'App\Http\Controllers\V2'], function ($api) {
        $api->group(['middleware' => 'auth'], function() use($api) {
            $api->post('submitorder', 'SubmitOrderController@submitOrder');
            $api->post('detailorder', 'OrderController@show');
        });
    });

    $api->group(['prefix' => 'v3', 'namespace' => 'App\Http\Controllers\V3'], function ($api) {
        $api->group(['middleware' => 'auth'], function() use($api) {
            $api->post('listorder', 'OrderController@index');
            $api->post('belanja', 'CartController@shop');
            $api->post('terimapesanan', 'FinishedOrderController@terimaPesanan');
        });
    });
});
