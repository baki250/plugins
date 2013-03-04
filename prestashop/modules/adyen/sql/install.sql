INSERT INTO `PREFIX_order_state` (`id_order_state`, `invoice`, `send_email`, `color`, `unremovable`, `logable`, `delivery`) VALUES
(20, 0, 1, 'lightblue', 1, 0, 0),
(21, 0, 0, 'lightblue', 1, 0, 0),
(22, 0, 0, 'lightblue', 1, 0, 0);

INSERT INTO `PREFIX_order_state_lang` (`id_order_state`, `id_lang`, `name`, `template`) VALUES
(20, 1, 'Adyen - Redirected', 'adyen_redirect'),
(21, 1, 'Adyen - Awaiting payment', ''),
(22, 1, 'Adyen - Payment Refused', '');



 CREATE TABLE `PREFIX_adyen_notification` (
`id_order` INT( 10 ) NOT NULL ,
`merchantReference` VARCHAR( 50 ) NOT NULL ,
`pspReference` VARCHAR( 255 ) NOT NULL ,
`eventDate` VARCHAR( 50 ) NOT NULL ,
`eventCode` VARCHAR( 100 ) NOT NULL ,
`live` ENUM( '1', '0' ) NOT NULL ,
`success` ENUM( '1', '0' ) NOT NULL ,
`paymentMethod` VARCHAR( 100 ) NOT NULL ,
`reason` VARCHAR( 255 ) NOT NULL ,
`currency` VARCHAR( 10 ) NOT NULL ,
`value` INT( 10 ) NOT NULL ,
INDEX ( `id_order` , `pspReference` )
) ENGINE = MYISAM 
