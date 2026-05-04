<?php

namespace Tests\Unit;

use App\Support\TagNameNormalizer;
use PHPUnit\Framework\TestCase;

class TagNameNormalizerTest extends TestCase
{
    public function test_it_normalizes_tag_names_for_storage(): void
    {
        $cases = [
            ['  友達  ', '友達', '友達'],
            ['ともだち', '友達', '友達'],
            ['友人', '友達', '友達'],
            ['なつ', '夏', '夏'],
            [' ＡＩ　Memory ', 'AI Memory', 'ai memory'],
        ];

        foreach ($cases as [$input, $expectedName, $expectedNormalizedName]) {
            $tagName = TagNameNormalizer::normalize($input);

            $this->assertSame($expectedName, $tagName->name);
            $this->assertSame($expectedNormalizedName, $tagName->normalizedName);
        }
    }
}
