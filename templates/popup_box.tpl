{strip}

<div class='popup box'>
	<h3>
		{$title}
		{if $closebutton}
			<a class="closebutton" onclick='javascript:return cClick();'>{biticon ipackage="icons" iname="list-remove"  ipackage="icons"  iexplain="close" iforce="icon"}</a>
		{/if}
	</h3>

	<div class='boxcontent'>
		{$content}
	</div>
</div>

{/strip}
