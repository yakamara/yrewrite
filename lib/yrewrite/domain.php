<?php

/**
 * YREWRITE Addon.
 *
 * @author jan.kristinus@yakamara.de
 * @author gregor.harlan@redaxo.org
 *
 * @package redaxo\yrewrite
 */

class rex_yrewrite_domain
{
    private $id;
    private $name;
    private $host;
    private $scheme;
    private $path;
    private $url;
    private $mountId;
    private $startId;
    private $notfoundId;
    private $clangs;
    private $startClang;
    private $startClangAuto;
    private $startClangHidden;
    private $title;
    private $description;
    private $robots;
    private $autoRedirect;
    private $autoRedirectDays;

    public function __construct($name, $scheme, $path, $mountId, $startId, $notfoundId, array $clangs = null, $startClang = 1, $title = '', $description = '', $robots = '', $startClangHidden = false, $id = null, $autoRedirect = false, $autoRedirectDays = 0, $startClangAuto = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->scheme = $scheme;
        $this->path = $path;
        $this->host = 'default' === $name ? rex_yrewrite::getHost() : $name;
        $scheme = $scheme ?: (rex_yrewrite::isHttps() ? 'https' : 'http');
        $this->url = $scheme . '://' . $this->host . $path;
        $this->mountId = $mountId;
        $this->startId = $startId;
        $this->notfoundId = $notfoundId;
        $this->clangs = null === $clangs ? rex_clang::getAllIds() : $clangs;
        $this->startClang = $startClang;
        $this->startClangAuto = $startClangAuto && !$startClangHidden;
        $this->startClangHidden = $startClangHidden;
        $this->title = $title;
        $this->description = $description;
        $this->robots = $robots;
        $this->autoRedirect = $autoRedirect;
        $this->autoRedirectDays = $autoRedirectDays;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string|null
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return int
     */
    public function getMountId()
    {
        return $this->mountId;
    }

    /**
     * @return int
     */
    public function getStartId()
    {
        return $this->startId;
    }

    /**
     * @return int
     */
    public function getNotfoundId()
    {
        return $this->notfoundId;
    }

    /**
     * @return array
     */
    public function getClangs()
    {
        return $this->clangs;
    }

    /**
     * @return int
     */
    public function getStartClang()
    {
        return $this->startClang;
    }

    /**
     * @return bool
     */
    public function isStartClangAuto()
    {
        return $this->startClangAuto;
    }

    /**
     * @return bool
     */
    public function isStartClangHidden()
    {
        return $this->startClangHidden;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getRobots()
    {
        return $this->robots;
    }

    /**
     * @return string
     */
    public function getAutoRedirect()
    {
        return $this->autoRedirect;
    }

    /**
     * @return int
     */
    public function getAutoRedirectDays()
    {
        return $this->autoRedirectDays;
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
