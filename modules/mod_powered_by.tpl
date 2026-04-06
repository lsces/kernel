{* $Header$ *}
{strip}
{bitmodule title="$moduleTitle" name="powered_by"}
	<ul class="list-unstyled">
		<li><a href="https://www.bitweaver.org/">{biticon ipackage="liberty" iname="bitweaver" ipath="bitweaver$size/" iexplain="Bitweaver"}</a></li>
		<li><a href="http://www.smarty.net/">{biticon ipackage="liberty" iname="smarty" ipath="bitweaver$size/" iexplain="Smarty"}</a></li>
		<li>
			{if $gBitDbSystem eq 'adodb'}
				<a href="http://adodb.sourceforge.net/">{biticon ipackage="liberty" iname="adodb" ipath="bitweaver$size/" iexplain="Adodb"}</a></li>
			{else}
				<a href="http://pear.php.net/">{biticon ipackage="liberty" iname="pear" ipath="bitweaver$size/" iexplain="PEAR"}</a>
			{/if}
		<li>
			{if $gBitDbType eq 'firebird'}
				<a href="http://www.firebirdsql.org/">{biticon ipackage="liberty" iname="firebird" ipath="bitweaver$size/" iexplain="Firebird"}</a>
			{elseif $gBitDbType eq 'mysql' || $gBitDbType eq 'mysqli'}
				<a href="http://www.mysql.com/">{biticon ipackage="liberty" iname="mysql" ipath="bitweaver$size/" iexplain="MySQL"}</a>
			{elseif $gBitDbType eq 'postgres'}
				<a href="http://www.postgresql.org/">{biticon ipackage="liberty" iname="postgresql" ipath="bitweaver$size/" iexplain="PostgreSQL"}</a>
			{elseif $gBitDbType eq 'oracle'}
				<a href="http://www.oracle.com/">{biticon ipackage="liberty" iname="oracle" ipath="bitweaver$size/" iexplain="Oracle"}</a>
			{/if}
		</li>
		{if $gLibertySystem->isPluginActive( 'filterhtmlpure' )}
			<li><a href="http://htmlpurifier.org">{biticon ipackage="liberty" iname="htmlpurifier" ipath="bitweaver/" iexplain="HTMLPurifier"}</a></li>
		{/if}
	</ul>
{/bitmodule}
{/strip}
