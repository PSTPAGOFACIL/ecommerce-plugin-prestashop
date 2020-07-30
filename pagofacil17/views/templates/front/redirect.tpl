{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{extends file='page.tpl'} 
{block name='content'}
	<div>
		{if $urlTrx }
			<input type="hidden" id="url_trx" name="url_trx" value="{$urlTrx}">
		{/if}
		<h3>{l s='Redirect your customer' mod='pagofacil17'}:</h3>
		<ul class="alert alert-info">
				<li>{l s='In a few moments you will be redirected to the Pago Facil page to make your payment' mod='pagofacil17'}.</li>
		</ul>
	</div>
{/block}
