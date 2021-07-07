<?php

namespace App\Helpers;

use GuzzleHttp\Client;

class Gmaps
{
    public static function matrixDistance($oLat, $oLng, $dLat, $dLng)
    {
        $client = new Client([
            'base_uri' => env('BASE_URL_GMAPS')
        ]);

        $response = $client->get('distancematrix/json', [ 'query' => [
            'origins' => $oLat . ',' . $oLng,
            'destinations' => $dLat . ',' . $dLng,
            'key' => env('GMAPS_KEY'),
        ]]);

        $response = json_decode($response->getBody());
        return $response->rows[0]->elements[0]->distance ? $response->rows[0]->elements[0]->distance : null;
    }
}
