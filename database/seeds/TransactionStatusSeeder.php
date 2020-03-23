<?php

use Illuminate\Database\Seeder;
use FireflyIII\Models\TransactionStatu;

class TransactionStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $status = [
            TransactionStatu::AWAITING_APPROVAL,
            TransactionStatu::AWAITING_PAYMENT,
            TransactionStatu::DECLINED,
            TransactionStatu::SUCCESS,
        ];

        foreach ($status as $statu) {
            try {
                TransactionStatu::create(['status' => $statu]);
            } catch (PDOException $e) {
                Log::info(sprintf('Could not create transaction status "%s". It might exist already.', $statu));
            }
        }
    }
}
