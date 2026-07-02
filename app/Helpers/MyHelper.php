<?php

namespace App\Helpers;


use App\Models\GymSchedule;


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
        //$substring = substr($binaryString, 9, 16); // Индексы начинаются с 0, поэтому берем с 9 символа и длиной 16
        //$decimal = bindec($substring);
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
                'trainer',
                'manager',
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
    //public static function get_client_department()
    //{
    //    return Department::where('gym_id', self::find_auth_user_client())->get();
    //}
    public  static function get_client_schedule()
    {

        return GymSchedule::where('gym_id', self::find_auth_user_client())->with('schedule_name.schedule_details')->get();
    }
    // public static function find_auth_user_client(){

    //     if(auth()->user()->hasRole(['client_admin','client_admin_rfID'])){

    //         $gym_id = Client::where('user_id',Auth::id())->value('id');
    //     }
    //     else{
    //         $gym_id = Staff::where('user_id',Auth::id())->value('client_admin_id');

    //     }
    //     return $gym_id;

    // }


}
