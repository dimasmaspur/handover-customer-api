<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/Http/Helper.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

app('translator')->setLocale('id');

$app->withFacades();
$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
|--------------------------------------------------------------------------
| Register Config Files
|--------------------------------------------------------------------------
|
| Now we will register the "app" configuration file. If the file exists in
| your configuration directory it will be loaded; otherwise, we'll load
| the default version. You may register other files below as needed.
|
*/

$app->configure('app');

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

// $app->middleware([
//     App\Http\Middleware\ExampleMiddleware::class
// ]);

$app->routeMiddleware([
    'auth' => App\Http\Middleware\Authenticate::class,
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/

$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(Dingo\Api\Provider\LumenServiceProvider::class);
// $app->register(App\Providers\EventServiceProvider::class);

$app->register(Illuminate\Mail\MailServiceProvider::class);
$app->configure('mail');
$app->alias('mail.manager', Illuminate\Mail\MailManager::class);
$app->alias('mail.manager', Illuminate\Contracts\Mail\Factory::class);
$app->alias('mailer', Illuminate\Mail\Mailer::class);
$app->alias('mailer', Illuminate\Contracts\Mail\Mailer::class);
$app->alias('mailer', Illuminate\Contracts\Mail\MailQueue::class);
/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__.'/../routes/web.php';
});

app('Dingo\Api\Exception\Handler')->register(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {

    return response()->json([
        'error' => true,
        'code' => $exception->getStatusCode(),
        'message' => 'Not Found! The specific API could not be found'
    ]);
});

app('Dingo\Api\Exception\Handler')->register(function (Symfony\Component\HttpKernel\Exception\HttpException $exception) {

    $message = 'HttpException Error';

    if ($exception->getMessage())
        $message = $exception->getMessage();
        
    if (!env('APP_DEBUG', false)) {
        switch ($exception->getStatusCode()) {
            case 400:
                $message = 'Bad Request! Your API request is invalid';
                break;
            case 401:
                $message = 'Unauthorized! Your API key is wrong';
                break;
        }
    }
    
    return response()->json([
        'error' => true,
        'code' => $exception->getStatusCode(),
        'message' => $message
    ]);
});

app('Dingo\Api\Exception\Handler')->register(function (Illuminate\Validation\ValidationException $exception) {

    return [
        'error' => true,
        'code' => 200,
        'message' => implode(', ', Arr::flatten($exception->errors())),
    ];
});

app('Dingo\Api\Exception\Handler')->register(function (Throwable $exception) {

    $message = $exception->getMessage()
        . ' in ' . $exception->getFile()
        . ' on Line ' . $exception->getLine();

    if (!env('APP_DEBUG', false)) {

        Mail::raw($message, function ($m) {
            $m->to('it.dept@kedaisayur.com');
            $m->subject('Error in end-customer-api (' . date('Y-m-d H:i:s') . ')');
        });

        $message = 'Saat ini kami sedang maintenance. Silahkan coba kembali dua jam kemudian. Mohon maaf atas ketidaknyamanannya.';
    }
    
    return response()->json([
        'error' => true,
        'code' => 500,
        'message' => $message
    ]);
});

$app['Dingo\Api\Exception\Handler']->setErrorFormat([
    'error' => true,
    'code' => ':status_code',
    'message' => ':message',
]);

return $app;
