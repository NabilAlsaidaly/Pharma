<?php

namespace Tests\Unit\Education;

use App\Domains\Content\Application\Education\ArticleInput;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class ArticleInputTest extends TestCase
{
    #[DataProvider('invalidInputs')]
    public function test_input_contract_rejects_unsafe_or_out_of_bounds_values(string $method, mixed $input): void
    {
        try {
            ArticleInput::$method($input);
            $this->fail('Expected an educational input rejection.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public static function invalidInputs(): iterable
    {
        foreach (['01', '-1', '1.0', '1e2', '18446744073709551616', '18446744073709551615', 1, true] as $i => $version) {
            yield 'version '.$i => ['version', $version];
        }
        foreach (['title' => 161, 'excerpt' => 501, 'seo_title' => 161, 'meta_description' => 301] as $key => $size) {
            yield $key.' length' => ['values', [$key => str_repeat('ع', $size)]];
        }
        foreach ([['is_featured' => '1'], ['is_featured' => null], ['editorial_scope' => 'doctor_verified'], ['category_id' => '../x'], ['sort_order' => -1], ['sort_order' => 1000000000], ['sort_order' => '1'], ['owner_id' => 'forged']] as $i => $values) {
            yield 'values '.$i => ['values', $values];
        }
        $id = '01999999-0000-7000-8000-000000000001';
        yield 'duplicate links' => ['links', [['id' => $id, 'sort_order' => 0], ['id' => strtoupper($id), 'sort_order' => 1]]];
        yield 'unknown relation field' => ['links', [['id' => $id, 'sort_order' => 0, 'medically_recommended' => true]]];
        yield '21 links' => ['links', array_fill(0, 21, ['id' => $id, 'sort_order' => 0])];
        $source = ['title' => 'مصدر تجريبي', 'url' => 'https://example.org/reference', 'sort_order' => 0];
        yield '11 sources' => ['sources', array_fill(0, 11, $source)];
        yield 'duplicate normalized source' => ['sources', [$source, array_replace($source, ['url' => 'https://EXAMPLE.org/reference'])]];
        yield 'source title' => ['sources', [array_replace($source, ['title' => str_repeat('a', 201)])]];
        yield 'source note' => ['sources', [array_replace($source, ['note' => str_repeat('a', 501)])]];
        yield 'source url' => ['sources', [array_replace($source, ['url' => 'https://example.org/'.str_repeat('a', 2048)])]];
        yield 'source credentials' => ['sources', [array_replace($source, ['url' => 'https://owner:password@example.org'])]];
        yield 'source unknown field' => ['sources', [$source + ['verified' => true]]];
        $image = ['media_id' => $id, 'role' => 'inline', 'alt_text' => 'وصف صورة'];
        yield 'image alt bound' => ['images', [array_replace($image, ['alt_text' => str_repeat('a', 251)])]];
        yield 'image missing alt' => ['images', [array_replace($image, ['alt_text' => ''])]];
        yield 'image path' => ['images', [$image + ['path' => '/secret']]];
        yield 'duplicate image' => ['images', [$image, $image]];
        yield 'invalid image role' => ['images', [array_replace($image, ['role' => 'public'])]];
        yield 'values null envelope' => ['envelope', ['operation_id' => $id, 'expected_version' => '0', 'action' => 'save', 'values' => null]];
        yield 'forged ack during save' => ['envelope', ['operation_id' => $id, 'expected_version' => '0', 'action' => 'save', 'health_acknowledged' => true]];
    }

    public function test_exact_metadata_and_relationship_limits_and_false_values_are_supported(): void
    {
        $value = ArticleInput::values(['title' => str_repeat('ع', 160), 'excerpt' => str_repeat('ع', 500), 'seo_title' => str_repeat('ع', 160), 'meta_description' => str_repeat('ع', 300), 'category_id' => null, 'editorial_scope' => 'general', 'is_featured' => false, 'sort_order' => 999999999]);
        $this->assertFalse($value['is_featured']);
        $this->assertNull($value['category_id']);
        $links = [];
        for ($i = 1; $i <= 20; $i++) {
            $links[] = ['id' => '01999999-0000-7000-8000-'.str_pad((string) $i, 12, '0', STR_PAD_LEFT), 'sort_order' => $i];
        }
        $this->assertCount(20, ArticleInput::links($links));
        $sources = [];
        for ($i = 0; $i < 10; $i++) {
            $sources[] = ['title' => str_repeat('ع', 200), 'url' => 'https://example.org/'.$i, 'note' => str_repeat('ع', 500), 'sort_order' => $i];
        }
        $this->assertCount(10, ArticleInput::sources($sources));
        $images = [];
        for ($i = 0; $i < 6; $i++) {
            $images[] = ['media_id' => $links[$i]['id'], 'role' => $i === 0 ? 'cover' : 'inline', 'alt_text' => str_repeat('ع', 250)];
        }
        $this->assertSame([0, 1, 2, 3, 4, 5], array_column(ArticleInput::images($images), 'slot'));
    }
}
