<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;


class BaseController extends Controller
{
    public function sendResponse($result = null, $message = '', $additionals = null, $params = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];

        // Դեպք՝ response-ի հետ կան լրացուցիչ պարամետրեր, որոնք պետք է վերադարձվեն `params` դաշտում։
        if ($params != null) {
            $response['params'] = $params;
        }

        // Դեպք՝ պետք է response-ի root մակարդակում ավելացնել լրացուցիչ դաշտեր (օր.՝ pagination)։
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
    public function sendError($error, $additionals = null, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        // Դեպք՝ error response-ի հետ էլ պետք է վերադարձնել լրացուցիչ դաշտեր։
        if ($additionals != null) {
            foreach ($additionals as $key => $value) {
                $response[$key] = $value;
            }
        }

        // Դեպք՝ առկա են մանրամասն error-ներ (օր.՝ վավերացման սխալներ), որոնք պետք է փոխանցվեն `data` դաշտում։
        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }


        return response()->json($response, $code);
    }
}
