{strip}<a tabindex="-1" accesskey="A" class="{if $gBitSystem->getActivePackage()=='kernel'} active{/if}" href="{if $gBitUser->isAdmin()}{$smarty.const.KERNEL_PKG_URL}admin/index.php{else}#{/if}">
<i class="icon-cog"></i> {tr}Administration{/tr}
</a>
<ul class="dropdown-menu sub-menu pull-right">	
{foreach key=key item=menu from=$adminMenu}
	{if $key=='kernel' or $key=='liberty' or $key=='users' or $key=='themes'}
	<li class="dropdown-submenu favorite">{include file=$menu.tpl packageMenuTitle=$key packageMenuClass="dropdown-menu sub-menu"}</li>
	{/if}
{/foreach}
{foreach key=key item=menu from=$adminMenu}
	{if $key!=='kernel' and $key!=='liberty' and $key!=='users' and $key!=='themes'}
	<li class="dropdown-submenu">{include file=$menu.tpl packageMenuTitle=$key packageMenuClass="dropdown-menu sub-menu"}</li>
	{/if}
{/foreach}
</ul>
{/strip}
