<?php

namespace App\Helpers;

use App\Models\Client;
use App\Models\ClientSchedule;
use App\Models\Department;
use App\Models\Gym;
use App\Models\ScheduleName;
use App\Models\Staff;
use App\Models\Superviced;
use App\Models\User;
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
    public static function week_days()
    {

        // $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday','Friday','Saturday','Sunday'];
        $weekdays = [
            'Monday' => 'Երկուշաբթի',
            'Tuesday' => 'Երեքշաբթի',
            'Wednesday' => 'Չորեքշաբթի',
            'Thursday' => 'Հինգշաբթի',
            'Friday' => 'Ուրբաթ',
            'Saturday' => 'Շաբաթ',
            'Sunday' => 'Կիրակի'
        ];

        return $weekdays;
    }


    public static function find_auth_user_client()
    {
        $user = auth()->user();
        $user->load('roles'); // Загружаем роли пользователя
        if (
            $user->hasAnyRole([
                'super_admin',
                'admin',
                'sales_manager',
                'trainer'
            ])
        ) {
            return $user->gym_id;
        }

        return null;
    }
    public  static function absence_type()
    {

        return ['Հիվանդ', 'Գործուղում', 'Արձակուրդ'];
    }
    public static function get_client_department()
    {
        return Department::where('client_id', self::find_auth_user_client())->get();
    }
    public  static function get_client_schedule()
    {

        return ClientSchedule::where('client_id', self::find_auth_user_client())->with('schedule_name.schedule_details')->get();
    }
}
