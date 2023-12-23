<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->string("link");
            $table->string("slug");
            $table->string("title")->nullable();
            $table->string("desc")->nullable();
            $table->string("content")->nullable();
            $table->string("source")->nullable();
            $table->string("type")->nullable();
            $table->string("list")->nullable();
            $table->string("cat")->nullable();
            $table->string("item")->nullable();
            $table->string("sub")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
