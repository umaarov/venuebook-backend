<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_hall_id')->constrained('wedding_halls');
            $table->foreignId('user_id')->constrained('users');
            $table->date('reservation_date');
            $table->integer('number_of_guests');
            $table->string('customer_name');
            $table->string('customer_surname');
            $table->string('customer_phone');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['booked', 'cancelled'])->default('booked');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
