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

        $pages = $db->fetchAll($db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', Date::time())
            ->where('table.contents.type = ?', 'page')
            ->order('table.contents.created', Db::SORT_DESC));

        $articles = $db->fetchAll($db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', Date::time())
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
            echo "\t\t<lastmod>" . date('Y-m-d\TH:i:s\Z', $page['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>always</changefreq>\n";
            echo "\t\t<priority>0.8</priority>\n";
            echo "\t</url>\n";
        }

        foreach ($articles as $article) {
            $type = $article['type'];
            $article['categories'] = $db->fetchAll($db->select()->from('table.metas')
                ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $article['cid'])
                ->where('table.metas.type = ?', 'category')
                ->order('table.metas.order', Db::SORT_ASC));
            $article['category'] = urlencode(current(array_column($article['categories'], 'slug')));
            $article['slug'] = urlencode($article['slug']);
            $date = new Date($article['created']);
            $article['year'] = $date->year;
            $article['month'] = $date->month;
            $article['day'] = $date->day;
            $pathinfo = Router::get($type) !== null ? Router::url($type, $article) : '#';
            $permalink = Common::url($pathinfo, $options->index);

            echo "\t<url>\n";
            echo "\t\t<loc>" . htmlspecialchars($permalink) . "</loc>\n";
            echo "\t\t<lastmod>" . date('Y-m-d\TH:i:s\Z', $article['modified']) . "</lastmod>\n";
            echo "\t\t<changefreq>always</changefreq>\n";
            echo "\t\t<priority>0.5</priority>\n";
            echo "\t</url>\n";
        }

        echo "</urlset>";
    }
}
