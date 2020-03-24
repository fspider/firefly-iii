<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SpiderModifyTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'transaction_journals',
            function (Blueprint $table) {
                $table->smallInteger('status', false, true)->default(1);
                $table->date('date_status')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('transaction_journals', 'status')) {
            Schema::table(
                'transaction_journals',
                function (Blueprint $table) {
                    $table->dropColumn('status');
                    $table->dropColumn('date_status');
                }
            );
        }
    }
}
