<?php

namespace App\Helpers;

class Format
{
    public static function phoneNumber($phone)
    {
        $phone = preg_replace("/\D/", "", $phone);

        return substr($phone,0,1) == "0"
            ? "62" . substr($phone,1)
            : $phone;
    }

    public static function castPhoneNumber($phone)
    {
        return substr($phone, 0, 2) == "62"
            ? "0" . substr($phone, 2)
            : $phone;
    }

    public static function rupiah($rupiah)
    {
        return "Rp " . number_format($rupiah, 0, ',', '.');
    }

    public static function cleanCity($string)
    {
        return trim(preg_replace("/(^[Kk]ota\s)|(\s*$)/", '', $string));
    }

    public static function cleanDistric($string)
    {
        return trim(preg_replace("/(^[Kk]ec\.*\s)|(^[Kk]ecamatan\s)|(\s*$)/", '', $string));
    }

    public static function cleanSpecialChar($string)
    {
        return trim(preg_replace("/[^A-Za-z\d\s\.\-\,]/", '', $string));
    }

    public static function response($data, $error = false, $code = 200)
    {
        $response = [
            'error' => $error,
            'code' => $code,
            'message' => 'Success',
        ];

        return response()->json(array_merge($response, $data), $code);
    }
}
