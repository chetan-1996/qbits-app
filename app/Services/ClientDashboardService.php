<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Client;

class ClientDashboardService
{
    public function getGroupedClients($request)
    {
        $user = Auth::user();

        $companyId = [$user->id];
        if ($user->user_flag == 1 && !is_null($user->qbits_company_code) && $user->qbits_company_code !== '') {
            $companyId = Client::where('qbits_company_code', $user->qbits_company_code)
                ->pluck('id')
                ->all();
        }

        $search  = $request->search;
        $perPage = $request->per_page ?? 20;

        $pages = [
            'all'     => $request->page_all     ?? 1,
            'normal'  => $request->page_normal  ?? 1,
            'alarm'   => $request->page_alarm   ?? 1,
            'offline' => $request->page_offline ?? 1,
        ];

        $cacheKey = function($type, $page) use ($search, $perPage) {
            return "clients_{$type}_{$search}_{$page}_{$perPage}";
        };

        $cached = function($key, $callback) {
            return Cache::remember($key, 60, $callback);
        };

        $base = DB::table('clients as c')
            ->leftJoin('inverter_status as s', 's.user_id', '=', 'c.id')
            ->select(
                'c.*',
                DB::raw('COALESCE(s.all_plant,0) as all_plant'),
                DB::raw('COALESCE(s.normal_plant,0) as normal_plant'),
                DB::raw('COALESCE(s.alarm_plant,0) as alarm_plant'),
                DB::raw('COALESCE(s.offline_plant,0) as offline_plant'),
                DB::raw('COALESCE(s.power,0) as power'),
                DB::raw('COALESCE(s.capacity,0) as capacity'),
                DB::raw('COALESCE(s.day_power,0) as day_power'),
                DB::raw('COALESCE(s.month_power,0) as month_power'),
                DB::raw('COALESCE(s.total_power,0) as total_power'),
                's.updated_at'
            )
            ->whereIn('c.id', $companyId);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('c.username', 'like', "%{$search}%")
                  ->orWhere('c.company_name', 'like', "%{$search}%")
                  ->orWhere('c.qbits_company_code', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('c.collector', 'like', "%{$search}%")
                  ->orWhere('c.plant_name', 'like', "%{$search}%")
                  ->orWhere('c.phone', 'like', "%{$search}%");
            });
        }

        $paginate = function ($query, $pageName, $page) use ($perPage) {
            return $query->paginate($perPage, ['*'], $pageName, $page);
        };

        return [
            'all_plant' => $cached($cacheKey('all', $pages['all']), function () use ($base, $paginate, $pages) {
                return $paginate((clone $base), 'page_all', $pages['all']);
            }),
            'normal_plant' => $cached($cacheKey('normal', $pages['normal']), function () use ($base, $paginate, $pages) {
                return $paginate((clone $base)->where('s.normal_plant', '>', 0), 'page_normal', $pages['normal']);
            }),
            'alarm_plant' => $cached($cacheKey('alarm', $pages['alarm']), function () use ($base, $paginate, $pages) {
                return $paginate((clone $base)->where('s.alarm_plant', '>', 0), 'page_alarm', $pages['alarm']);
            }),
            'offline_plant' => $cached($cacheKey('offline', $pages['offline']), function () use ($base, $paginate, $pages) {
                return $paginate((clone $base)->where('s.offline_plant', '>', 0), 'page_offline', $pages['offline']);
            }),
        ];
    }
}
