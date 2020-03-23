<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SpiderModifyUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'users',
            function (Blueprint $table) {
                $table->smallinteger('isAccountant', false, true)->default(0);
            }
        );
        if (!Schema::hasTable('accountant_users')) {
            Schema::create(
                'accountant_users',
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->timestamps();
                    $table->integer('accountant_id', false, true)->default(0);
                    $table->integer('user_id', false, true)->default(0);
                    $table->tinyInteger('status', false, true)->default(0);
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'isAccountant')) {
            Schema::table(
                'users',
                function (Blueprint $table) {
                    $table->dropColumn('isAccountant');
                }
            );
        }

        if (Schema::hasTable('accountant_users')) {
            Schema::drop('accountant_users');
        }
    }
}
