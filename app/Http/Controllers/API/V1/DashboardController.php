<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends BaseController
{
    public function widgetTotals(){
        $totals = DB::table('clients as c')
        // ->where('c.user_flag', 1)  // 👈 filter early
        ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
        ->selectRaw('
            SUM(s.all_plant)     AS all_plant,
            SUM(s.normal_plant)  AS normal_plant,
            SUM(s.alarm_plant)   AS alarm_plant,
            SUM(s.offline_plant) AS offline_plant,
            SUM(s.power)         AS power,
            SUM(s.capacity)      AS capacity,
            SUM(s.day_power)     AS day_power,
            SUM(s.month_power)   AS month_power,
            SUM(s.total_power)   AS total_power
        ')
        ->first();
         return $this->sendResponse($totals, 'User login successfully.');
    }

    public function frontendWidgetTotals()
    {
        $user = Auth::user();

        $companyId=[$user->id];
        if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
            $companyId = DB::table('clients as c')->where('qbits_company_code', $user->qbits_company_code)
                    // ->where('user_flag', 0)
                    ->pluck('id')
                    ->all();
        }

        $totals = DB::table('clients as c')
        ->join('inverter_status as s', 's.user_id', '=', 'c.id')
        ->selectRaw('
            SUM(s.all_plant)     AS all_plant,
            SUM(s.normal_plant)  AS normal_plant,
            SUM(s.alarm_plant)   AS alarm_plant,
            SUM(s.offline_plant) AS offline_plant,
            SUM(s.power)         AS power,
            SUM(s.capacity)      AS capacity,
            SUM(s.day_power)     AS day_power,
            SUM(s.month_power)   AS month_power,
            SUM(s.total_power)   AS total_power
        ')
        ->whereIn('c.id', $companyId)
        ->first();

        unset($companyId);
         return $this->sendResponse($totals, 'User login successfully.');
    }
}
