<?php

namespace App\Console\Commands;

use App\Model\Award;
use App\Model\BusinessHallPrize;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleasePrize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:prize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'release prize';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            DB::beginTransaction();
            $time = time();
            $res = Award::query()
                ->where('is_exchange', 0)
                ->where('expire_time', '<', $time)
                ->where('exchange_code', '<>', '')
                ->get();
            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $surplus_number = BusinessHallPrize::query()
                        ->where('prize_id', $value['prize_id'])
                        ->where('business_hall_id', $value['business_hall_id'])
                        ->first();
                    if (!empty($surplus_number)) {
                        if ($surplus_number->lock_prize_number - 1 < 0) {
                            DB::rollBack();
                            Log::error('库存余量不足', $surplus_number->toArray());
                            return false;
                        }
                        $surplus_number->decrement('lock_prize_number');
                        $surplus_number->increment('business_surplus_number');
                    }
                }
            }
            $res = Award::query()
                ->where('is_exchange', 0)
                ->where('expire_time', '<', $time)
                ->update([
                    'exchange_code' => '',
                    'expire_time' => 0,
                    'business_hall_id' => 0,
                    'business_hall_name' => ''
                ]);
            if (!$res) {
                DB::rollBack();
                Log::error('清空兑换码失败');
                return false;
            }
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return false;
        }
    }
}
