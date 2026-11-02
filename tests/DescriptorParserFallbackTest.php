<?php

declare(strict_types=1);

namespace Poagen\Runtime\Tests;

use Composer\Autoload\ClassLoader;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Poagen\Runtime\Marshal\MethodDocScanner;
use Poagen\Runtime\Tests\Fixtures\DocScanTargets;
use Poagen\Runtime\Tests\Fixtures\Pet;
use ReflectionClass;
use ReflectionMethod;

use function dirname;
use function extension_loaded;
use function sprintf;

use const PHP_BINARY;

/**
 * Exercises the `token_get_all` fallback path that kicks in when
 * `ReflectionMethod::getDocComment()` returns `false` under
 * `opcache.save_comments=0`. See task 029 and
 * `attribute-and-phpdoc-driven-serialization`.
 */
final class DescriptorParserFallbackTest extends TestCase
{
    public function testTokenScanRecoversConstructorPhpdocFromFile(): void
    {
        $reflection = new ReflectionMethod(Pet::class, '__construct');
        $doc = MethodDocScanner::docFor($reflection);

        self::assertIsString($doc);
        // `Tag` unqualified: it shares a namespace with `Pet`, so this is the
        // shortest valid spelling and the one the file carries. Resolving it
        // back to a class is `PropertyTypeResolver`'s job, and
        // `testHydratorRecoversListItemTypeWhenOpcacheStripsComments` below
        // proves it happens even with comments stripped.
        self::assertStringContainsString('@param list<Tag>|null $tags', $doc);
    }

    public function testTokenScanReturnsFalseForInternalReflection(): void
    {
        // `\DateTimeImmutable::__construct` is a C-defined method — no source file.
        $reflection = new ReflectionMethod(DateTimeImmutable::class, '__construct');

        self::assertFalse(MethodDocScanner::docFor($reflection));
    }

    /**
     * `scan()` re-tokenises the whole file and builds a doc-comment map for
     * every method it contains, but the only prior coverage
     * (testTokenScanRecoversConstructorPhpdocFromFile above) checks a
     * single method against a single substring. Nothing asserted that the
     * map is built correctly once a file has more than one method, or an
     * attribute sitting between a docblock and its `function` keyword —
     * `DocScanTargets` carries exactly those three shapes, and each
     * method's recovered doc (or lack of one) is asserted individually so
     * a mis-attributed or lost doc on any one of the three fails here
     * rather than passing on the strength of another method's assertion.
     */
    public function testScanRecoversDistinctDocsAcrossMethodsAttributesAndMissingDocs(): void
    {
        $first = MethodDocScanner::docFor(new ReflectionMethod(DocScanTargets::class, 'first'));
        $second = MethodDocScanner::docFor(new ReflectionMethod(DocScanTargets::class, 'second'));
        $third = MethodDocScanner::docFor(new ReflectionMethod(DocScanTargets::class, 'third'));

        self::assertIsString($first);
        self::assertStringContainsString("First method's own doc comment.", $first);
        self::assertStringNotContainsString('Second method', $first);

        // `second`'s docblock sits above a `#[Tagged(['a', 'b'])]` attribute,
        // which itself sits above `function`. Recovering the right text here
        // proves skipAttributeGroup() walked past the attribute's own
        // nested `[...]` argument without losing the pending docblock or
        // misreading which method the `function` keyword that follows
        // belongs to.
        self::assertIsString($second);
        self::assertStringContainsString("Second method's own, distinct doc comment.", $second);
        self::assertStringNotContainsString('First method', $second);

        // `third` has no docblock at all — the scanner must not carry
        // `second`'s (or any) pending doc forward onto it.
        self::assertFalse($third);
    }

    public function testHydratorRecoversListItemTypeWhenOpcacheStripsComments(): void
    {
        if (!extension_loaded('Zend OPcache')) {
            self::markTestSkipped('Zend OPcache not loaded in CLI.');
        }

        // Not `dirname(__DIR__, N)`: a fixed hop count only works while this
        // file sits at one specific depth under one specific root, and this
        // package is deliberately built to be checked out at two different
        // roots — the monorepo (this file under `runtime/tests/`, autoloaded
        // through the root's own `vendor/`, `runtime/` reached only via the
        // path-repository symlink) and the standalone split (this file under
        // `tests/`, autoloaded through a `vendor/` composer installed right
        // beside it) — see `bundle-the-runtime-package-with-its-own-ci-
        // tooling`. Asking the classloader that is already active for its own
        // file finds the right `vendor/` in either shape without guessing a
        // depth: `ClassLoader.php` always sits at `<vendor>/composer/
        // ClassLoader.php`, so two `dirname()` calls land on `vendor/` and a
        // third on whichever root that `vendor/` belongs to.
        $classLoaderFile = new ReflectionClass(ClassLoader::class)->getFileName();

        if ($classLoaderFile === false) {
            self::fail('could not resolve the active Composer ClassLoader to a file path');
        }

        $vendorDir = dirname($classLoaderFile, 2);
        $projectRoot = dirname($vendorDir);
        $autoload = $vendorDir . '/autoload.php';
        $script = <<<PHP
            <?php
            require {$this->phpString($autoload)};

            \$reflection = new ReflectionMethod(\\Poagen\\Runtime\\Tests\\Fixtures\\Pet::class, '__construct');
            \$stripped = \$reflection->getDocComment() === false;

            \$pet = (new \\Poagen\\Runtime\\Marshal\\Hydrator())->hydrate(
                \\Poagen\\Runtime\\Tests\\Fixtures\\Pet::class,
                ['id' => 1, 'tags' => [['id' => 10, 'name' => 'friendly']]],
            );

            echo (\$stripped ? 'stripped' : 'keeps-comments'), ':',
                (\$pet->tags[0] instanceof \\Poagen\\Runtime\\Tests\\Fixtures\\Tag ? 'tag' : 'array');
            PHP;
        $scriptPath = $projectRoot . '/build/cache/opcache-fallback-' . uniqid() . '.php';

        if (!is_dir(dirname($scriptPath))) {
            mkdir(dirname($scriptPath), 0o755, true);
        }
        file_put_contents($scriptPath, $script);

        try {
            $cmd = sprintf(
                '%s -d opcache.enable_cli=1 -d opcache.save_comments=0 -d opcache.jit=disable %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($scriptPath),
            );
            $output = trim((string) shell_exec($cmd));
        } finally {
            @unlink($scriptPath);
        }

        // CI enables JIT by default, and JIT plus PCOV prints a warning on
        // stderr before the script runs. The script's own answer is the last
        // line; assert that, and keep the whole output in the failure message.
        $lines = explode("\n", $output);
        $result = trim(end($lines));

        // Guard against someone simplifying the test to the point where the
        // fallback doesn't actually fire — we need `getDocComment()` to
        // return false *and* hydration to still produce a Tag.
        self::assertSame('stripped:tag', $result, "Subprocess output: {$output}");
    }

    private function phpString(string $raw): string
    {
        return var_export($raw, true);
    }
}
