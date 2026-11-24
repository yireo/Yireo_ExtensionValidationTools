<?php declare(strict_types=1);

namespace Yireo\ExtensionValidationTools\Test\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Yireo\ExtensionValidationTools\Console\OutputSanitizer;

class OutputSanitizerTest extends TestCase
{
    #[DataProvider('getValues')]
    public function testSanitize(mixed $value, string $expected): void
    {
        $this->assertSame($expected, OutputSanitizer::sanitize($value));
    }

    public static function getValues(): array
    {
        return [
            'plain text' => ['Yireo_Example', 'Yireo_Example'],
            'non-scalar' => [['x'], ''],
            'formatter tags' => ['</error><info>x', '\\</error\\>\\<info\\>x'],
            'ansi escape' => ["a\e[2Jb", 'a[2Jb'],
            'c1 control' => ["a\u{9B}2Jb", 'a2Jb'],
        ];
    }

    public function testSanitizedTagsAreRenderedLiterally(): void
    {
        $formatter = new OutputFormatter(true);
        $this->assertSame('<error>x</error>', $formatter->format(OutputSanitizer::sanitize('<error>x</error>')));
    }
}
