# Sitemap — Typecho Sitemap 插件

为 [Typecho](https://typecho.org) 自动生成符合 [sitemaps.org 协议](https://www.sitemaps.org/protocol.html) 的 XML 站点地图，帮助搜索引擎更高效地抓取站点内容。

## 功能

- 自动收录已发布、达到发布时间且未设置访问密码的**文章**（post）与**独立页面**（page）
- 输出标准 XML 格式，兼容 Google Search Console、Bing Webmaster Tools 等主流搜索引擎
- 文章优先级 `0.5`，页面优先级 `0.8`
- 包含 `<lastmod>`（最后修改时间，统一输出 UTC，如 `2026-03-01T08:30:00Z`）、`<changefreq>` 字段
- 非 ASCII 的 slug（如中文）自动做 URL 编码，与站点 canonical 保持一致
- 固定链接含 `{category}` / `{directory}` 时，自动跳过没有分类的文章（这类文章在此链接格式下无法访问）
- 输出 `ETag` 与 `Cache-Control: no-cache` 响应头，支持 `If-None-Match` 条件请求：内容未变化时返回 `304 Not Modified`，节省爬虫抓取流量；新内容发布后立即可见
- 访问地址固定为 `/sitemap.xml`，无需额外配置

## 环境要求

| 项目 | 要求        |
|------|-----------|
| Typecho | 1.2.0 及以上 |
| PHP | 8.0 及以上   |

## 安装

1. 将 `Sitemap` 目录放入 `usr/plugins/`，确保目录结构如下：

```
usr/plugins/Sitemap/
    Plugin.php
    Action.php
```

2. 登录 Typecho 后台 → **控制台** → **插件** → 找到 **Sitemap** → 点击**激活**

激活后访问 `https://yourdomain.com/sitemap.xml` 即可看到生成的站点地图。

## 使用

### 提交到搜索引擎

| 搜索引擎 | 提交地址 |
|----------|----------|
| Google | [Google Search Console](https://search.google.com/search-console) |
| Bing | [Bing Webmaster Tools](https://www.bing.com/webmasters) |

在对应平台的 Sitemap 提交页面填入：

```
https://yourdomain.com/sitemap.xml
```

### 输出格式示例

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://yourdomain.com/about.html</loc>
    <lastmod>2026-03-01T08:30:00Z</lastmod>
    <changefreq>always</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://yourdomain.com/hello-world.html</loc>
    <lastmod>2026-03-01T08:30:00Z</lastmod>
    <changefreq>always</changefreq>
    <priority>0.5</priority>
  </url>
</urlset>
```

## 卸载

在后台**插件**页面禁用插件即可，禁用时会自动移除 `/sitemap.xml` 路由。

## 协议

[MIT License](./LICENSE) © 2026 Pain

