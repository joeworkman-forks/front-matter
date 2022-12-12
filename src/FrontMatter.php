<?php

/*
 * This is part of the webuni/front-matter package.
 *
 * (c) Martin Hasoň <martin.hason@gmail.com>
 * (c) Webuni s.r.o. <info@webuni.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webuni\FrontMatter;

use Webuni\FrontMatter\Processor\JsonWithoutBracesProcessor;
use Webuni\FrontMatter\Processor\ProcessorInterface;
use Webuni\FrontMatter\Processor\TomlProcessor;
use Webuni\FrontMatter\Processor\YamlProcessor;

final class FrontMatter implements FrontMatterInterface, FrontMatterExistsInterface
{
    /** @var string */
    private $startSep;

    /** @var string */
    private $endSep;

    /** @var ProcessorInterface */
    private $processor;

    /** @var string */
    private $regexp;

    public static function createYaml(): self
    {
        return new self(new YamlProcessor(), '---', '---');
    }

    public static function createToml(): self
    {
        return new self(new TomlProcessor(), '+++', '+++');
    }

    public static function createJson(): self
    {
        return new self(new JsonWithoutBracesProcessor(), '{', '}');
    }

    public function __construct(ProcessorInterface $processor = null, string $startSep = '---', string $endSep = '---')
    {
        $this->startSep = $startSep;
        $this->endSep = $endSep;
        $this->processor = $processor ?: new YamlProcessor();

        $this->regexp = '{^(?:'.preg_quote($startSep, null).")[\r\n|\n]*(.*?)[\r\n|\n]+(?:".preg_quote($endSep, null).")[\r\n|\n]*(.*)$}s";
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $source): Document
    {
        if (preg_match($this->regexp, $source, $matches) !== 1) {
            return new Document($source);
        }

        $frontMatter = $this->cancelIndentation($matches[1]);

        /** @var array<string, mixed> $data */
        $data = '' !== $frontMatter ? $this->processor->parse($frontMatter) : [];

        return new Document($matches[2], $data);
    }

    /**
     * {@inheritdoc}
     */
    public function dump(Document $document): string
    {
        $data = trim($this->processor->dump($document->getData()));
        if ('' === $data) {
            return $document->getContent();
        }

        return sprintf("%s\n%s\n%s\n%s", $this->startSep, $data, $this->endSep, $document->getContent());
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $source): bool
    {
        return preg_match($this->regexp, $source) === 1;
    }

    private function cancelIndentation(string $string): string
    {
        $indent = $this->revealIndention($string);

        if ('' === $indent) {
            return trim($string);
        }

        return trim(preg_replace("/^$indent/m", '', $string));
    }

    private function revealIndention(string $string): string
    {
        $separator = "\r\n";
        $line = strtok($string, $separator);
        while ($line !== false) {
            if (trim($line) !== '') {
                preg_match('/^(\s+)/', $line, $match);
                return $match[1] ?? '';
            }
            $line = strtok($separator);
        }

        return '';
    }
}
