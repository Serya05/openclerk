<?php
$account_data = array('exchange_name' => get_exchange_name('justcoin'));
?>

<div class="instructions_add">
<h2>Adding a <?php echo $account_data['exchange_name']; ?> account</h2>

<ol class="steps">
	<li>Log into <a href="https://justcoin.com/client/#settings/profile"><?php echo $account_data['exchange_name']; ?></a> account and visit your <i>Account Settings</i>.<br>
		<img src="<?php echo htmlspecialchars(url_for('img/accounts/justcoin1.png')); ?>"></li>

	<li>Visit your <a href="https://justcoin.com/client/#settings/apikeys">API keys</a> section.<br>
		<img src="<?php echo htmlspecialchars(url_for('img/accounts/justcoin2.png')); ?>"></li>

	<li>Click on the <i>New API key</i> button to create a new API key.<br>
		<img src="<?php echo htmlspecialchars(url_for('img/accounts/justcoin3.png')); ?>"></li>

	<li>Make sure that the API key <i>does not have any permissions</i>. An API key without any permissions will still have access
		to retrieve account balances. Click on <i>Create API key</i>.<br>
		<img src="<?php echo htmlspecialchars(url_for('img/accounts/justcoin4.png')); ?>"></li>

	<li>Copy and paste the <i>API Key</i> into the <a class="wizard_link" href="<?php echo htmlspecialchars(url_for('wizard_accounts_exchanges')); ?>">"Add new Exchange" form</a>, and click "Add account".<br>
		<img src="<?php echo htmlspecialchars(url_for('img/accounts/justcoin5.png')); ?>"></li>
</ol>
</div>

<div class="instructions_safe">
<h2>Is it safe to provide <?php echo htmlspecialchars(get_site_config('site_name')); ?> a <?php echo $account_data['exchange_name']; ?> API key?</h2>

<ul>
	<li>You need to make sure that the API key <em>does not</em> have any of the trade, deposit or withdraw permissions. This should
		mean that the API key can only be used to retrieve account status, and it should not be possible
		to perform trades or withdraw funds using that key.</li>

	<li>Your <?php echo $account_data['exchange_name']; ?> API keys will <i>never</i> be displayed on the <?php echo htmlspecialchars(get_site_config('site_name')); ?>
		site, even if you have logged in.</li>

	<li>Through the <?php echo $account_data['exchange_name']; ?> interface you can revoke an API key&apos;s access at any time by
		going to your <a href="https://justcoin.com/client/#settings/apikeys">API keys</a> and clicking on the <i>Delete</i> button.<br>
		<img src="<?php echo htmlspecialchars(url_for('img/accounts/justcoin_delete.png')); ?>"></li>
</ul>
</div>