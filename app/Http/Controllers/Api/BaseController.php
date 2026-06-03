<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use lluminate\Http\JsonResponse;


class BaseController extends Controller
{
    public function sendResponse($result = null, $message, $additionals = null, $params = null)
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];

        if ($params != null) {
            $response['params'] = $params;
        }

        if ($additionals != null) {
            foreach ($additionals as $key => $value) {
                $response[$key] = $value;
            }
        }

        return response()->json($response, 200);
    }


    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error,  $additionals = null, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if ($additionals != null) {
            foreach ($additionals as $key => $value) {
                $response[$key] = $value;
            }
        }

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }


        return response()->json($response, $code);
    }
}
