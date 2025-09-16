<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyUsersTableAddFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the default 'name' column and add first_name, last_name
            $table->dropColumn('name');
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            
            // Add new fields
            $table->string('contact')->after('email');
            $table->enum('role', ['admin', 'user'])->default('user')->after('contact');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverse the changes
            $table->dropColumn(['first_name', 'last_name', 'contact', 'role', 'is_active']);
            $table->string('name')->after('id');
        });
    }
}