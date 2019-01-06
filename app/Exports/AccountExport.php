<?php

namespace App\Exports;

use App\Model\BusinessHall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;

class AccountExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        try {
            DB::table('admin_user')->delete();
            $business = BusinessHall::query()->select('business_hall_name')->get();
            $data = $response = [];
            $time = time();
            $business->each(function ($item, $index) use (&$data, &$response, $time) {
                $str_rand = str_random(8);
                $data[$index]['username'] = $response[$index]['username'] = $item->business_hall_name;
                $response[$index]['password'] = $str_rand;
                $data[$index]['password'] = bcrypt($str_rand);
                $data[$index]['created_at'] = $time;
            });
            DB::table('admin_user')->insert($data);
            return collect($response);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code' => 1,
                'msg' =>  $exception->getMessage()
            ]);
        }
    }
}
