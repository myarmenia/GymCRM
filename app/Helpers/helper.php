<?php



use App\Models\Person;



if (! function_exists('getPeople')) {
    function getPeople($id){

        return Person::where('id',$id)->first();

    }
}


if (!function_exists('getAllPeople')) {
    function getAllPeople($clientId)
    {
        return Person::where('client_id', $clientId)->get(['id', 'name', 'surname']);

    }
}







