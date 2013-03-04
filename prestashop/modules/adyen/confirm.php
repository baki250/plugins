<?php
/**
 * @author Adyen <support@adyen.com>
 * @copyright Adyen B.V.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GPL 2.0
 */

/* SSL Management */
$useSSL = true;

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/adyen.php');

if (!$cookie->isLogged())
    Tools::redirect('authentication.php?back=order.php');
$adyen = new Adyen();
echo $adyen->confirmPayment($cart);

include_once(dirname(__FILE__).'/../../footer.php');
?>