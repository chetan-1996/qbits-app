<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\ClientDashboardService;

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

        $companyId = [$user->id];
        if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
            $companyId = DB::table('clients')
                ->where('qbits_company_code', $user->qbits_company_code)
                ->pluck('id')
                ->all();
        }

        $result = DB::table('clients as c')
            ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
            ->whereIn('c.id', $companyId)
            ->selectRaw('
                COUNT(c.id) as all_plant,

                SUM(CASE WHEN s.normal_plant  > 0 THEN 1 ELSE 0 END) as normal_plant,
                SUM(CASE WHEN s.alarm_plant   > 0 THEN 1 ELSE 0 END) as alarm_plant,
                SUM(CASE WHEN s.offline_plant > 0 THEN 1 ELSE 0 END) as offline_plant,

                COALESCE(SUM(s.power),0)        as power,
                COALESCE(SUM(s.capacity),0)     as capacity,
                COALESCE(SUM(s.day_power),0)    as day_power,
                COALESCE(SUM(s.month_power),0)  as month_power,
                COALESCE(SUM(s.total_power),0)  as total_power
            ')
            ->first();

        return $this->sendResponse($result, 'Dashboard totals fetched successfully.');
    }

    // public function frontendWidgetTotals()
    // {
    //     // $data = $service->getGroupedClients($request);
    //     $user = Auth::user();

    //     $companyId=[$user->id];
    //     if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
    //         $companyId = DB::table('clients as c')->where('qbits_company_code', $user->qbits_company_code)
    //                 // ->where('user_flag', 0)
    //                 ->pluck('id')
    //                 ->all();
    //     }

    //     $query = DB::table('clients as c')
    //     ->join('inverter_status as s', 's.user_id', '=', 'c.id')
    //     ->selectRaw('
    //         SUM(s.power)         AS power,
    //         SUM(s.capacity)      AS capacity,
    //         SUM(s.day_power)     AS day_power,
    //         SUM(s.month_power)   AS month_power,
    //         SUM(s.total_power)   AS total_power
    //     ');
    //     $query->wherein('c.id', $companyId);

    //     $result = $query->first();

    //     $base = DB::table('clients as c')
    //     ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
    //     ->whereIn('c.id', $companyId);


    //     $result['all_plant'] =(clone $base)->count();
    //     $result['normal_plant'] =(clone $base)
    //         ->where('s.normal_plant', '>', 0)
    //         ->count();
    //     $result['alarm_plant'] =(clone $base)
    //         ->where('s.alarm_plant', '>', 0)
    //         ->count();
    //     $result['offline_plant'] =(clone $base)
    //         ->where('s.offline_plant', '>', 0)
    //         ->count();

    //     // unset($companyId);
    //      return $this->sendResponse($result, 'User login successfully.');
    // }
}
