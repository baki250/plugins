<?php
/**
 * @author Adyen <support@adyen.com>
 * @copyright Adyen B.V.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GPL 2.0
 */

include(dirname(__FILE__).'/config/config.inc.php');

class Adyen extends PaymentModule {
	private	$_html = '';
	private $_postErrors = array();
	
	private $_merchantAccount;
	private $_skinCode;
	private $_mode;
	private $_pageType;
	private $_hmacTest;
	private $_hmacLive;
	
	private $_notificationUsername;
	private $_notificationPassword;
	private $_notificationDebugInfo;
	private $_sessionValidity;
	private $_shippingDate;
	private $_oneClick;
	
	public function __construct() {
		$this->name = 'adyen';
		$this->tab = 'Payment';
		$this->version = '1.3';
		
		$this->currencies = true;
		$this->currencies_mode = 'radio';

        parent::__construct();

		$this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Adyen');
        $this->description = $this->l('Accepts payments by Adyen\'s Hosted Payment Page');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details for the Adyen plugin?');
		
		$this->_init();
		
		if (!isset($this->_merchantAccount) OR !$this->_skinCode) {
			$this->warning = $this->l('You need to configure your Ayden plugin');
		} elseif (!isset($this->_hmacTest) AND $this->_mode == 'test') {
			$this->warning = $this->l('You need to configure your HMAC key for Test');
		} elseif (!isset($this->_hmacLive) AND $this->_mode == 'live') {
			$this->warning = $this->l('You need to configure your HMAC key for Live');
		}
	}
	
	protected function _init() {
		$config =  Configuration::getMultiple(array(
			'ADYEN_MERCHANTACCOUNT', 
			'ADYEN_MODE', 
			'ADYEN_PAGE_TYPE', 
			'ADYEN_SKINCODE',
			'ADYEN_HMAC_TEST',
			'ADYEN_HMAC_LIVE',
			'ADYEN_NOTIFICATION_USERNAME',
			'ADYEN_NOTIFICATION_PASSWORD',
			'ADYEN_NOTIFICATION_DEBUGINFO',
			'ADYEN_SESSION_VALIDITY',
			'ADYEN_SHIPPING_DATE',
			'ADYEN_ONECLICK'));
		if (isset($config['ADYEN_MERCHANTACCOUNT']))		$this->_merchantAccount = $config['ADYEN_MERCHANTACCOUNT'];
		if (isset($config['ADYEN_MODE']))					$this->_mode = $config['ADYEN_MODE'];
		if (isset($config['ADYEN_PAGE_TYPE']))				$this->_pageType = $config['ADYEN_PAGE_TYPE'];
		if (isset($config['ADYEN_SKINCODE']))				$this->_skinCode = $config['ADYEN_SKINCODE'];
		if (isset($config['ADYEN_HMAC_TEST']))				$this->_hmacTest = $config['ADYEN_HMAC_TEST'];
		if (isset($config['ADYEN_HMAC_LIVE']))				$this->_hmacLive = $config['ADYEN_HMAC_LIVE'];
		if (isset($config['ADYEN_NOTIFICATION_USERNAME']))	$this->_notificationUsername = $config['ADYEN_NOTIFICATION_USERNAME'];
		if (isset($config['ADYEN_NOTIFICATION_PASSWORD']))	$this->_notificationPassword = $config['ADYEN_NOTIFICATION_PASSWORD'];
		if (isset($config['ADYEN_NOTIFICATION_DEBUGINFO']))	$this->_notificationDebugInfo = $config['ADYEN_NOTIFICATION_DEBUGINFO'];
		if (isset($config['ADYEN_SESSION_VALIDITY']))		$this->_sessionValidity = $config['ADYEN_SESSION_VALIDITY'];
		if (isset($config['ADYEN_SHIPPING_DATE']))			$this->_shippingDate = $config['ADYEN_SHIPPING_DATE'];
		if (isset($config['ADYEN_ONECLICK']))				$this->_oneClick = $config['ADYEN_ONECLICK'];
	}

	public function getAdyenUrl() {
		$mainUrl = Configuration::get('ADYEN_MODE') == 'live' ? 'https://live.adyen.com/hpp/' : 'https://test.adyen.com/hpp/';
		return $mainUrl .( Configuration::get('ADYEN_PAGE_TYPE') == 'single' ? 'pay.shtml' : 'select.shtml');
	}
	
