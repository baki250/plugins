<?php
/**
 * @author Adyen <support@adyen.com>
 * @copyright Adyen B.V.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GPL 2.0
 */
 
include_once(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/../../init.php');

include_once(_PS_MODULE_DIR_.'adyen/adyen.php');

$adyen = new Adyen();

$resultMessage = _PS_ADYEN_NOTIFICATION_;

$userName = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : "";
$password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : "";

// Check login credentials
if(!$adyen->checkNotificationCredential($userName,$password)) {
	if($adyen->printNotificationDebugInfo()) {
		$resultMessage .= ' - Invalide Username/Password: '.$userName.'/'.$password;
	}
	die($resultMessage);
}

// Get all the required fields from the notification

// The merchant account must be the same as the one configured
$merchantAccountCode = Tools::getValue('merchantAccountCode');
if(!$adyen->checkMerchantAccount($merchantAccountCode)) {
	if($adyen->printNotificationDebugInfo()) {
		$resultMessage .= ' - Invalide merchantAccount: '.$merchantAccountCode;
	}
	die($resultMessage);
}

// Currently we only check on eventCode AUTHORISATION
$eventCode = Tools::getValue('eventCode');
if($eventCode != 'AUTHORISATION') {
	if($adyen->printNotificationDebugInfo()) {
		$resultMessage .= ' - Not supported eventCode: '.$eventCode;
	}
	die($resultMessage);
}

// Try to find the order, based on the merchantReference
$merchantReference = Tools::getValue('merchantReference');

$order = new Order(intval($merchantReference));
if (!Validate::isLoadedObject($order) OR !$order->id) {
	if($adyen->printNotificationDebugInfo()) {
		$resultMessage .= ' - Can not find an order for merchantReference: '.$merchantReference;
	}
	die($resultMessage);
}

$pspReference = Tools::getValue('pspReference');
$success = Tools::getValue('success');
$live = Tools::getValue('live');
$eventDate = Tools::getValue('eventDate');
$paymentMethod = Tools::getValue('paymentMethod');

// Get all the adyen notifications for this order
$notifications = $adyen->getNotificationsByOrderId($order->id);
// Check if we already have this notification
$storedNotification = null;
foreach($notifications as $notification) {
	if($notification['eventCode'] == $eventCode 
		&& $notification['pspReference'] == $pspReference) {
		$storedNotification = $notification;
	}
}

$href = $adyen->getAdyenPaymentUrl($pspReference,$merchantAccountCode,$live);
	$message = '<b>Reveived payment from Adyen:</b><br/>' .
		'date: '.$eventDate.'<br/>' .
		'pspReference: <a href="'.$href.'" target="_blank"><b>'.$pspReference.'</b></a><br/>' .
		'Result: '.($success == 'true' ? 'AUTHORISED':'REFUSED').'<br/>' .
		'Payment Method: '.$paymentMethod;

if(is_null($storedNotification)) {
	$adyen->insertNotification($order->id,$_POST);
	// update the status of the payment, if this is not done yet.
	$id_order_state = _PS_OS_ADYEN_REFUSED_;
	if($success == 'true') {
		$id_order_state = _PS_OS_PAYMENT_;
	}
	$adyen->updateOrderStatus($order->id, $id_order_state);
	$adyen->addMessage($order->id, $message);
	if($adyen->printNotificationDebugInfo()) {
		$resultMessage .= ' - Entry inserted with pspReference '.$pspReference;
	}
	die($resultMessage);
} elseif($storedNotification['success'] == '0' && $success == 'true') {
	// First we had a payment that was refused, but now it is authorised (should not happen!)
	$adyen->updateNotification($order->id,$_POST);
	$id_order_state = _PS_OS_PAYMENT_;
	$adyen->updateOrderStatus($order->id, $id_order_state);
	$adyen->addMessage($order->id, $message);
	if($adyen->printNotificationDebugInfo()) {
		$resultMessage .= ' - Entry updated with pspReference '.$pspReference;
	}
	die($resultMessage);
}

if($adyen->printNotificationDebugInfo()) {
	$resultMessage .= ' - Duplicate notification';
}
die($resultMessage);
?>