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
        Schema::create('book_genre', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            // ジャンルは書籍が紐付いている間は削除させない（アプリ側でも事前チェックする）
            $table->foreignId('genre_id')->constrained()->restrictOnDelete();
            $table->primary(['book_id', 'genre_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_genre');
    }
};
