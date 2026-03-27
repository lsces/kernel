<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$bitlanguage|default:'en'}" lang="{$bitlanguage|default:'en'}" {$htmlTagAttributes|default:''} {if $gBitLanguage->isLanguageRTL()}dir="rtl"{/if}>
{if $gBitThemes->mDisplayMode}
	{assign var=displayClass value=$gBitThemes->mDisplayMode|cat:"mode"|cat:" "}
{/if}
{if !empty($gQueryUser->mUserId)}
	{assign var=userClass value="user"|cat:$gQueryUser->mUserId|cat:" "}
{/if}
{if !empty($gContent->mContentId)}
	{assign var=contentClass value="cid"|cat:$gContent->mContentId}
{/if}
{strip}
<head>
	<title>{$browserTitle} - {$gBitSystem->getConfig('site_title')}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="referrer" content="always">

	<link rel="icon" href="{$gBitThemes->getStyleUrl()}favicon.ico" type="image/ico" />

	{**** if the theme has a header, it goes first ****}
	{if !empty($theme_head)}
		{include file=$theme_head}
	{/if}

	{**** get custom head files from individual packages ****}
	{foreach from=$gBitThemes->mAuxFiles.templates.html_head_inc item=file}
		{include file=$file}
	{/foreach}

</head>
<body itemscope itemtype="http://schema.org/WebPage"
	{if $gBitSystem->mOnload} onload="{foreach from=$gBitSystem->mOnload item=loadString}{$loadString}{/foreach}" {/if}
	{if $gBitSystem->mOnunload} onunload="{foreach from=$gBitSystem->mOnunload item=loadString}{$loadString}{/foreach}"	{/if} 
	id="{$gBitSystem->getActivePackage()}" class="{$displayClass}{$userClass|default:''}{$contentClass}">
{if $gBitSystem->mDebugHtml}
	<div id="bw_debughtml">
		<a href="#postdebug" onclick="document.getElementById('bw_debughtml').style.display='none';">Go to content</a>
		{$gBitSystem->mDebugHtml}
	</div>
	<a name="postdebug"></a>
{/if}

	{if $gBitSystem->isFeatureActive( 'bidirectional_text' )}<div dir="rtl">{/if}

	{if $gBitThemes->mDisplayMode != 'edit'}
		{if $gBitSystem->isFeatureActive( 'site_left_column' ) && !$gHideModules && $gBitThemes->hasColumnModules('l')}
			{assign var=leftCol value=$gBitThemes->fetchLayoutColumn('l')}
		{/if}

		{if $gBitSystem->isFeatureActive( 'site_right_column' ) && !$gHideModules && $gBitThemes->hasColumnModules('r')}
			{assign var=rightCol value=$gBitThemes->fetchLayoutColumn('r')}
		{/if}
	{/if}

	{if !empty($leftCol)}
		{if !empty($rightCol)}
			{assign var=extraColumns value=2}
		{else}
			{assign var=extraColumns value=1}
		{/if}
	{elseif !empty($rightCol)}
		{assign var=extraColumns value=1}
	{else}
		{assign var=extraColumns value=0}
	{/if}

	{if $gBitSystem->isFeatureActive( 'site_top_column' ) && !$gHideModules}
	<header itemscope itemtype="http://schema.org/WPHeader" role="banner" class="container{$gBitSystem->getConfig('layout-header')}" id="bw-main-header">
		{$gBitThemes->displayLayoutColumn('t')}
		{if $gBitSystem->getConfig('site_notice')}
		<div class="container{$gBitSystem->getConfig('layout-header')}">
			<div class="sitenotice">{$gBitSystem->getConfig('site_notice')}</div>
		</div>
		{/if}

	</header>
	{/if}

	<div id="bw-main-spacer-top"></div>

	<section id="bw-main-content" class="container{$gBitSystem->getConfig('layout-body')}">
		<div class="row">
			{if !empty($gContent->mInfo)}
				{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='row' serviceHash=$gContent->mInfo}
			{/if}

			{**** Theme Layout Modules : NAVIGATION ****}
			{if !empty($leftCol)}
			<nav id="navigation" class="col-md-3 col-sm-4 col-xs-12">
				<div class="row">
					{$leftCol}
				</div>
			</nav><!-- end #navigation -->{* needed by output filters. *}
			{/if}

			<main role="main" id="wrapper" class="col-md-{math equation='12-x*3' x=$extraColumns} col-sm-{math equation='12-x*4' x=$extraColumns} col-xs-12">
				{**** Theme Layout Modules : CENTER ****}
				{if !empty($gContent->mInfo)}
					{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='wrapper' serviceHash=$gContent->mInfo}
				{/if}	
				{include file="bitpackage:liberty/structure_display.tpl"}
				{include file=$mid}
			</main><!-- end #wrapper -->

			{**** Theme Layout Modules : EXTRA ****}
			{if $rightCol}
			<nav id="extra" class="col-md-3 col-sm-4 col-xs-12">
				<div class="row">
					{$rightCol}
				</div>
			</nav><!-- end #extra -->{* needed by output filters. *}
			{/if}
		</div>
	</section>

	<div id="bw-spacer-bottom"></div>

	<footer id="bw-main-footer" class="container{$gBitSystem->getConfig('layout-footer')}">
		{**** Theme Layout Modules : BOTTOM ****}
		{if $gBitSystem->isFeatureActive( 'site_bottom_column' ) && !$gHideModules}
			{$gBitThemes->displayLayoutColumn('b')}
		{/if}
		{* get custom footer files from individual packages *}
		{foreach from=$gBitThemes->mAuxFiles.templates.footer_inc item=file}
			{include file=$file}
		{/foreach}
	</footer>

	{if $gBitSystem->isFeatureActive( 'bidirectional_text' )}</div>{/if}
	{include file="bitpackage:kernel/footer.tpl"}
	{include file="bitpackage:ckeditor/footer.tpl"}
	</body>
</html>
{/strip}
