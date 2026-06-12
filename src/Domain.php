<?php

namespace Yakamara\YRewrite;

use Redaxo\Core\Language\Language;
use Stringable;

/**
 * Represents a single (multidomain) domain configuration.
 *
 * Port of REDAXO 5 `rex_yrewrite_domain`.
 */
class Domain implements Stringable
{
    private string $host;
    private string $url;
    /** @var list<int> */
    private array $clangs;
    private bool $startClangAuto;

    /**
     * @param list<int>|null $clangs
     */
    public function __construct(
        private string $name,
        private ?string $scheme,
        private string $path,
        private int $mountId,
        private int $startId,
        private int $notfoundId,
        ?array $clangs = null,
        private int $startClang = 1,
        private string $title = '',
        private string $description = '',
        private string $robots = '',
        private bool $startClangHidden = false,
        private ?int $id = null,
        private bool $autoRedirect = false,
        private int $autoRedirectDays = 0,
        bool $startClangAuto = false,
    ) {
        $this->host = 'default' === $name ? (YRewrite::getHost() ?? '') : $name;
        $resolvedScheme = $scheme ?: (YRewrite::isHttps() ? 'https' : 'http');
        $this->url = $resolvedScheme . '://' . $this->host . $path;
        $this->clangs = null === $clangs ? Language::getAllIds() : $clangs;
        $this->startClangAuto = $startClangAuto && !$startClangHidden;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMountId(): int
    {
        return $this->mountId;
    }

    public function getStartId(): int
    {
        return $this->startId;
    }

    public function getNotfoundId(): int
    {
        return $this->notfoundId;
    }

    /** @return list<int> */
    public function getClangs(): array
    {
        return $this->clangs;
    }

    public function getStartClang(): int
    {
        return $this->startClang;
    }

    public function isStartClangAuto(): bool
    {
        return $this->startClangAuto;
    }

    public function isStartClangHidden(): bool
    {
        return $this->startClangHidden;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getRobots(): string
    {
        return $this->robots;
    }

    public function getAutoRedirect(): bool
    {
        return $this->autoRedirect;
    }

    public function getAutoRedirectDays(): int
    {
        return $this->autoRedirectDays;
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
