<?php

namespace TypechoPlugin\Sitemap;

use Typecho\Common;
use Typecho\Date;
use Typecho\Db;
use Typecho\Db\Exception;
use Typecho\Router;
use Typecho\Widget;
use Widget\ActionInterface;
use Widget\Contents\Page\Rows as PageRows;
use Widget\Metas\Category\Rows as CategoryRows;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends Widget implements ActionInterface
{
    public function execute()
    {
    }

    /**
     * @throws Exception
     */
    public function action(): void
    {
        $db = Db::get();
        $options = Options::alloc();

        $pages = $db->fetchAll($db->select(
            'table.contents.cid',
            'table.contents.slug',
            'table.contents.type',
            'table.contents.created',
            'table.contents.modified'
        )->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', Date::time())
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->where('table.contents.type = ?', 'page')
            ->order('table.contents.created', Db::SORT_DESC));

        $articles = $db->fetchAll($db->select(
            'table.contents.cid',
            'table.contents.slug',
            'table.contents.type',
            'table.contents.created',
            'table.contents.modified'
        )->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', Date::time())
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Db::SORT_DESC));

        $this->response->setContentType('application/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        // 页面固定链接用到 {directory} (多级页面) 时, 与核心 Archive::___directory 一致: 父页面 slug + 自身 slug
        $pageTree = in_array('directory', Router::get('page')['params'] ?? [], true)
            ? PageRows::allocWithAlias('sitemap-page-rows')
            : null;

        foreach ($pages as $page) {
            $type = $page['type'];

            if ($pageTree !== null) {
                $directory = $pageTree->getAllParentsSlug((int)$page['cid']);
                $directory[] = $page['slug'];
                $page['directory'] = implode('/', array_map('urlencode', $directory));
            }

            $page['slug'] = urlencode($page['slug']);
            $pathinfo = Router::get($type) !== null ? Router::url($type, $page) : '#';
            $permalink = Common::url($pathinfo, $options->index);

            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($permalink) . "</loc>\n";
            echo "\t\t<lastmod>" . gmdate('Y-m-d\\TH:i:s\\Z', $page['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>always</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }

        // 只按固定链接实际用到的变量做补全, 用不到的一律不查
        $postParams = Router::get('post')['params'] ?? [];
        $needDirectory = in_array('directory', $postParams, true);
        $needCategory = $needDirectory || in_array('category', $postParams, true);
        $needDate = [] !== array_intersect(['year', 'month', 'day'], $postParams);

        // 复用 Typecho 的分类树顺序, 再批量取回文章与分类的关系, 避免逐篇查询 (N+1)
        $articleCategories = [];
        $categoryRows = null;
        if ($needCategory && !empty($articles)) {
            $categoryRows = CategoryRows::allocWithAlias('sitemap-category-rows');
            $allCategories = [];
            while ($categoryRows->next()) {
                $allCategories[] = [
                    'mid' => $categoryRows->mid,
                    'slug' => $categoryRows->slug,
                    'order' => $categoryRows->order,
                ];
            }

            // Typecho 1.2 按 order、mid 选择文章主分类; Related 类引入后改为分类树顺序
            if (!class_exists('Widget\Metas\Category\Related')) {
                usort($allCategories, static function ($a, $b) {
                    return [$a['order'], $a['mid']] <=> [$b['order'], $b['mid']];
                });
            }

            $categoryMap = [];
            $categoryRanks = [];
            foreach ($allCategories as $rank => $category) {
                $mid = (int)$category['mid'];
                $categoryMap[$mid] = $category;
                $categoryRanks[$mid] = $rank;
            }

            foreach (array_chunk(array_column($articles, 'cid'), 500) as $chunk) {
                $rows = $db->fetchAll($db->select(
                    'table.relationships.cid',
                    'table.relationships.mid'
                )->from('table.relationships')
                    ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid IN ?', $chunk)
                    ->where('table.metas.type = ?', 'category'));

                foreach ($rows as $row) {
                    $cid = (int)$row['cid'];
                    $mid = (int)$row['mid'];

                    if (!isset($categoryMap[$mid])) {
                        continue;
                    }

                    if (
                        !isset($articleCategories[$cid])
                        || $categoryRanks[$mid] < $articleCategories[$cid]['rank']
                    ) {
                        $articleCategories[$cid] = [
                            'rank' => $categoryRanks[$mid],
                            'row' => $categoryMap[$mid],
                        ];
                    }
                }
            }
        }

        foreach ($articles as $article) {
            $type = $article['type'];
            $article['slug'] = urlencode($article['slug']);

            if ($needCategory) {
                $category = $articleCategories[$article['cid']]['row'] ?? null;

                // 固定链接含 {category}/{directory} 时, 无分类文章的核心链接会退化成缺段路径,
                // 任何地址都访问不到 (404, 或 301 到 404), 不应提交给搜索引擎
                if ($category === null) {
                    continue;
                }

                $article['category'] = urlencode($category['slug']);

                if ($needDirectory) {
                    $directory = $categoryRows->getAllParentsSlug((int)$category['mid']);
                    $directory[] = $category['slug'];
                    $article['directory'] = implode('/', array_map('urlencode', $directory));
                }
            }

            if ($needDate) {
                $date = new Date($article['created']);
                $article['year'] = $date->year;
                $article['month'] = $date->month;
                $article['day'] = $date->day;
            }

            $pathinfo = Router::get($type) !== null ? Router::url($type, $article) : '#';
            $permalink = Common::url($pathinfo, $options->index);

            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($permalink) . "</loc>\n";
            echo "\t\t<lastmod>" . gmdate('Y-m-d\\TH:i:s\\Z', $article['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>always</changefreq>\n";
            echo "\t\t<priority>0.5</priority>\n";
            echo "\t</url>\n";
        }

        echo "</urlset>";
    }
}
