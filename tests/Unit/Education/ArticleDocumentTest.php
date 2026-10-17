<?php

namespace Tests\Unit\Education;

use App\Domains\Content\Application\Education\ArticleInput;
use App\Domains\Content\Domain\Education\ArticleDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class ArticleDocumentTest extends TestCase
{
    public static function textRun(string $text = 'نص', array $extra = []): array
    {
        return array_replace(['text' => $text, 'bold' => false, 'italic' => false, 'href' => null], $extra);
    }

    public static function doc(array $blocks): array
    {
        return ['schema_version' => 1, 'blocks' => $blocks];
    }

    public function test_arabic_paragraphs_marks_links_and_canonical_merging_preserve_text(): void
    {
        $text = "نص عربي؛ علامات & وأقواس (صحيحة).\nسطر ثانٍ 💚";
        $input = self::doc([['type' => 'paragraph', 'runs' => [self::textRun($text, ['bold' => true]), self::textRun(' تتمة', ['bold' => true]), self::textRun(' رابط', ['italic' => true, 'href' => 'https://EXAMPLE.org/Path?Q=A#Part'])]], ['type' => 'heading', 'level' => 3, 'runs' => [self::textRun('عنوان')]], ['type' => 'list', 'style' => 'ordered', 'items' => [[self::textRun('أول')], [self::textRun('ثانٍ')]]]]);
        $actual = ArticleDocument::normalize($input);
        $this->assertSame($text.' تتمة', $actual['blocks'][0]['runs'][0]['text']);
        $this->assertCount(2, $actual['blocks'][0]['runs']);
        $this->assertSame('https://example.org/Path?Q=A#Part', $actual['blocks'][0]['runs'][1]['href']);
        $this->assertSame($actual, ArticleDocument::normalize($actual));
        $this->assertTrue(ArticleDocument::readable($actual));
        $this->assertFalse(ArticleDocument::readable(ArticleDocument::empty()));
    }

    #[DataProvider('invalidDocuments')]
    public function test_unapproved_or_unsafe_documents_are_rejected(mixed $input): void
    {
        try {
            ArticleDocument::normalize($input);
            $this->fail('Expected a rejected structured document.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public static function invalidDocuments(): iterable
    {
        yield 'non object' => ['text'];
        yield 'unknown schema' => [['schema_version' => 2, 'blocks' => []]];
        yield 'string schema' => [['schema_version' => '1', 'blocks' => []]];
        yield 'unknown root attribute' => [['schema_version' => 1, 'blocks' => [], 'html' => '<b>']];
        yield 'too many blocks' => [self::doc(array_fill(0, 101, ['type' => 'paragraph', 'runs' => [self::textRun()]]))];
        yield 'associative blocks' => [self::doc(['x' => ['type' => 'paragraph', 'runs' => [self::textRun()]]])];
        yield 'unknown type' => [self::doc([['type' => 'iframe', 'url' => 'https://example.org']])];
        yield 'arbitrary style' => [self::doc([['type' => 'paragraph', 'style' => 'color:red', 'runs' => [self::textRun()]]])];
        yield 'h1 disallowed' => [self::doc([['type' => 'heading', 'level' => 1, 'runs' => [self::textRun()]]])];
        yield 'string heading level' => [self::doc([['type' => 'heading', 'level' => '2', 'runs' => [self::textRun()]]])];
        yield 'unknown list style' => [self::doc([['type' => 'list', 'style' => 'medical', 'items' => [[self::textRun()]]]])];
        yield 'nested list' => [self::doc([['type' => 'list', 'style' => 'ordered', 'items' => [[['type' => 'list', 'items' => []]]]]])];
        yield 'empty list' => [self::doc([['type' => 'list', 'style' => 'ordered', 'items' => []]])];
        yield '51 list items' => [self::doc([['type' => 'list', 'style' => 'unordered', 'items' => array_fill(0, 51, [self::textRun()])]])];
        yield '21 runs' => [self::doc([['type' => 'paragraph', 'runs' => array_fill(0, 21, self::textRun())]])];
        yield 'integer bold' => [self::doc([['type' => 'paragraph', 'runs' => [self::textRun(extra: ['bold' => 1])]]])];
        yield 'arbitrary run attribute' => [self::doc([['type' => 'paragraph', 'runs' => [self::textRun(extra: ['onclick' => 'alert(1)'])]]])];
        foreach (['<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '&#x3c;script>', "hidden\0text", "bidi\u{202e}text", "bad\xff"] as $i => $text) {
            yield 'unsafe text '.$i => [self::doc([['type' => 'paragraph', 'runs' => [self::textRun($text)]]])];
        }
        foreach (['javascript:alert(1)', 'data:text/html,x', '//example.org', '/relative', 'http://example.org', 'https://user:pass@example.org', 'https://example.org:8443/x', 'https://example.org/%0aX', 'https://example.org\\x'] as $i => $url) {
            yield 'unsafe link '.$i => [self::doc([['type' => 'paragraph', 'runs' => [self::textRun(extra: ['href' => $url])]]])];
        }
        yield '40001 chars' => [self::doc([['type' => 'paragraph', 'runs' => [self::textRun(str_repeat('ع', 40001))]]])];
        yield 'total text cross blocks' => [self::doc([['type' => 'paragraph', 'runs' => [self::textRun(str_repeat('a', 30000))]], ['type' => 'paragraph', 'runs' => [self::textRun(str_repeat('b', 10001))]]])];
        yield 'image remote path' => [self::doc([['type' => 'image', 'media_id' => 'https://example.org/image.png']])];
        yield 'image attributes' => [self::doc([['type' => 'image', 'media_id' => '01999999-0000-7000-8000-000000000001', 'path' => '/private']])];
        yield 'image duplicate' => [self::doc(array_fill(0, 2, ['type' => 'image', 'media_id' => '01999999-0000-7000-8000-000000000001']))];
        yield 'caption bound' => [self::doc([['type' => 'image', 'media_id' => '01999999-0000-7000-8000-000000000001', 'caption' => str_repeat('a', 501)]])];
        $images = [];
        for ($i = 1; $i <= 6; $i++) {
            $images[] = ['type' => 'image', 'media_id' => '01999999-0000-7000-8000-00000000000'.$i];
        }
        yield 'six inline blocks' => [self::doc($images)];
        // Text is under40k but repeated valid link metadata exceeds the192KiB structural bound.
        $runs = [];
        for ($i = 0; $i < 20; $i++) {
            $runs[] = self::textRun('x', ['bold' => $i % 2 === 0, 'href' => 'https://example.org/'.str_repeat('a', 1800)]);
        }
        yield 'encoded structure bytes' => [self::doc(array_fill(0, 10, ['type' => 'paragraph', 'runs' => $runs]))];
    }

    public function test_exact_document_count_and_text_boundaries_remain_supported(): void
    {
        $doc = self::doc(array_fill(0, 100, ['type' => 'paragraph', 'runs' => [self::textRun(str_repeat('ع', 400))]]));
        $this->assertCount(100, ArticleDocument::normalize($doc)['blocks']);
        $this->assertSame('18446744073709551615', ArticleInput::version('18446744073709551615', false));
        $this->assertSame('18446744073709551615', ArticleInput::next('18446744073709551614'));
    }
}
