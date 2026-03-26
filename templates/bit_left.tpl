{if $gBitSystem->isFeatureActive( 'site_left_column' ) and $l_modules and !$gHideModules}
	{section name=homeix loop=$l_modules}
		{$l_modules[homeix].data}
	{/section}
{/if}
