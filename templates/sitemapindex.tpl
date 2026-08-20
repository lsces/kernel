<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{foreach from=$gSiteMapIndex item=sitemap}
	<sitemap>
		<loc>{$sitemap.loc|escape:'html'}</loc>
		{if $sitemap.lastmod}<lastmod>{$sitemap.lastmod}</lastmod>{/if}
	</sitemap>
{/foreach}
</sitemapindex>
