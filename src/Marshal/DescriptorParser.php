<?php

declare(strict_types=1);

namespace Mlavrinenko\Paogen\Runtime\Marshal;

use Mlavrinenko\Paogen\Runtime\Attribute\AdditionalProperties;
use Mlavrinenko\Paogen\Runtime\Attribute\Discriminator;
use Mlavrinenko\Paogen\Runtime\Attribute\Format;
use Mlavrinenko\Paogen\Runtime\Attribute\WireName;
use Mlavrinenko\Paogen\Runtime\Contract\DescriptorParserInterface;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyDescriptor;
use Mlavrinenko\Paogen\Runtime\Contract\PropertyType;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

use function assert;

/**
 * Reflects a Model class once and produces a list of {@see PropertyDescriptor}s.
 * Reads the constructor's `@param` phpdoc through `phpstan/phpdoc-parser` and
 * the `#[WireName] / #[Format] / #[Discriminator]` attributes per parameter.
 *
 * Deciding which {@see PropertyType} a parameter carries is
 * {@see PropertyTypeResolver}'s job, not this one's.
 *
 * @internal
 */
final class DescriptorParser implements DescriptorParserInterface
{
    /** @var array<class-string, list<PropertyDescriptor>> */
    private array $cache = [];

    private ?PhpDocParser $docParser = null;
    private ?Lexer $lexer = null;

    /**
     * @param class-string $class
     *
     * @return list<PropertyDescriptor>
     */
    public function descriptorsFor(string $class): array
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $this->cache[$class] = [];
        }

        $phpdocTypes = $this->parseConstructorPhpdoc($constructor);
        $descriptors = [];

        foreach ($constructor->getParameters() as $parameter) {
            $descriptors[] = $this->buildDescriptor($parameter, $phpdocTypes[$parameter->getName()] ?? null, $reflection->getNamespaceName());
        }

        return $this->cache[$class] = $descriptors;
    }

    /**
     * @return array<string, TypeNode>
     */
    private function parseConstructorPhpdoc(ReflectionMethod $constructor): array
    {
        $doc = $constructor->getDocComment();

        if ($doc === false) {
            $doc = MethodDocScanner::docFor($constructor);
        }

        if ($doc === false) {
            return [];
        }

        if (!$this->docParser instanceof PhpDocParser) {
            $config = new ParserConfig([]);
            $constExpr = new ConstExprParser($config);
            $typeParser = new TypeParser($config, $constExpr);
            $this->docParser = new PhpDocParser($config, $typeParser, $constExpr);
            $this->lexer = new Lexer($config);
        }

        assert($this->lexer instanceof Lexer);
        $tokens = new TokenIterator($this->lexer->tokenize($doc));
        $node = $this->docParser->parse($tokens);

        $out = [];

        foreach ($node->getParamTagValues() as $tag) {
            $out[ltrim($tag->parameterName, '$')] = $tag->type;
        }

        return $out;
    }

    private function buildDescriptor(
        ReflectionParameter $parameter,
        ?TypeNode $phpdocType,
        string $namespace,
    ): PropertyDescriptor {
        $phpName = $parameter->getName();
        $wireAttr = $this->attributeValue($parameter, WireName::class);
        $wireName = $wireAttr === null ? $phpName : $wireAttr->name;
        $formatAttr = $this->attributeValue($parameter, Format::class);
        $discriminator = $this->attributeValue($parameter, Discriminator::class);

        $type = PropertyTypeResolver::resolve(
            $parameter->getType(),
            $phpdocType,
            $formatAttr?->format,
            $namespace,
        );

        return new PropertyDescriptor(
            $phpName,
            $wireName,
            $type,
            $discriminator,
            $this->attributeValue($parameter, AdditionalProperties::class) !== null,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $name
     *
     * @return T|null
     */
    private function attributeValue(ReflectionParameter $parameter, string $name): ?object
    {
        $attrs = $parameter->getAttributes($name);

        return $attrs === [] ? null : $attrs[0]->newInstance();
    }
}
