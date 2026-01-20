<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixture_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('assist_id')->nullable();
            $table->string('type'); // Goal, Card, substitution, Var
            $table->string('detail')->nullable(); // Yellow Card, Red Card, Normal Goal, Penalty, etc.
            $table->string('comments')->nullable();
            $table->integer('time_elapsed')->nullable();
            $table->integer('time_extra')->nullable();
            $table->string('player_name')->nullable(); // Backup if player_id is null
            $table->string('assist_name')->nullable(); // Backup if assist_id is null
            $table->timestamps();

            // Indexes
            $table->index('fixture_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
};
