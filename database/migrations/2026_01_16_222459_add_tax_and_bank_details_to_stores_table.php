<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('stores', function (Blueprint $table) {
        $table->string('tax_number')->nullable()->after('address'); // الرقم الضريبي
        $table->string('commercial_registration')->nullable()->after('tax_number'); // السجل التجاري
        $table->text('bank_accounts')->nullable()->after('commercial_registration'); // الحسابات البنكية
    });
}

public function down()
{
    Schema::table('stores', function (Blueprint $table) {
        $table->dropColumn(['tax_number', 'commercial_registration', 'bank_accounts']);
    });
}
};