	public function checkMerchantAccount($merchantAccount) {
		return $this->_merchantAccount == $merchantAccount;
	}
	
	public function checkNotificationCredential($userName, $password) {
		return $this->_notificationUsername == $userName && $this->_notificationPassword == $password;
	}
	
	public function printNotificationDebugInfo() {
		return $this->_notificationDebugInfo == '1';
	}
	
	private function _getHmac() {
		return Configuration::get('ADYEN_MODE') == 'live' ? $this->_hmacLive : $this->_hmacTest;  
	}
	
	public function install() {
		// SQL Table
		if (!file_exists(dirname(__FILE__).'/sql/install.sql'))
			die('lol');
		elseif (!$sql = file_get_contents(dirname(__FILE__).'/sql/install.sql'))
			die('lal');
		$sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
		$sql = preg_split("/;\s*[\r\n]+/", $sql);
		foreach ($sql as $query) {
			if ($query AND sizeof($query)) {
				// Just run the query, if it is already inserted, no problem
				Db::getInstance()->Execute(trim($query));
			}
		}
		
		if (!parent::install()
			OR !Configuration::updateValue('ADYEN_MERCHANTACCOUNT', '')
			OR !Configuration::updateValue('ADYEN_MODE', 'test')
			OR !Configuration::updateValue('ADYEN_PAGE_TYPE', 'multiple')
			OR !Configuration::updateValue('ADYEN_SKINCODE', '')
			OR !Configuration::updateValue('ADYEN_HMAC_TEST', '')
			OR !Configuration::updateValue('ADYEN_HMAC_LIVE', '')
			OR !Configuration::updateValue('ADYEN_NOTIFICATION_USERNAME', 'adyen')
			OR !Configuration::updateValue('ADYEN_NOTIFICATION_PASSWORD', 'Pr*sTaS3o9')
			OR !Configuration::updateValue('ADYEN_NOTIFICATION_DEBUGINFO', '0')
			OR !Configuration::updateValue('ADYEN_SESSION_VALIDITY', '60')
			OR !Configuration::updateValue('ADYEN_SHIPPING_DATE', '5')
			OR !Configuration::updateValue('ADYEN_ONECLICK', '1')
			OR !$this->registerHook('payment')
			OR !$this->registerHook('paymentReturn'))
			return false;
		return true;
	}

	public function uninstall()	{
		if (!Configuration::deleteByName('ADYEN_MERCHANTACCOUNT')
			OR !Configuration::deleteByName('ADYEN_MODE')
			OR !Configuration::deleteByName('ADYEN_PAGE_TYPE')
			OR !Configuration::deleteByName('ADYEN_SKINCODE')
			OR !Configuration::deleteByName('ADYEN_HMAC_TEST')
			OR !Configuration::deleteByName('ADYEN_HMAC_LIVE')
			OR !Configuration::deleteByName('ADYEN_NOTIFICATION_USERNAME')
			OR !Configuration::deleteByName('ADYEN_NOTIFICATION_PASSWORD')
			OR !Configuration::deleteByName('ADYEN_NOTIFICATION_DEBUGINFO')
			OR !Configuration::deleteByName('ADYEN_SESSION_VALIDITY')
			OR !Configuration::deleteByName('ADYEN_SHIPPING_DATE')
			OR !Configuration::deleteByName('ADYEN_ONECLICK')
			OR !parent::uninstall())
			return false;
		return true;
	}

	public function getContent() {
		$this->_html = '<h2>Adyen</h2>';
		
		// process
		if ( isset($_POST['submitAdyenGlobal']) OR isset($_POST['submitAdyenPayment']) OR isset($_POST['submitAdyenNotification']) ) {
			$this->_checkValues();
			if (!sizeof($this->_postErrors)) {
				$this->_updateValues();
				$this->_displayConf();
			}
			else {
				$this->_displayErrors();
			}
		}

		$this->_displayAdyen();
		$this->_displayFormSettings();
		return $this->_html;
	}
	
