<?php

namespace App\Imports;

use App\Model\BusinessHall;
use Maatwebsite\Excel\Concerns\ToModel;

class BusinessHallImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new BusinessHall([
            'province' => empty($row[0]) ? '' : $row[0],
            'bank' => empty($row[1]) ? '' : $row[1],
            'business_hall_name' => empty($row[2]) ? '' : $row[2],
            'area' => empty($row[3]) ? '' : $row[3],
            'address' => empty($row[4]) ? '' : $row[4],
        ]);
    }
}
