<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductControllerSlugTest extends TestCase
{
    public function test_cost_only_update_keeps_the_existing_legacy_slug(): void
    {
        $controller = new class extends ProductController
        {
            public function slugForUpdate(Product $product, string $name, int $storeId): string
            {
                return $this->resolveProductSlugForUpdate($product, $name, $storeId);
            }
        };

        $product = new Product([
            'name' => 'منتج قديم',
            'slug' => 'legacy-product-slug',
        ]);

        // نموذج التعديل يرسل الاسم الحالي أيضاً؛ لذلك يجب ألا يؤدي تعديل التكلفة إلى تغيير slug.
        $this->assertSame(
            'legacy-product-slug',
            $controller->slugForUpdate($product, 'منتج قديم', 7),
        );
    }

    public function test_name_change_generates_a_store_scoped_slug(): void
    {
        $controller = new class extends ProductController
        {
            public function slugForUpdate(Product $product, string $name, int $storeId): string
            {
                return $this->resolveProductSlugForUpdate($product, $name, $storeId);
            }
        };

        $product = new Product([
            'name' => 'Old Product',
            'slug' => 'old-product',
        ]);

        $this->assertSame(
            'New-Product-s7',
            $controller->slugForUpdate($product, 'New Product', 7),
        );
    }
}
