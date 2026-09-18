<?php

namespace TypechoPlugin\Sitemap;

use Typecho\Common;
use Typecho\Date;
use Typecho\Db;
use Typecho\Db\Exception;
use Typecho\Router;
use Typecho\Widget;
use Widget\ActionInterface;
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

        foreach ($pages as $page) {
            $type = $page['type'];
            $pathinfo = Router::get($type) !== null ? Router::url($type, $page) : '#';
            $permalink = Common::url($pathinfo, $options->index);

            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($permalink) . "</loc>\n";
            echo "\t\t<lastmod>" . date('Y-m-d', $page['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>always</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }

        // 只按固定链接实际用到的变量做补全, 用不到的一律不查
        $postParams = Router::get('post')['params'] ?? [];
        $needCategory = in_array('category', $postParams, true) || in_array('mid', $postParams, true);
        $needDate = [] !== array_intersect(['year', 'month', 'day'], $postParams);

        // 一次性取回全部文章的分类, 避免逐篇查询 (N+1)
        $categories = [];
        if ($needCategory && !empty($articles)) {
            foreach (array_chunk(array_column($articles, 'cid'), 500) as $chunk) {
                $rows = $db->fetchAll($db->select(
                    'table.relationships.cid',
                    'table.metas.mid',
                    'table.metas.slug'
                )->from('table.metas')
                    ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid IN ?', $chunk)
                    ->where('table.metas.type = ?', 'category')
                    ->order('table.metas.order', Db::SORT_ASC));

                // 全局按 order 升序, 故每个 cid 首次出现的即是 order 最小的分类
                foreach ($rows as $row) {
                    if (!isset($categories[$row['cid']])) {
                        $categories[$row['cid']] = $row;
                    }
                }
            }
        }

        foreach ($articles as $article) {
            $type = $article['type'];
            $article['slug'] = urlencode($article['slug']);

            if ($needCategory) {
                $article['category'] = urlencode($categories[$article['cid']]['slug'] ?? '');
                $article['mid'] = $categories[$article['cid']]['mid'] ?? '';
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
            echo "\t\t<lastmod>" . date('Y-m-d', $article['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>always</changefreq>\n";
            echo "\t\t<priority>0.5</priority>\n";
            echo "\t</url>\n";
        }

        echo "</urlset>";
    }
}
