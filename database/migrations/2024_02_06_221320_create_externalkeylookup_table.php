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
        // load the local db primary key format
        $dbPrimaryKeyFormat = config('sync_modeltocrm.db.primary_key_format', 'int');

        // create the table
        Schema::create('smtc_external_key_lookup', function (Blueprint $table) use ($dbPrimaryKeyFormat) {
            // define tables engine and charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // define columns
            $table->id();
            $table->string('object_type', 64)->nullable()->comment('The local object type - e.g. order, entity, user, etc.');
            if ($dbPrimaryKeyFormat === 'uuid') {
                $table->string('object_id', 36)->nullable()->comment('The local object unique ID (primary key - `uuid`)');
            } else {
                $table->unsignedBigInteger('object_id')->nullable()->comment('The local object unique ID (primary key - auto-incremented `int`)');
            }
            $table->string('ext_provider', 64)->nullable()->comment('The external provider. e.g. hubspot, pipedrive, etc.');
            $table->string('ext_object_type', 64)->nullable()->comment('The external object type - e.g. deal, company, contact, etc.');
            $table->string('ext_object_id', 128)->nullable()->comment('The external object unique ID (primary key - `uuid` or auto-incremented `int`)');
            $table->string('ext_environment', 32)->nullable()->default('production')->comment('The external provider environment. e.g. sandbox, production, etc.');
            $table->timestamps();

            // add some indexes (we need one on all columns for searching)
            $table->index('object_id');
            $table->index('ext_object_type');
            $table->index('ext_object_id');
            $table->index('ext_environment');

            // composite indexes
            $table->index(['object_type', 'object_id']);
            $table->index(['ext_provider', 'ext_object_type', 'ext_object_id', 'ext_environment'], 'object_lookup_ext_provider_env_oid_ot_index');
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
