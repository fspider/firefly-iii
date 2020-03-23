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
            'transactions',
            function (Blueprint $table) {
                $table->smallInteger('status', false, true)->default(0);
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
        Schema::table(
            'transactions',
            function (Blueprint $table) {
                $table->dropColumn('status');
                $table->dropColumn('date_status');
            }
        );
    }
}
