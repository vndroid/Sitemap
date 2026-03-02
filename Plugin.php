<?php

namespace TypechoPlugin\Sitemap;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 标准 Google Sitemap 生成插件 for Typecho
 *
 * @package Sitemap
 * @author Vex
 * @version 0.1.0
 * @link https://github.com/vndroid/Sitemap.git
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     */
    public static function activate(): string
    {
        Helper::addRoute('sitemap', '/sitemap.xml', 'TypechoPlugin\Sitemap\Action', 'action');

        return _t('插件激活成功');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return string
     */
    public static function deactivate(): string
    {
        Helper::removeRoute('sitemap');

        return _t('站点地图生成已停止');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Form $form 配置面板
     * @return void
     */
    public static function config(Form $form)
    {
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form)
    {
    }
}
