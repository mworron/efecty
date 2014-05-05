<?php
/**
* 2007-2014 PrestaShop 
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
*  @author       PrestaShop SA <contact@prestashop.com>
*  @copyright    2007-2014 PrestaShop SA
*  @version      Release: $Revision: 8005 $
*  @license      http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class Efecty extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();

	public $efecty_name;
	public $efecty_details;
	public $extra_mail_vars;

	public function __construct()
	{
		$this->name = 'efecty';
		$this->tab = 'payments_gateways';
		$this->version = '0.1';
		$this->author = 'jorgevrgs';

		$config = Configuration::getMultiple(array('EFECTY_DETAILS', 'EFECTY_NAME'));
		if (isset($config['EFECTY_NAME']))
			$this->efecty_name = $config['EFECTY_NAME'];
		if (isset($config['EFECTY_DETAILS']))
			$this->efecty_details = $config['EFECTY_DETAILS']

		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Efecty');
		$this->description = $this->l('Accept payments using Efecty.');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

		if ((!isset($this->efecty_name) || !isset($this->efecty_details) || empty($this->efecty_name) || empty($this->efecty_details)))
			$this->warning = $this->l('The "Account owner" and "Details" fields must be configured before using this module.');
		if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');
	
		$this->extra_mail_vars = array(
			'{efecty_name}' => Configuration::get('EFECTY_NAME'),
			'{efecty_details}' => Configuration::get('EFECTY_DETAILS'),
			'{efecty_details_html}' => str_replace("\n", '<br />', Configuration::get('EFECTY_DETAILS'))
		);
	}

	public function install()
	{
		if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn'))
			return false;
		$this->_createOrderState();
		return true;
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
					$orderState->name[$language['id_lang']] = 'Efecty: Esperando pago';
				else
					$orderState->name[$language['id_lang']] = 'Efecty: Awaiting for payment';
			}

			$orderState->send_email = true;
			$orderState->color = 'royalblue';
			$orderState->hidden = false;
			$orderState->delivery = false;
			$orderState->logable = false;
			$orderState->invoice = false;
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

	public function uninstall()
	{
		if (!Configuration::deleteByName('EFECTY_DETAILS')
		|| !Configuration::deleteByName('EFECTY_NAME')
		|| !parent::uninstall())
			return false;
		if ($this->_deleteOrderState())
			Configuration::deleteByName('PS_OS_EFECTY');
		$this->_deleteMails();
		return true;
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

	public function getContent()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= $this->displayError($err);
		}

		$this->_html .= $this->_displayEfecty();
		$this->_html .= $this->renderForm();

		return $this->_html;
	}

	private function _displayEfecty()
	{
		return $this->display(__FILE__, 'infos.tpl');
	}

	private function _postValidation()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			if (!Tools::getValue('EFECTY_DETAILS'))
				$this->_postErrors[] = $this->l('Account details are required.');
			elseif (!Tools::getValue('EFECTY_NAME'))
				$this->_postErrors[] = $this->l('Account owner is required.');
		}
	}

	private function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			Configuration::updateValue('EFECTY_NAME', pSQL(Tools::getValue('EFECTY_NAME')));
			Configuration::updateValue('EFECTY_DETAILS', pSQL(Tools::getValue('EFECTY_DETAILS')));
		}
		$this->_html .= $this->displayConfirmation($this->l('Settings updated');
	}

	private function _renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Contact details'),
					'icon' => 'icon-envelope'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Account name'),
						'name' => 'EFECTY_NAME',
					),
					array(
						'type' => 'textarea',
						'label' => $this->l('Details'),
						'desc' => $this->l('Set your Efecty details like CC or NIT, phone,...'),
						'name' => 'EFECTY_DETAILS',
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'EFECTY_NAME' => Tools::getValue('EFECTY_NAME', Configuration::get('EFECTY_NAME')),
			'EFECTY_DETAILS' => Tools::getValue('EFECTY_DETAILS', Configuration::get('EFECTY_DETAILS')),
		);
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

	public function hookPayment($params)
	{
		if (!$this->active)
			return;
		if (!$this->checkCurrency($params['cart']))
			return;

		$this->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_efecty' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;

		$state = $params['objOrder']->getCurrentState();
		if ($state == Configuration::get('PS_OS_EFECTY') || $state == Configuration::get('PS_OS_OUTOFSTOCK'))
		{
			$this->smarty->assign(array(
				'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
				'efecty_name' => $this->efecty_name,
				'efecty_details' => Tools::nl2br($this->efecty_details),
				'status' => 'ok',
				'id_order' => $params['objOrder']->id
			));
			if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
				$this->smarty->assign('reference', $params['objOrder']->reference);
		}
		else
			$this->smarty->assign('status', 'failed');
		return $this->display(__FILE__, 'payment_return.tpl');
	}
}
