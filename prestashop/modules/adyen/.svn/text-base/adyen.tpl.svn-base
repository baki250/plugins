<p class="payment_module">
	<a href="javascript:$('#adyen_form').submit();">{l s='You will now be redirected to Adyen. If this does not happen automatically, please press here.' mod='adyen'}</a>
</p>

<form id="adyen_form" action="{$adyenUrl}" method="post">
		<input type="hidden" name="merchantAccount"   value="{$merchantAccount}" />
		<input type="hidden" name="currencyCode"      value="{$currencyCode}" />
		<input type="hidden" name="skinCode"          value="{$skinCode}" />
		<input type="hidden" name="shopperEmail"      value="{$shopperEmail}" />
		<input type="hidden" name="merchantReference" value="{$merchantReference}" />
		<input type="hidden" name="paymentAmount"     value="{$paymentAmount}" />
		<input type="hidden" name="shopperReference"  value="{$shopperReference}" />
		<input type="hidden" name="shipBeforeDate"    value="{$shipBeforeDate}" />
		<input type="hidden" name="sessionValidity"   value="{$sessionValidity}" />
		<input type="hidden" name="shopperLocale"     value="{$shopperLocale}" />
		<input type="hidden" name="countryCode"       value="{$countryCode}" />
		<input type="hidden" name="recurringContract" value="{$recurringContract}" />
		<input type="hidden" name="merchantSig"       value="{$merchantSig}" />
		<input type="hidden" name="orderData"         value="{$orderData}" />
		<input type="hidden" name="resURL"            value="{$resURL}" />
</form>
<script type="text/javascript">
	$('#adyen_form').submit();
</script>