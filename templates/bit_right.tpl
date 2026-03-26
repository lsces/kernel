{if $gBitSystem->isFeatureActive( 'site_right_column' ) and $r_modules and !$gHideModules}
	{section name=homeix loop=$r_modules}
		{$r_modules[homeix].data}
	{/section}
{/if}
