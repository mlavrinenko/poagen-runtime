<?php

declare(strict_types=1);

namespace Poagen\Runtime\Marshal;

use ReflectionMethod;

use function count;
use function in_array;
use function is_array;

/**
 * Recovers the doc comment for a named method by re-tokenising the file
 * that declares it. Used by {@see DescriptorParser} as a fallback when
 * {@see ReflectionMethod::getDocComment()} returns `false` — which
 * happens under `opcache.save_comments=0`, where the opcache strips
 * comments from its in-memory copy of the class, even though the source
 * file on disk is unchanged.
 *
 * Results are cached per file so each source pays the `token_get_all`
 * cost at most once per process. See
 * `attribute-and-phpdoc-driven-serialization` and task 029.
 *
 * @internal
 */
final class MethodDocScanner
{
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    public static function docFor(ReflectionMethod $method): string|false
    {
        $file = $method->getFileName();

        if ($file === false) {
            return false;
        }

        if (!isset(self::$cache[$file])) {
            self::$cache[$file] = self::scan($file);
        }

        return self::$cache[$file][$method->getName()] ?? false;
    }

    /**
     * @return array<string, string>
     */
    private static function scan(string $file): array
    {
        $source = @file_get_contents($file);

        if ($source === false) {
            return [];
        }

        $tokens = token_get_all($source);
        $count = count($tokens);
        $result = [];
        $pendingDoc = null;

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                $pendingDoc = null;

                continue;
            }

            $id = $token[0];

            if ($id === T_DOC_COMMENT) {
                $pendingDoc = $token[1];

                continue;
            }

            if (self::isIgnorableBeforeFunction($id)) {
                continue;
            }

            if ($id === T_ATTRIBUTE) {
                $i = self::skipAttributeGroup($tokens, $i, $count);

                continue;
            }

            if ($id === T_FUNCTION) {
                $name = self::readFunctionName($tokens, $i, $count);

                if ($name !== null && $pendingDoc !== null && !isset($result[$name])) {
                    $result[$name] = $pendingDoc;
                }
                $pendingDoc = null;

                continue;
            }

            $pendingDoc = null;
        }

        return $result;
    }

    /**
     * Tokens permitted between a doc comment and its `function` keyword:
     * visibility modifiers plus `static` / `abstract` / `final` / `readonly`.
     */
    private static function isIgnorableBeforeFunction(int $id): bool
    {
        return in_array(
            $id,
            [T_WHITESPACE, T_COMMENT, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY],
            true,
        );
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function skipAttributeGroup(array $tokens, int $start, int $count): int
    {
        $depth = 1;
        $i = $start + 1;

        while ($i < $count && $depth > 0) {
            $token = $tokens[$i];

            if (is_array($token)) {
                ++$i;

                continue;
            }

            if ($token === '[') {
                ++$depth;
            } elseif ($token === ']') {
                --$depth;
            }
            ++$i;
        }

        return $i - 1;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function readFunctionName(array $tokens, int $functionIndex, int $count): ?string
    {
        for ($j = $functionIndex + 1; $j < $count; ++$j) {
            $token = $tokens[$j];

            if (!is_array($token)) {
                return null;
            }

            if ($token[0] === T_WHITESPACE) {
                continue;
            }

            if ($token[0] === T_STRING) {
                return $token[1];
            }

            return null;
        }

        return null;
    }
}
