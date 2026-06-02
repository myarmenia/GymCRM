<?php

namespace App\Helpers;

use App\Models\Client;
use App\Models\ClientSchedule;
use App\Models\Department;
use App\Models\ScheduleName;
use App\Models\Staff;
use App\Models\Superviced;
use Illuminate\Support\Facades\Auth;

class MyHelper
{
    public static function binaryToDecimal($binaryString)
    {
    //    dd($binaryString);
        $substring = substr($binaryString, 9, 16); // Индексы начинаются с 0, поэтому берем с 9 символа и длиной 16
// dd($substring);
        // Преобразуем бинарную подстроку в десятичное число
        $decimal = bindec($substring);
// dd($decimal);
        return $decimal;
    }
    public static function week_days(){

        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday','Friday','Saturday','Sunday'];

        return $weekdays;
    }


    // public static function find_auth_user_client(){

    //     if(auth()->user()->hasRole(['client_admin','client_admin_rfID'])){

    //         $client_id = Client::where('user_id',Auth::id())->value('id');
    //     }
    //     else{
    //         $client_id = Staff::where('user_id',Auth::id())->value('client_admin_id');

    //     }
    //     return $client_id;

    // }

    public static function find_auth_user_client()
    {
        // 1. Если пришёл внешний запрос (из middleware)
        if (request()->has('client_id')) {
            return request()->get('client_id');
        }

        // 2. Если нет авторизации вообще
        if (!auth()->check()) {
            return null;
        }

        // 3. Старая логика (НЕ ТРОГАЕМ)
        if (auth()->user()->hasRole(['client_admin', 'client_admin_rfID'])) {

            return Client::where('user_id', auth()->id())->value('id');

        } else {

            return Staff::where('user_id', auth()->id())->value('client_admin_id');
        }
    }
    public  static function absence_type(){

        return ['Հիվանդ','Գործուղում','Արձակուրդ'];

    }
    public static function get_client_department(){
        return Department::where('client_id',self::find_auth_user_client())->get();
    }
    public  static function get_client_schedule(){

        return ClientSchedule::where('client_id',self::find_auth_user_client())->with('schedule_name.schedule_details')->get();

    }


}
