<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExternalKeyLookupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('smtc_external_key_lookup', function (Blueprint $table) {
            // define tables engine and charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // define columns
            $table->id();
            $table->string('object_id', 36)->nullable()->comment('The local object unique ID (primary key - `uuid` or auto-incremented `int`)');
            $table->string('object_type', 32)->nullable()->comment('The local object type - e.g. order, entity, user, etc.');
            $table->string('ext_provider', 32)->nullable()->comment('The external provider. e.g. hubspot, pipedrive, etc.');
            $table->string('ext_environment', 16)->nullable()->comment('The external provider environment. e.g. sandbox, production, etc.');
            $table->string('ext_object_id', 36)->nullable()->comment('The external object unique ID (primary key - `uuid` or auto-incremented `int`)');
            $table->string('ext_object_type', 32)->nullable()->comment('The external object type - e.g. deal, company, contact, etc.');
            $table->timestamps();

            // add some indexes (we need one on all columns for searching)
            $table->index('object_id');
            $table->index('object_type');
            $table->index('ext_provider');
            $table->index('ext_environment');
            $table->index('ext_object_id');
            $table->index('ext_object_type');

            // none; fk will have their own indexes
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('smtc_external_key_lookup');
    }
}
