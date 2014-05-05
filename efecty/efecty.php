<?php
/*
* 2007-2011 PrestaShop 
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
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 8005 $
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class Efecty extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();

	public  $details;
	public  $owner;

	public function __construct()
	{
		$this->name = 'efecty';
		$this->tab = 'payments_gateways';
		$this->version = '0.5';
		$this->author = 'eTiendas.co';
		

		$config = Configuration::getMultiple(array('EFECTY_WIRE_DETAILS', 'EFECTY_WIRE_OWNER'));
		if (isset($config['EFECTY_WIRE_OWNER']))
			$this->owner = $config['EFECTY_WIRE_OWNER'];
		if (isset($config['EFECTY_WIRE_DETAILS']))
			$this->details = $config['EFECTY_WIRE_DETAILS'];

		parent::__construct();

		$this->displayName = $this->l('Efecty');
		$this->description = $this->l('Accept payments using Efecty.');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
		if (!isset($this->owner) OR !isset($this->details))
			$this->warning = $this->l('Account owner and details must be configured in order to use this module correctly.');
	}

	public function install()
	{
		if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn'))
			return false;
		$this->_createOrderState();
		return true;
	}

	public function uninstall()
	{
		if (!Configuration::deleteByName('EFECTY_WIRE_DETAILS')
				OR !Configuration::deleteByName('EFECTY_WIRE_OWNER')
				OR !parent::uninstall())
			return false;
		if ($this->_deleteOrderState())
			Configuration::deleteByName('PS_OS_EFECTY');
		$this->_deleteMails();
		return true;
	}

	private function _postValidation()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			if (!Tools::getValue('details'))
				$this->_postErrors[] = $this->l('Account details are required.');
			elseif (!Tools::getValue('owner'))
				$this->_postErrors[] = $this->l('Account owner is required.');
		}
	}

	private function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			Configuration::updateValue('EFECTY_WIRE_DETAILS', Tools::getValue('details'));
			Configuration::updateValue('EFECTY_WIRE_OWNER', Tools::getValue('owner'));
		}
		$this->_html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('ok').'" /> '.$this->l('Settings updated').'</div>';
	}

	private function _displayEfecty()
	{
		$this->_html .= '<img src="../modules/'.$this->name.'/efecty.jpg" width="86" height="49" style="float:left; margin-right:15px;"><b>'.$this->l('This module allows you to accept payments using Efecty.').'</b><br /><br />
		'.$this->l('If the client chooses this payment mode, the order will change its status into a \'Waiting for payment\' status.').'<br />
		'.$this->l('Therefore, you must manually confirm the order as soon as you receive the payment.').'<br /><br /><br />';
	}

	private function _displayForm()
	{
		$this->_html .=
		'<form action="'.Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />'.$this->l('Efecty Details for Order of Service ').'</legend>
				<table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
					<tr><td colspan="2">'.$this->l('Please specify the Efecty account details for customers').'.<br /><br /></td></tr>
					<tr><td width="130" style="height: 35px;">'.$this->l('Account owner').'</td><td><input type="text" name="owner" value="'.htmlentities(Tools::getValue('owner', $this->owner), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" /></td></tr>
					<tr>
						<td width="130" style="vertical-align: top;">'.$this->l('Your Account Code:').'</td>
						<td style="padding-bottom:15px;">
							<textarea name="details" rows="4" cols="53">'.htmlentities(Tools::getValue('details', $this->details), ENT_COMPAT, 'UTF-8').'</textarea>
							<p>'.$this->l('NIT, Cedula, etc.').'</p>
						</td>
					</tr>
					<tr><td colspan="2" align="center"><input class="button" name="btnSubmit" value="'.$this->l('Update settings').'" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>';
	}

	public function getContent()
	{
		$this->_html = '<h2>'.$this->displayName.'</h2>';

		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!sizeof($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors AS $err)
					$this->_html .= '<div class="alert error">'. $err .'</div>';
		}
		else
			$this->_html .= '<br />';

		$this->_displayEfecty();
		$this->_displayForm();

		return $this->_html;
	}

	public function execPayment($cart)
	{
		if (!$this->active)
			return ;
		if (!$this->checkCurrency($cart))
			Tools::redirectLink(__PS_BASE_URI__.'order.php');
			global $cookie, $smarty;
	
			$smarty->assign(array(
				'nbProducts' => $cart->nbProducts(),
				'total' => $cart->getOrderTotal(true, Cart::BOTH),
				'this_path' => $this->_path,
				'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
			));

		return $this->display(__FILE__, 'payment_execution.tpl');
	}

	public function hookPayment($params)
	{
		if (!$this->active)
			return ;
		if (!$this->checkCurrency($params['cart']))
			return ;

		if (substr(_PS_VERSION_, 0, 3) == '1.4')
		{
			global $smarty;
			$smarty->assign(array(
				'this_path' => $this->_path,
				'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
			));
		}
		if (substr(_PS_VERSION_, 0, 3) == '1.5')
		{
			$this->smarty->assign(array(
				'this_path' => $this->_path,
				'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
			));
		}
		return $this->display(__FILE__, 'payment.tpl');
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return ;
		if (substr(_PS_VERSION_, 0, 3) == '1.4')
		{
			global $smarty;
			$state = $params['objOrder']->getCurrentState();
			if ($state == Configuration::get('PS_OS_EFECTY') OR $state == Configuration::get('PS_OS_OUTOFSTOCK'))
				$smarty->assign(array(
					'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
					'bankwireDetails' => nl2br2($this->details),
					'bankwireOwner' => $this->owner,
					'status' => 'ok',
					'id_order' => $params['objOrder']->id
				));
			else
				$smarty->assign('status', 'failed');
		}
		if (substr(_PS_VERSION_, 0, 3) == '1.5')
		{
			$state = $params['objOrder']->getCurrentState();
			if ($state == Configuration::get('PS_OS_EFECTY') || $state == Configuration::get('PS_OS_OUTOFSTOCK'))
			{
				$this->smarty->assign(array(
					'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
					'efectydetails' => Tools::nl2br($this->details),
					'efectyowner' => $this->owner,
					'status' => 'ok',
					'id_order' => $params['objOrder']->id
				));
				if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
					$this->smarty->assign('reference', $params['objOrder']->reference);
			}
			else
				$this->smarty->assign('status', 'failed');
		}
		return $this->display(__FILE__, 'payment_return.tpl');
	}

	public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

	private function _createOrderState()
	{
		if (!Configuration::get('PS_OS_EFECTY'))
		{
			$orderState = new OrderState();
			$orderState->name = array();

			foreach (Language::getLanguages() as $language)
			{
				if (strtolower($language['iso_code']) == 'es')
					$orderState->name[$language['id_lang']] = 'EN ESPERA DE PAGO POR EFECTY';
				else
					$orderState->name[$language['id_lang']] = 'Awaiting Efecty Payment';
			}

			$orderState->send_email = true;
			if (substr(_PS_VERSION_, 0, 3) == '1.4')
				$orderState->color = 'lightblue';
			else
				$orderState->color = 'royalblue';
			$orderState->hidden = false;
			$orderState->delivery = false;
			$orderState->logable = false;
			$orderState->invoice = false;
			if (substr(_PS_VERSION_, 0, 3) == '1.4')
				$orderState->template = array_fill(0,10,"efecty");
			else
				$orderState->template = 'efecty';
			if ($orderState->add())
			{
				@copy(dirname(__FILE__).'/../../modules/'.$this->name.'/logo.jpg', dirname(__FILE__).'/../../img/os/'.(int)$orderState->id.'.gif');
				@copy(dirname(__FILE__).'/../../modules/'.$this->name.'/copymails/en/efecty.html', dirname(__FILE__).'/../../mails/en/efecty.html');
				@copy(dirname(__FILE__).'/../../modules/'.$this->name.'/copymails/en/efecty.txt', dirname(__FILE__).'/../../mails/en/efecty.txt');
				@copy(dirname(__FILE__).'/../../modules/'.$this->name.'/copymails/es/efecty.html', dirname(__FILE__).'/../../mails/es/efecty.html');
				@copy(dirname(__FILE__).'/../../modules/'.$this->name.'/copymails/es/efecty.txt', dirname(__FILE__).'/../../mails/es/efecty.txt');
				Configuration::updateValue('PS_OS_EFECTY', (int)$orderState->id);
			}
		}
	}
	private function _deleteOrderState()
	{
		$orderState = new OrderState(Configuration::get('PS_OS_EFECTY'));
		return $orderState->delete();	
	}
	
	private function _deleteMails()
	{
		@unlink(dirname(__FILE__).'/../../mails/en/efecty.html');
		@unlink(dirname(__FILE__).'/../../mails/en/efecty.txt');
		@unlink(dirname(__FILE__).'/../../mails/es/efecty.html');
		@unlink(dirname(__FILE__).'/../../mails/es/efecty.txt');
	}
}