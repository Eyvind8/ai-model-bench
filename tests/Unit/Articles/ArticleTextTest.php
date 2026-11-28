<?php

namespace Tests\Unit\Articles;

use App\Articles\ArticleTextNormalizer;
use App\Articles\LanguageDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArticleTextTest extends TestCase
{
    public function test_normalizer_collapses_whitespace(): void
    {
        $result = (new ArticleTextNormalizer)->normalize("  Title \r\n\n\n\n Line   one\t two \n\n\n", 0);

        $this->assertSame("Title\n\nLine one two", $result->text);
        $this->assertFalse($result->truncated);
    }

    public function test_normalizer_truncates_at_word_boundary(): void
    {
        $text = str_repeat('слово ', 100);

        $result = (new ArticleTextNormalizer)->normalize($text, 50);

        $this->assertTrue($result->truncated);
        $this->assertSame(599, $result->originalChars);
        $this->assertLessThanOrEqual(51, mb_strlen($result->text));
        $this->assertStringEndsWith('слово…', $result->text);
    }

    #[DataProvider('languages')]
    public function test_language_detector(string $text, ?string $expected): void
    {
        $this->assertSame($expected, (new LanguageDetector)->detect($text));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function languages(): array
    {
        return [
            'ukrainian' => ['Національний банк знизив облікову ставку, це їхнє рішення', 'uk'],
            'russian' => ['Национальный банк снизил учетную ставку, это было ожидаемо', 'ru'],
            'english' => ['The national bank cut its key rate by one percentage point', 'en'],
            'too short' => ['Ок', null],
        ];
    }
}
