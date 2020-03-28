<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SpiderUserAddNameField extends Migration
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
                $table->string('name', 50);
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
        if (Schema::hasColumn('users', 'name')) {
            Schema::table(
                'users',
                function (Blueprint $table) {
                    $table->dropColumn('name');
                }
            );
        }
    }
}
