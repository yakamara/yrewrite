<?php

namespace Yakamara\YRewrite\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\RouteCollection;
use rex;
use rex_article;
use rex_article_cache;
use rex_clang;
use rex_sql;
use rex_yrewrite_seo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;

use const JSON_PRETTY_PRINT;

class YRewrite
{
    public function loadRoutes(): void
    {
        // Article Get Seo Details
        RouteCollection::registerRoute(
            'yrewrite/articles/seo/get',
            new Route(
                'yrewrite/articles/seo/{id}/{clang_id}',
                [
                    '_controller' => 'Yakamara\YRewrite\Api\RoutePackage\YRewrite::handleGetArticle',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get seo data of article',
        );

        // Article Update Seo Details
        RouteCollection::registerRoute(
            'yrewrite/articles/seo/update',
            new Route(
                'yrewrite/articles/seo/{id}/{clang_id}',
                [
                    '_controller' => 'Yakamara\YRewrite\Api\RoutePackage\YRewrite::handleUpdateArticle',
                    'Body' => [
                        'title' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                            'description' => 'SEO Title of article',
                        ],
                        'description' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                            'description' => 'SEO Description of article',
                        ],
                    ],
                    'bodyContentType' => 'application/json',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update seo data of article',
        );
    }

    /** @api */
    public static function handleGetArticle($Parameter): Response
    {
        $clang_id = (int) ($Parameter['clang_id'] ?? 1);
        $clang = rex_clang::get($clang_id);
        if (!$clang) {
            return new Response(json_encode(['error' => 'Content language not found']), 404);
        }

        $article = rex_article::get($Parameter['id']);

        if (!$article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $SEOArticle = new rex_yrewrite_seo($Parameter['id'], $clang->getId());

        $articleData = [
            'id' => $article->getId(),
            'clang_id' => $clang_id,
            'title' => $SEOArticle->getTitle(),
            'description' => $SEOArticle->getDescription(),
            'canonical_url' => $SEOArticle->getCanonicalUrl(),
        ];

        //     yrewrite_url_type
        //     yrewrite_url
        //     yrewrite_redirection
        //     yrewrite_image
        //     yrewrite_changefreq
        //     yrewrite_priority
        //     yrewrite_index

        return new Response(json_encode($articleData, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleUpdateArticle($Parameter): Response
    {
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        foreach ($Data as $key => $value) {
            if (null === $value) {
                unset($Data[$key]);
            }
        }

        if (0 === count($Data)) {
            return new Response(json_encode(['error' => 'No data provided']), 400);
        }

        $clang_id = ($Parameter['clang_id'] ?? 1);
        $clang = rex_clang::get($clang_id);
        if (!$clang) {
            return new Response(json_encode(['error' => 'Content language not found']), 404);
        }

        $article = rex_article::get($Parameter['id'], $clang_id);

        if (!$article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('article'));
            $sql->setWhere(['id' => $article->getId(), 'clang_id' => $article->getClangId()]);

            if (isset($Data['title'])) {
                $sql->setValue('yrewrite_title', $Data['title']);
            }

            if (isset($Data['description'])) {
                $sql->setValue('yrewrite_description', $Data['description']);
            }

            $sql->update();
            rex_article_cache::delete($article->getId(), $article->getClangId());

            return new Response(json_encode([
                'message' => 'SEO data updated',
                'id' => $Parameter['id'],
                'clang_id' => $clang_id,
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'SQL Error: Timestamp ' . microtime()]), 500);
        }
    }
}
