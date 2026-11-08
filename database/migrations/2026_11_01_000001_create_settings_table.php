<?php
use App\Migrations\Migration;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        $this->schema->create('settings', function ($table) {
            $table->bigIncrements('id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        $this->schema->dropIfExists('settings');
    }
}