	private function _updateValues() {
		if (isset($_POST['submitAdyenGlobal'])) {
			Configuration::updateValue('ADYEN_MERCHANTACCOUNT', strval($_POST['merchantAccount']));
			Configuration::updateValue('ADYEN_MODE', strval($_POST['mode']));
			Configuration::updateValue('ADYEN_SKINCODE', strval($_POST['skinCode']));
			Configuration::updateValue('ADYEN_HMAC_LIVE', strval($_POST['hmacLive']));
			Configuration::updateValue('ADYEN_HMAC_TEST', strval($_POST['hmacTest']));
		} elseif (isset($_POST['submitAdyenPayment'])) {
			$this->_oneClick = $_POST['oneClick'] ? '1' : '0';
			
			Configuration::updateValue('ADYEN_PAGE_TYPE', strval($_POST['pageType']));
			Configuration::updateValue('ADYEN_SESSION_VALIDITY', strval($_POST['sessionValidity']));
			Configuration::updateValue('ADYEN_SHIPPING_DATE', strval($_POST['shippingDate']));
			Configuration::updateValue('ADYEN_ONECLICK', $this->_oneClick);
		} elseif (isset($_POST['submitAdyenNotification'])) {
			$this->_notificationDebugInfo = $_POST['notificationDebugInfo'] ? '1' : '0';
			
			Configuration::updateValue('ADYEN_NOTIFICATION_USERNAME', strval($_POST['notificationUsername']));
			Configuration::updateValue('ADYEN_NOTIFICATION_PASSWORD', strval($_POST['notificationPassword']));
			Configuration::updateValue('ADYEN_NOTIFICATION_DEBUGINFO', $this->_notificationDebugInfo);
		}
	}
	
	private function _checkValues() {
		if (isset($_POST['submitAdyenGlobal'])) {
			if (empty($_POST['merchantAccount'])) {
				$this->_postErrors[] = $this->l('Adyen Merchant Account is required.');
			}
			
			if (empty($_POST['skinCode'])) {
				$this->_postErrors[] = $this->l('Adyen Skincode is required.');
			} elseif(strlen(strval($_POST['skinCode'])) != 8) {
				$this->_postErrors[] = $this->l('Adyen Skincode must be 8 long.');
			}
			
			if (empty($_POST['mode'])) {
				$this->_postErrors[] = $this->l('Adyen Mode is required.');
			} elseif($_POST['mode'] == 'live') {
				if (empty($_POST['hmacLive'])) {
					$this->_postErrors[] = $this->l('Adyen Mode is Live, but you have not configured the HMAC Key for Live.');
				}
			} elseif($_POST['mode'] == 'test') {
				if (empty($_POST['hmacTest'])) {
					$this->_postErrors[] = $this->l('Adyen Mode is Test, but you have not configured the HMAC Key for Test.');
				}
			} else {
				$this->_postErrors[] = $this->l('Adyen Mode is invalid.');
			}
		} elseif (isset($_POST['submitAdyenPayment'])) {
			if (empty($_POST['pageType'])) {
				$this->_postErrors[] = $this->l('Adyen Page Type is required.');
			} elseif($_POST['pageType'] != 'single' && $_POST['pageType'] != 'multiple') {
				$this->_postErrors[] = $this->l('Adyen Page Type is invalid.');
			}
			
			if (empty($_POST['sessionValidity'])) {
				$this->_postErrors[] = $this->l('Adyen Session Validity Time is required.');
			} 
			
			if (!isset($_POST['shippingDate'])) {
				$this->_postErrors[] = $this->l('Adyen Shipping Time is required.');
			} 
		} elseif (isset($_POST['submitAdyenNotification'])) {
			if (empty($_POST['notificationUsername'])) {
				$this->_postErrors[] = $this->l('Adyen Notification User Name is required.');
			} elseif(strlen(strval($_POST['notificationUsername'])) <= 4) {
				$this->_postErrors[] = $this->l('The Notification User Name should be at least 5 long.');
			}
			
			if (empty($_POST['notificationPassword'])) {
				$this->_postErrors[] = $this->l('Adyen Notification Password is required.');
			} elseif(strlen(strval($_POST['notificationPassword'])) <= 4) {
				$this->_postErrors[] = $this->l('The Notification Password should be at least 5 long.');
			}
		}
	}

	private function _displayConf() {
		$this->_html .= '
		<div class="conf confirm">
			<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
			'.$this->l('Settings updated').'
		</div>';
	}

