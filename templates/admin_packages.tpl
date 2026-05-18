{strip}
{* install_unavailable: shown near install links/buttons when installer is not accessible *}
{if $install_pkg_accessible}
	{capture assign=install_unavailable}{/capture}
{else}
	{capture assign=install_unavailable}
		<p class="text-muted"><em>{tr}Installer not accessible — to install or upgrade packages, symlink the <code>install</code> directory into the web root, then reload this page.{/tr}</em></p>
	{/capture}
{/if}

{assign var=pageName value="kernel_`$page`"}

{form class=$pageName|replace:'packages':'pkg'}
	<input type="hidden" name="page" value="{$page}" />
	{jstabs}
		{if $upgradable}
			{jstab title="Upgradable"}
				{legend legend="Upgradable packages"}
					<p class="warning">
						{biticon class="img-responsive" iname="large/dialog-warning" iexplain="Warning"} {tr}You seem to have at least one package that can be upgraded.{/tr}
						{if $install_pkg_accessible}
							<a href="{$smarty.const.INSTALL_PKG_URL}install.php?step=4">{tr}We recommend you visit the installer now.{/tr}</a>
						{else}
							<em class="text-muted">{tr}Make the installer accessible to proceed with upgrades.{/tr}</em>
						{/if}
					</p>

					{foreach from=$upgradable item=package key=name}
						<div class="form-group">
							<div class="formlabel">
								<label for="package_{$name}">{biticon class="img-responsive" ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}</label>
							</div>
							{forminput}
								<label>
									<strong>{$name|capitalize}</strong>
								</label>
								{formhelp note=$package.info|default:'Missing'}
							{/forminput}
						</div>
					{/foreach}
				{/legend}
			{/jstab}
		{/if}

		{jstab title="Installed"}
			{legend legend="Packages installed on your system"}
				<p>
					{tr}Packages with checkmarks are currently enabled, packages without are disabled.  To enable or disable a package, check or uncheck it, and click the 'Modify Activation' button.{/tr}
					{if $install_pkg_accessible}
						<a href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>{tr}To uninstall or reinstall a package, visit the installer.{/tr}</a>
					{else}
						{tr}You will need to enable the installer to manage packages.{/tr}
					{/if}
				</p>

				{$install_unavailable}

				<div class="bit-columns">
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if $package.installed|default:false && !$package.service|default:false && !$package.required|default:true }
					<div class="bit-column-cell">
					<div class="well">
						<div class="form-group clear">
							<div class="formlabel">
								<label for="package_{$name}">{biticon class="img-responsive" ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}</label>
							</div>
							{forminput}
								<label>
									{assign var=is_requirement value=''}
										{foreach from=$gBitSystem->mRequirements key=req item=reqs}
											{if !empty($reqs.$name) && $gBitSystem->isPackageActive($req) && $package.active_switch eq 'y'}
												{assign var=is_requirement value='true'}
											{/if}
										{/foreach}
									{if !empty($is_requirement)}
										{booticon iname="icon-ok"   iexplain="Required"}
										<input type="hidden" value="y" name="fPackage[{$name}]" id="package_{$name}" />
									{else}
										<input type="checkbox" value="y" name="fPackage[{$name}]" id="package_{$name}" {if $package.active_switch eq 'y' }checked="checked"{/if} />
									{/if}
									&nbsp; <strong>{$name|capitalize}</strong>
									{assign var=first_loop value=1}
									{foreach from=$gBitSystem->mRequirements key=required_by item=reqs}
										{if !empty($reqs.$name)}
											{if $first_loop}<br />{booticon iname="icon-warning-sign"   iexplain="Requirement"} Required by {else}, {/if}{$required_by}
											{assign var=first_loop value=0}
										{/if}
									{/foreach}
								</label>
								{formhelp note=$package.info|default:'Missing' package=$name}
							{/forminput}
						</div>
					</div>
					</div>
					{/if}
				{/foreach}
				</div>
			{/legend}


			{legend legend="Services installed on your system"}
				<p>
					A service package is a package that allows you to extend the way you display bitweaver content - such as <em>categorising your content</em>. Activating more than one of any service type might lead to conflicts.<br />
					We therefore recommend that you <em>	enable only one of each</em> <strong>service type</strong>.
				</p>
				<div class="bit-columns">
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if $package.installed && !empty($package.service) && !$package.required|default:false}
					<div class="bit-column-cell">
					<div class="well">
						<div class="form-group">
							<div class="formlabel">
								{if !$package.required|default:false}<label for="package_{$name}">{/if}{biticon class="img-responsive" ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}{if !$package.required|default:false}</label>{/if}
							</div>
							{forminput}
								<label>
									{assign var=is_requirement value=''}
									{foreach from=$gBitSystem->mRequirements key=req item=reqs}
										{if !empty($reqs.$name) && $gBitSystem->isPackageActive($req) && $package.active_switch eq 'y'}
											{assign var=is_requirement value='true'}
										{/if}
									{/foreach}
									{if $is_requirement}
										{booticon iname="icon-ok"   iexplain="Required"}
										<input type="hidden" value="y" name="fPackage[{$name}]" id="package_{$name}" />
									{else}
										<input type="checkbox" value="y" name="fPackage[{$name}]" id="package_{$name}" {if $package.active_switch eq 'y' }checked="checked"{/if} />
									{/if}
									&nbsp; <strong>{$name|capitalize}</strong>
									{assign var=first_loop value=1}
									{foreach from=$gBitSystem->mRequirements key=required_by item=reqs}
										{if !empty($reqs.$name)}
											{if $first_loop}<br />{booticon iname="icon-warning-sign"   iexplain="Requirement"} Required by {else}, {/if}{$required_by}
											{assign var=first_loop value=0}
										{/if}
									{/foreach}
									<br />
									{tr}Service Type{/tr}: <strong>{$package.service|default:''|capitalize|replace:"_":" "}</strong>
								</label>
								{formhelp note=$package.info|default:'Missing' package=$name}
							{/forminput}
						</div>
					</div>
					</div>
					{/if}
				{/foreach}
				</div>
			{/legend}

			<div class="form-group submit">
				<input type="submit" class="btn btn-default" name="features" value="{tr}Modify Activation{/tr}"/>
			</div>
		{/jstab}


		{jstab title="Required"}
			{legend legend="Required packages installed on your system"}
				<div class="bit-columns">
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if $package.installed && !$package.service && $package.required|default:false }
					<div class="bit-column-cell">
					<div class="well">
						<div class="form-group">
							<div class="formlabel">
								{biticon class="img-responsive" ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}
							</div>
							{forminput}
								<strong>{$name|capitalize}</strong>
								{formhelp note=$package.info|default:'Missing' package=$name}
							{/forminput}
						</div>
					</div>
					</div>
					{/if}
				{/foreach}
				</div>
			{/legend}
			{legend legend="Required services installed on your system"}
				<div class="bit-columns">
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if $package.installed && !empty($package.service) && $package.required|default:false}
					<div class="bit-column-cell">
					<div class="well">
						<div class="form-group">
							<div class="formlabel">
								{biticon class="img-responsive" ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}
							</div>
							{forminput}
								<label>
									<strong>{$name|capitalize}</strong>
								</label>
								{formhelp note=$package.info|default:'Missing' package=$name}
							{/forminput}
						</div>
					</div>
					</div>
					{/if}
				{/foreach}
				</div>
			{/legend}
		{/jstab}


		{if !empty($requirementsMap) || !empty($requirements)}
			{jstab title="Dependencies"}
				{legend legend="Requirements"}
					{if !empty($requirementsMap)}
						<p class="help">{tr}Below you will find an illustration of how packages depend on each other.{/tr}</p>
						<div style="text-align:center; overflow:auto;">
							<img alt="{tr}A graphical representation of package requirements{/tr}" title="{tr}Requirements graph{/tr}" src="{$smarty.const.KERNEL_PKG_URL}requirements_graph.php?install_version=1&amp;format={$smarty.request.format|default:''}&amp;command={$smarty.request.command|default:''}" usemap="#Requirements" />
							{$requirementsMap}
						</div>
					{/if}

					{if !empty($requirements)}
						<p class="help">{tr}Below you will find a detailed table with package requirements. If not all package requirements are met, consider trying to meet all package requirements. If you don't meet them, you may continue at your own peril.{/tr}</p>
						<table id="requirements">
							<caption>Package Requirements</caption>
							<tr>
								<th style="width:16%;">Requirement</th>
								<th style="width:16%;">Min Version</th>
								<th style="width:16%;">Max Version</th>
								<th style="width:16%;">Available</th>
								<th style="width:36%;">Result</th>
							</tr>
							{foreach from=$requirements item=dep}
								{if $pkg != $dep.package}
									<tr><th colspan="5">{$dep.package|ucfirst} requirements</th></tr>
									{assign var=pkg value=$dep.package}
								{/if}

								{if $dep.result == 'ok'}
									{assign var=class value=success}
								{elseif $dep.result == 'missing'}
									{assign var=class value=error}
								{elseif $dep.result == 'min_dep'}
									{assign var=class value=error}
								{elseif $dep.result == 'max_dep'}
									{assign var=class value=warning}
								{/if}

								<tr class="{$class}">
									<td>{$dep.requires|ucfirst}</td>
									<td>{$dep.required_version.min}</td>
									<td>{$dep.required_version.max|default:''}</td>
									<td>{$dep.required_version.available|default:''}</td>
									<td>
										{if $dep.result == 'ok'}
											OK
										{elseif $dep.result == 'missing'}
											Package not installed or not activated
											{assign var=missing value=true}
										{elseif $dep.result == 'min_dep'}
											Minimum version not met
											{assign var=min_dep value=true}
										{elseif $dep.result == 'max_dep'}
											Maximum version exceeded
											{assign var=max_dep value=true}
										{/if}
									</td>
								</tr>
							{/foreach}
						</table>

						{if $missing}
							{formfeedback warning="At least one required package is missing. Please activate or install the missing package." link="install/install.php?step=3/Install Package"}
						{/if}

						{if $min_dep}
							{formfeedback warning="At least one package did not meet the minimum version requirement. If possible, please upgrade to a newer version."}
						{/if}

						{if $max_dep}
							{formfeedback warning="At least one package recommend a version lower to the one you have installed. This might cause problems."}
						{/if}

						{if !$min_dep && !$max_dep && !$missing}
							{formfeedback success="All package requirements have been met."}
						{/if}
					{/if}

					<ul>
						<li>{smartlink ititle="Install Packages" ipackage=install ifile="install.php" step=3}</li>
						<li>{smartlink ititle="Upgrade Packages" ipackage=install ifile="install.php" step=4}</li>
					</ul>
				{/legend}
			{/jstab}
		{/if}


		{jstab title="Not Installed"}
			{legend legend="bitweaver packages available for installation"}

						{if $install_pkg_accessible}
							<p><a class="btn btn-default" href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>{tr}Install Packages{/tr}</a></p>
						{else}
							<p><a class="btn btn-default disabled" href='#' aria-disabled="true">{tr}Install Packages{/tr}</a></p>
							{$install_unavailable}
						{/if}

				<hr style="clear:both" />

				<div class="bit-columns">
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if ((1 || $package.tables) && !$package.required|default:false && !$package.installed|default:false) }
					<div class="bit-column-cell">
					<div class="well">
						<div class="form-group clear">
							<div class="formlabel">
								{biticon class="img-responsive" ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}
							</div>
							{forminput}
								{$name|capitalize}
								{formhelp note=$package.info|default:'Missing' package=$name}
							{/forminput}
						</div>
					</div>
					</div>
					{/if}
				{/foreach}
			{/legend}
		{/jstab}
	{/jstabs}
{/form}

{/strip}
