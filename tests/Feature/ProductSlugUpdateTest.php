<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductSlugUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ننشئ فقط الأعمدة اللازمة لعزل سلوك تحديث slug عن بقية مخطط التطبيق.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('quantity', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    public function test_updating_another_field_keeps_the_store_scoped_slug(): void
    {
        /*
         * المنتج الأول يحمل slug حديثاً مرتبطاً بالمتجر، بينما المنتج الثاني
         * يحاكي بيانات قديمة في متجر آخر تستخدم slug عاماً للاسم نفسه.
         * إعادة توليد slug المنتج الأول عند تعديل السعر ستؤدي إلى تعارض وهمي.
         */
        DB::table('products')->insert([
            [
                'id' => 1,
                'store_id' => 1,
                'name' => 'منتج تجريبي',
                'slug' => 'منتج-تجريبي-s1',
                'price' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'store_id' => 2,
                'name' => 'منتج تجريبي',
                'slug' => 'منتج-تجريبي',
                'price' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $product = Product::findOrFail(1);

        // تعديل السعر وحده يجب ألا يغيّر الاسم أو slug المرتبط بالمتجر.
        $product->update(['price' => 20]);

        $this->assertSame('منتج-تجريبي-s1', $product->fresh()->slug);
    }

    public function test_changing_the_name_still_regenerates_the_slug_when_none_is_supplied(): void
    {
        // هذا الاختبار يحمي السلوك القديم المطلوب: تغيير الاسم يولّد slug جديداً.
        DB::table('products')->insert([
            'id' => 1,
            'store_id' => 1,
            'name' => 'Old Product',
            'slug' => 'old-product-s1',
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::findOrFail(1);

        // لم نمرر slug صراحةً، لذلك يتولى حدث updating توليده من الاسم الجديد.
        $product->update(['name' => 'New Product']);

        $this->assertSame('new-product', $product->fresh()->slug);
    }
}
