{*
* 2007-2012 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2012 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $status == 'ok'}
		<div class="block_content">
		<p class="success">
		{l s='Your order on %s is complete.' sprintf=$shop_name mod='efecty'}</p>
		<h1>{l s='Payment Details:' mod='efecty'}</h1>
            {l s='Amount of payment:' mod='efecty'}   <span class="price"> <strong>{$total_to_pay}</strong></span>
            <br /><br /> {l s='To the account owner:' mod='efecty'}   <strong>{if $efectyowner}{$efectyowner}{else}___________{/if}</strong>
            <br /><br /> {l s='With these details:' mod='efecty'}   <strong>{if $efectydetails}{$efectydetails}{else}___________{/if}</strong>
       
		{if !isset($reference)}
			<br /><br /><strong>{l s='Do not forget to insert your order number #%d in the subject of your Efecty payment' sprintf=$id_order mod='efecty'}</strong>
		{else}
			<br /><br /><strong>{l s='Do not forget to insert your order reference %s in the subject of your Efecty payment.' sprintf=$reference mod='efecty'}</strong>
		{/if}		
        <br /><br />{l s='An e-mail has been sent to you with this information.' mod='efecty'}
		<br /><br /> <strong>{l s='Your order will be sent as soon as we receive your settlement.' mod='efecty'}</strong>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='efecty'} <a href="{$link->getPageLink('contact', true)}">{l s='customer support' mod='efecty'}</a>.
	</p>
</div>
{else}
	<p class="warning">
		{l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='efecty'} 
		<a href="{$link->getPageLink('contact', true)}">{l s='customer support' mod='efecty'}</a>.
	</p>
{/if}