	private function _displayErrors()	{
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>'.$error.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}
	
	
	private function _displayAdyen() {
		$this->_html .= '
		<img src="../modules/adyen/adyen.png" style="float:left; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by Adyen.').'</b><br /><br />
		'.$this->l('You need to configure your Adyen account first before using this module.').'
		<div style="clear:both;">&nbsp;</div>';
	}

	private function _displayFormSettings() {
		// Global settings
		$mode = array_key_exists('mode', $_POST) ? strval($_POST['mode']) : $this->_mode;
		$merchantAccount = array_key_exists('merchantAccount', $_POST) ? strval($_POST['merchantAccount']) : $this->_merchantAccount;
		$skinCode = array_key_exists('skinCode', $_POST) ? strval($_POST['skinCode']) : $this->_skinCode;
		$hmacTest = array_key_exists('hmacTest', $_POST) ? strval($_POST['hmacTest']) : $this->_hmacTest;
		$hmacLive = array_key_exists('hmacLive', $_POST) ? strval($_POST['hmacLive']) : $this->_hmacLive;
		
		// Payment settings
		$pageType = array_key_exists('pageType', $_POST) ? strval($_POST['pageType']) : $this->_pageType;
		$sessionValidity = array_key_exists('sessionValidity', $_POST) ? strval($_POST['sessionValidity']) : $this->_sessionValidity;
		$shippingDate = array_key_exists('shippingDate', $_POST) ? strval($_POST['shippingDate']) : $this->_shippingDate;
		$oneClick = array_key_exists('oneClick', $_POST) ? ($_POST['oneClick'] ? '1' : '0') : $this->_oneClick;
		
		// Notification settings
		$notificationUsername = array_key_exists('notificationUsername', $_POST) ? strval($_POST['notificationUsername']) : $this->_notificationUsername;
		$notificationPassword = array_key_exists('notificationPassword', $_POST) ? strval($_POST['notificationPassword']) : $this->_notificationPassword;
		$notificationDebugInfo = array_key_exists('notificationDebugInfo', $_POST) ? ($_POST['notificationDebugInfo'] ? '1' : '0') : $this->_notificationDebugInfo;
		
		// The normal settings
		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post" style="clear: both;">
		<fieldset>
			<legend><img src="../img/admin/edit.gif" />'.$this->l('General Settings').'</legend>
			<label style="width: 220px;">'.$this->l('Adyen Merchant Account').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="text" size="33" name="merchantAccount" value="'.htmlentities($merchantAccount, ENT_COMPAT, 'UTF-8').'" />
			</div>
			<label style="width: 220px;">'.$this->l('Adyen Skin Code').'</label>
			<div class="margin-form" style="padding-left: 230px;"><input type="text" size="10" maxsize="8" name="skinCode" value="'.htmlentities($skinCode, ENT_COMPAT, 'UTF-8').'" /></div>
			<label style="width: 220px;">'.$this->l('Adyen Mode').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="radio" name="mode" value="test" '.($mode=='test' ? 'checked="checked"' : '').' /> '.$this->l('Test').'
				<input type="radio" name="mode" value="live" '.($mode=='live' ? 'checked="checked"' : '').' /> '.$this->l('Live').'
			</div>
			<label style="width: 220px;">'.$this->l('HMAC Key for Test').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="text" size="33" name="hmacTest" value="'.htmlentities($hmacTest, ENT_COMPAT, 'UTF-8').'" />
			</div>
			<label style="width: 220px;">'.$this->l('HMAC Key for Live').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="text" size="33" name="hmacLive" value="'.htmlentities($hmacLive, ENT_COMPAT, 'UTF-8').'" />
			</div>
			<br /><center><input type="submit" name="submitAdyenGlobal" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form><br/>';
		
		// Payment Settings
		
		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post" style="clear: both;">
		<fieldset>
			<legend><img src="../img/admin/payment.gif" />'.$this->l('Payment Settings').'</legend>
			<label style="width: 220px;">'.$this->l('Adyen Payment Flow').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="radio" name="pageType" value="multiple" '.($pageType=='multiple' ? 'checked="checked"' : '').' /> '.$this->l('Multiple Payment Page').'
				<input type="radio" name="pageType" value="single" '.($pageType=='single' ? 'checked="checked"' : '').' /> '.$this->l('Single Payment Page').'
			</div>
			<label style="width: 220px;">'.$this->l('Payment Session Validity').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<select name="sessionValidity">
					<option value="15" '.($sessionValidity=='15' ? 'selected="selected"' : '').'>'.$this->l('15 minutes').'</option>
					<option value="30" '.($sessionValidity=='30' ? 'selected="selected"' : '').'>'.$this->l('30 minutes').'</option>
					<option value="60" '.($sessionValidity=='60' ? 'selected="selected"' : '').'>'.$this->l('1 hour').'</option>
					<option value="120" '.($sessionValidity=='120' ? 'selected="selected"' : '').'>'.$this->l('2 hours').'</option>
					<option value="180" '.($sessionValidity=='180' ? 'selected="selected"' : '').'>'.$this->l('3 hours').'</option>
					<option value="240" '.($sessionValidity=='240' ? 'selected="selected"' : '').'>'.$this->l('4 hours').'</option>
					<option value="300" '.($sessionValidity=='300' ? 'selected="selected"' : '').'>'.$this->l('5 hours').'</option>
					<option value="360" '.($sessionValidity=='360' ? 'selected="selected"' : '').'>'.$this->l('6 hours').'</option>
					<option value="1440" '.($sessionValidity=='1440' ? 'selected="selected"' : '').'>'.$this->l('1 day').'</option>
					<option value="2880" '.($sessionValidity=='2880' ? 'selected="selected"' : '').'>'.$this->l('2 days').'</option>
					<option value="5760" '.($sessionValidity=='5760' ? 'selected="selected"' : '').'>'.$this->l('4 days').'</option>
					<option value="10080" '.($sessionValidity=='10080' ? 'selected="selected"' : '').'>'.$this->l('1 week').'</option>
					<option value="20160" '.($sessionValidity=='20160' ? 'selected="selected"' : '').'>'.$this->l('2 weeks').'</option>
				</select>
			</div>
			<label style="width: 220px;">'.$this->l('Payment Shipping Time').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<select name="shippingDate">
					<option value="0" '.($shippingDate=='0' ? 'selected="selected"' : '').'>'.$this->l('0 days').'</option>
					<option value="1" '.($shippingDate=='1' ? 'selected="selected"' : '').'>'.$this->l('1 day').'</option>
					<option value="2" '.($shippingDate=='2' ? 'selected="selected"' : '').'>'.$this->l('2 days').'</option>
					<option value="3" '.($shippingDate=='3' ? 'selected="selected"' : '').'>'.$this->l('3 days').'</option>
					<option value="4" '.($shippingDate=='4' ? 'selected="selected"' : '').'>'.$this->l('4 days').'</option>
					<option value="5" '.($shippingDate=='5' ? 'selected="selected"' : '').'>'.$this->l('5 days').'</option>
					<option value="10" '.($shippingDate=='10' ? 'selected="selected"' : '').'>'.$this->l('10 days').'</option>
					<option value="20" '.($shippingDate=='20' ? 'selected="selected"' : '').'>'.$this->l('20 days').'</option>
					<option value="30" '.($shippingDate=='30' ? 'selected="selected"' : '').'>'.$this->l('30 days').'</option>
				</select>
			</div>
			<label style="width: 220px;">'.$this->l('Adyen One Click').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="checkbox" name="oneClick" value="1" '.($oneClick=='1' ? 'checked="checked"' : '').' />
				<p class="hint clear" style="display: block; width: 650px;">'.$this->l('By using One Click, a returning shopper does not have to fill in all his Card Details again, only is CVV code.').'</p>
			</div>
			<br /><br /><br /><center><input type="submit" name="submitAdyenPayment" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form><br/>';
		
		// Notification settings
		$this->_html .= '
		<form action="'.strval($_SERVER['REQUEST_URI']).'" method="post" style="clear: both;">
		<fieldset>
			<legend><img src="../img/admin/cog.gif" />'.$this->l('Notification Settings').'</legend>
			<label style="width: 220px;">'.$this->l('Notification User Name').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="text" size="33" name="notificationUsername" value="'.htmlentities($notificationUsername, ENT_COMPAT, 'UTF-8').'" />
			</div>
			<label style="width: 220px;">'.$this->l('Notification Password').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="text" size="33" name="notificationPassword" value="'.htmlentities($notificationPassword, ENT_COMPAT, 'UTF-8').'" />
			</div>
			<label style="width: 220px;">'.$this->l('Echo Notification Debug Info').'</label>
			<div class="margin-form" style="padding-left: 230px;">
				<input type="checkbox" name="notificationDebugInfo" value="1" '.($notificationDebugInfo=='1' ? 'checked="checked"' : '').' />
				<p class="hint clear" style="display: block; width: 650px;">'.$this->l('By activating this option, the accepted reply for the Adyen Notification will contain some extra debug info. This should only be used for a short time, because this can be used for example to retrieve your username/password.').'</p>
			</div>
			<br /><br /><br /><center><input type="submit" name="submitAdyenNotification" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form>';
		
		// Information
		$this->_html .='<div style="clear:both;"><br /></div>
		<fieldset>
			<legend><img src="../img/admin/unknown.gif" />'.$this->l('Adyen Skin Settings').'</legend>
			'.$this->l('Follow these steps in order to get the Prestashop plugin working with Adyen.').'<br />
			'.$this->l('1. Log in to the Adyen CA Test Interface.').'<br />
			'.$this->l('2. Click on Skins.').'<br />
			'.$this->l('3. Click on \'New\' to create a new skin, or on the Skin Code of an existing skin.').'<br />
			'.$this->l('4. Fill in the \'Description\' field.').'<br />
			'.$this->l('5. Fill in the \'HMAC Key for Test\' field, and configure this value also in the Adyen Prestashop plugin under \'HMAC Key for Test\'. The value itself does not matter.').'<br />
			'.$this->l('6. Fill in the \'HMAC Key for Live\' field, and configure this value also in the Adyen Prestashop plugin under \'HMAC Key for Live\'. The value itself does not matter, but it must be different than the value for Test.').'<br />
			'.$this->l('7. Make sure that the option \'Use deprecated back-button behaviour\' is NOT selected.').'<br />
			'.$this->l('8. Select the Merchant Account under \'Valid Accounts\' that you want to use, and configure this value also in the Adyen Prestashop plugin under \'Merchant Account\'.').'<br />
			'.$this->l('9. Press the \'Save Skin to Test\' button.').'<br />
			'.$this->l('10. You will have to wait for a couple of minutes before your change take effect.').'
			</fieldset>';
			
		$this->_html .='<div style="clear:both;"><br /></div>
		<fieldset>
			<legend><img src="../img/admin/unknown.gif" />'.$this->l('Moving to Live').'</legend>
			'.$this->l('If you want to use the Adyen Live Environment, you will have to publish the skin to live via the \'Publish\' tab and pressing the \'Publish to Live\' button.').'<br />
			'.$this->l('Make sure that you have also set \'Adyen Mode\' of the Adyen Prestashop plugin to \'Live\'.').'
		</fieldset>';
		
		$this->_html .='<div style="clear:both;"><br /></div>
		<fieldset>
			<legend><img src="../img/admin/unknown.gif" />'.$this->l('Adyen Notification Settings').'</legend>
			'.$this->l('Follow these steps in order to configure the Notification functionality for the Prestashop plugin working with Adyen.').'<br />
			'.$this->l('1. Log in to the Adyen CA Test Interface.').'<br />
			'.$this->l('2. Click on Settings.').'<br />
			'.$this->l('3. Click on Notificatons.').'<br />
			'.$this->l('4. If you are not on a Merchant Account level yet, select the Merchant Account that is configured under the Adyen Prestashop plugin under \'Merchant Account\'.').'<br />
			'.$this->l('5. Set the \'URL\' to ').(Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/adyen/notification.php'.'<br />
			'.$this->l('6. Select the \'Active\' checkbox.').'<br />
			'.$this->l('7. Set the \'Method\' to \'HTTP\'.').'<br />
			'.$this->l('8. Fill in the \'User Name\' field, and configure this value also in the Adyen Prestashop plugin under \'Notification User Name\'.').'<br />
			'.$this->l('9. Fill in the \'Password\' field, and configure this value also in the Adyen Prestashop plugin under \'Notification Password\'.').'<br />
			'.$this->l('10.Press the \'Save Settings\' button.').'
			</fieldset>';
	}


	public function confirmPayment($cart) {
		if (!$this->active)
			return ;

		global $cookie, $smarty;

		$smarty->assign(array(
			'currency' => $this->getCurrency(),
			'total' => number_format($cart->getOrderTotal(true, 3), 2, '.', ''),
			'this_path' => $this->_path,
			'this_path_ssl' => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/'
		));

		return $this->display(__FILE__, 'confirm.tpl');
	}

	public function hookPayment($params) {
		if (!$this->active)
			return ;

		global $smarty;

		$smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}


	public function execPayment($cart) {
		if (!$this->active)
			return ;

		global $smarty;

		$customer = new Customer(intval($cart->id_customer));
		$address = new Address(intval($cart->id_address_invoice));
		$country = new Country(intval($address->id_country));
		$language = Language::getIsoById($cart->id_lang);
		$currency = $this->getCurrency();

		if (empty($this->_merchantAccount)) {
			return $this->l('Adyen error: (undefined Merchant Account)');
		}
		
		if (!Validate::isLoadedObject($address) OR !Validate::isLoadedObject($customer) OR !Validate::isLoadedObject($currency)) {
			return $this->l('Adyen error: (invalid address, customer, or currency)');
		}
		
		$merchantAccount	= $this->_merchantAccount;
		$skinCode 			= $this->_skinCode;
		$currencyCode		= $currency->iso_code; 
		$shopperEmail		= $customer->email;
		$merchantReference	= $this->currentOrder;
		$paymentAmount		= number_format(Tools::convertPrice($cart->getOrderTotal(true, 3), $currency), 2, '', '');
		$shopperReference	= $customer->secure_key; 
		$countryCode		= $country->iso_code;
		$shopperLocale		= $language; // Locale (language) to present to shopper (e.g. en_US, nl, fr, fr_BE)
		$recurringContract	= $this->_oneClick ? "ONECLICK" : "";
		$shipBeforeDate 	= date("Y-m-d" , mktime(date("H"), date("i"), date("s"), date("m"), date("j")+(isset($this->_shippingDate) ? $this->_shippingDate : 5), date("Y"))); // example: ship in 5 days
		$sessionValidity	= date(DATE_ATOM	, mktime(date("H"), date("i")+($this->_sessionValidity ? $this->_sessionValidity : 30), date("s"), date("m"), date("j"), date("Y"))); // example: shopper has one hour to complete
		// TODO => a nice presentation of the shopping basket.
		$orderDataRaw	   = ""; // A description of the payment which is displayed to shoppers
		
		$orderData = base64_encode($this->_alt_gzdecode($orderDataRaw));
		$hmacData =  $paymentAmount . $currencyCode . $shipBeforeDate . $merchantReference . $skinCode . $merchantAccount . $sessionValidity . $shopperEmail . $shopperReference . $recurringContract;
		$merchantSig = base64_encode(pack('H*',$this->_hmacsha1($this->_getHmac(),$hmacData)));
		
		$smarty->assign(array(
			'merchantAccount' 	=> $merchantAccount,
			'skinCode' 			=> $skinCode,
			'currencyCode' 		=> $currencyCode,
			'shopperEmail' 		=> $shopperEmail,
			'merchantReference'	=> $merchantReference,
			'paymentAmount' 	=> $paymentAmount,
			'shopperReference' 	=> $shopperReference,
			'shipBeforeDate' 	=> $shipBeforeDate,
			'sessionValidity' 	=> $sessionValidity,
			'shopperLocale' 	=> $shopperLocale,
			'countryCode' 		=> $countryCode,
			'orderData' 		=> $orderData,
			'recurringContract'	=> $recurringContract,
			'merchantSig' 		=> $merchantSig,
			'adyenUrl' 			=> $this->getAdyenUrl(),
			'resURL' 			=> (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.intval($cart->id).'&id_module='.intval($this->id)
		));
		
		return $this->display(__FILE__, 'adyen.tpl');
	}

	public function hookPaymentReturn($params) {
		if (!$this->active)
			return ;
		// Validate the details in the result url.
		$authResult = $_GET['authResult'];
		$pspReference = $_GET['pspReference'];
		$merchantReference = $_GET['merchantReference'];
		$skinCode = $_GET['skinCode'];
		$merchantSig = $_GET['merchantSig'];
		
		// Calculate the merchant signature from the return values.
		$hmacData =  $authResult . $pspReference . $merchantReference . $skinCode;
		$calculatedMerchantSig = base64_encode(pack('H*',$this->_hmacsha1($this->_getHmac(),$hmacData)));
		
		// Both values must be the same.
		$id_order_state = _PS_OS_ERROR_;
		$template = 'error.tpl';
		if($merchantSig == $calculatedMerchantSig && $merchantReference == $params['objOrder']->id) {
			switch ($authResult) {
				case 'AUTHORISED':
					$id_order_state = _PS_OS_PAYMENT_;
					$template = 'authorised.tpl';
					break;
				case 'PENDING':
					$id_order_state = _PS_OS_ADYEN_WAITING_;
					$template = 'pending.tpl';
					break;
				case 'REFUSED':
					$id_order_state = _PS_OS_ADYEN_REFUSED_;
					$template = 'refused.tpl';
					break;
				case 'CANCELLED':
					$id_order_state = _PS_OS_CANCELED_;
					$template = 'cancelled.tpl';
					break;
				default:
					break;
			}
		}
		// Check if we have to update the status of the order
		$this->updateOrderStatus($params['objOrder']->id,$id_order_state);
		
		return $this->display(__FILE__, 'result/'.$template);
	}
	
	public function updateOrderStatus($id_order,$id_order_state) {
		$order = new Order(intval($id_order));
		// If we have a new status, update.
		if(!sizeof($order->getHistory(intval($order->id_lang), $id_order_state))) {
			$history = new OrderHistory();
			$history->id_order = intval($id_order);
			$history->changeIdOrderState(intval($id_order_state), intval($id_order));
			$history->addWithemail(true, false);
		}
	}
	
	public function addMessage($id_order, $messageData) {
		$message = new Message();
		$message->id_employee = 0;
		$message->message = $messageData;
		$message->id_order = $id_order;
		$message->private = '1';
		$message->add();				
	}
	
	/**
	 * Return a link to the adyen CA interface with the payment details.
	 */
	public function getAdyenPaymentUrl($pspReference, $merchantAccount, $live) {
		return 'https://ca-'.($live=='true'?'live':'test').'.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=' . $pspReference .'&amp;txType=Payment&amp;accountKey=MerchantAccount.'.$merchantAccount;
	}
	
	private function _hmacsha1($key,$data) {
		$blocksize=64;
		$hashfunc='sha1';
		if (strlen($key)>$blocksize) {
			$key=pack('H*', $hashfunc($key));
		}
		$key=str_pad($key,$blocksize,chr(0x00));
		$ipad=str_repeat(chr(0x36),$blocksize);
		$opad=str_repeat(chr(0x5c),$blocksize);
		$hmac = pack(
			'H*',$hashfunc(
				($key^$opad).pack(
					'H*',$hashfunc(
						($key^$ipad).$data
					)
				)
			)
		);
		return bin2hex($hmac);
	}
	
	private function _alt_gzdecode($str) {
		// seed with microseconds since last "whole" second
		mt_srand((float)microtime()*1000000);
		$eh="/tmp/php-" . md5(mt_rand(0,mt_getrandmax())) . ".gz";
	
		$fd=fopen($eh,"w");
		fwrite($fd,$str);
		fclose($fd);
		unset($str);
		$fd = gzopen ($eh, "r");
		while (1==1) {
			$s=gzread($fd,10240);
			if ("$s" == "") {
				break;
			}
			$str=$str . $s;
		}
		unlink($eh);
		return $str;
	}
	
	/**
	 * 
	 * Notifications
	 * 
	 */
	
	public function getNotificationsByOrderId($id_order) {
	 	global $cookie;
		$result = Db::getInstance()->ExecuteS('
			SELECT *
			FROM `'._DB_PREFIX_.'adyen_notification`
			WHERE id_order = '.intval($id_order).'
			GROUP BY pspReference
			ORDER BY eventDate DESC
		');
		return $result;
	}
	
	public function insertNotification($id_order, $notification) {
		return Db::getInstance()->Execute('
			INSERT INTO `'._DB_PREFIX_.'adyen_notification` (`id_order`, `merchantReference`,`pspReference`,`eventDate`,`eventCode`,`live`,`success`,`paymentMethod`,`reason`,`currency`,`value` )
			VALUES('.intval($id_order).', ' .
					'\''.pSQL($notification['merchantReference']).'\',' .
					'\''.pSQL($notification['pspReference']).'\',' .
					'\''.pSQL($notification['eventDate']).'\',' .
					'\''.pSQL($notification['eventCode']).'\',' .
					'\''.($notification['live']=='true' ? '1' : '0').'\',' .
					'\''.($notification['success']=='true' ? '1' : '0').'\',' .
					'\''.pSQL($notification['paymentMethod']).'\',' .
					'\''.pSQL($notification['reason']).'\',' .
					'\''.pSQL($notification['currency']).'\',' .
					''.intval($notification['value']).')');
	}
	
	public function updateNotification($id_order, $notification) {
		return Db::getInstance()->Execute('
			UPDATE `'._DB_PREFIX_.'adyen_notification` SET ' .
					'`success`= \''.($notification['success']=='true' ? '1' : '0').'\' ' .
				'WHERE `id_order` = '.intval($id_order).' AND `pspReference` \''.pSQL($notification['pspReference']).'\'');
	}
}
?>