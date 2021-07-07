<?php

if (!function_exists('responseSuccess')) {

    function responseSuccess($data = null, $status_code = 200, $toArray = false) {

        $result['error'] = false;
        $result['code'] = $status_code;
        $result['message'] = 'Success';

        if ($status_code >= 400) {
            $result['error'] = true;
        }

        if (isset($data['message']) && !empty($data['message'])) {
            $result['message'] = $data['message'];
            unset($data['message']);
        }

        if ($data) {
            $result['data'] = !is_array($data) && $toArray ? [$data] : $data;
        }

        return response()->json($result)
            ->setStatusCode($status_code);
    }
}

if (!function_exists('responseArray')) {

    function responseArray($data = null, $status_code = 200, $error = false) {

        $result['error'] = $error;
        $result['code'] = $status_code;
        $result['message'] = 'Success';

        if ($status_code >= 400) {
            $result['error'] = true;
        }

        if (isset($data['message']) && !empty($data['message'])) {
            $result['message'] = $data['message'];
            unset($data['message']);
        }

        $result = array_merge($result, $data);

        return response()->json($result)
            ->setStatusCode($status_code);
    }
}