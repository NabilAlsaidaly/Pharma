<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\IdentityTestCase;

class PrimaryImageConstraintCompatibilityTest extends IdentityTestCase
{
    public function test_nullable_generated_unique_key_allows_secondary_images_but_rejects_two_primaries(): void
    {
        $db = DB::connection();
        // Temporary, connection-scoped probe: never a development or Catalog table.
        $db->statement('CREATE TEMPORARY TABLE catalog_image_constraint_probe (
            id CHAR(36) PRIMARY KEY,
            product_id CHAR(36) NOT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT FALSE,
            primary_product_id CHAR(36) GENERATED ALWAYS AS
                (CASE WHEN is_primary = 1 THEN product_id ELSE NULL END) STORED,
            UNIQUE KEY image_primary_unique (primary_product_id)
        ) ENGINE=InnoDB');
        try {
            $db->table('catalog_image_constraint_probe')->insert([
                ['id' => '1', 'product_id' => 'product-a', 'is_primary' => false],
                ['id' => '2', 'product_id' => 'product-a', 'is_primary' => false],
                ['id' => '3', 'product_id' => 'product-a', 'is_primary' => true],
                ['id' => '4', 'product_id' => 'product-b', 'is_primary' => true],
            ]);
            $this->assertSame(4, $db->table('catalog_image_constraint_probe')->count());
            $this->assertNull($db->table('catalog_image_constraint_probe')->where('id', '1')->value('primary_product_id'));
            try {
                $db->table('catalog_image_constraint_probe')->insert(['id' => '5', 'product_id' => 'product-a', 'is_primary' => true]);
                $this->fail('MySQL must reject a second primary image.');
            } catch (QueryException $exception) {
                $this->assertSame('23000', $exception->errorInfo[0]);
                $this->assertSame(1062, $exception->errorInfo[1]);
            }
        } finally {
            $db->statement('DROP TEMPORARY TABLE catalog_image_constraint_probe');
        }
    }
}
