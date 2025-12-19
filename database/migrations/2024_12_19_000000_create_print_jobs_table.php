<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrintJobsTable extends Migration
{
    public function up()
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('printer_ip', 45);
            $table->integer('printer_port')->default(9100);
            $table->text('payload');
            $table->enum('status', ['pending', 'retrying', 'printed', 'failed'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->text('last_error')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('printer_ip');
        });
    }

    public function down()
    {
        Schema::dropIfExists('print_jobs');
    }
}
