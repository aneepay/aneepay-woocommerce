{*
 * AneePay pending-payment screen: shows the token amount and the hosted-checkout
 * link, polls the status via check.php, reloads to success/fail on a terminal
 * status. Shown by controllers/front/payment.php.
 *}
{literal}<style>
.aneepay-pending{max-width:560px;margin:40px auto;text-align:center;}
.aneepay-pending-card{border:1px solid #e0e0e0;border-radius:8px;padding:32px 24px;background:#fff;}
.aneepay-pending-spinner{width:40px;height:40px;margin:0 auto 16px;border:4px solid #e6e6e6;border-top-color:#2f6fed;border-radius:50%;animation:aneepay-spin 1s linear infinite;}
@keyframes aneepay-spin{to{transform:rotate(360deg);}}
.aneepay-pending-amount{font-size:1.35em;margin:12px 0;}
.aneepay-pending-meta{color:#777;}
.aneepay-pending-info{color:#555;margin:12px 0 20px;}
.aneepay-pending-actions{margin:16px 0;}
.aneepay-pending-actions .btn{display:inline-block;margin:4px;}
</style>{/literal}
<div class="aneepay-pending">
	<div class="aneepay-pending-card">
		<div class="aneepay-pending-spinner"></div>

		<h2>{l s='Waiting for the payment to be confirmed' mod='ps_aneepay'}</h2>
		<p>{l s='This page updates automatically. Once you have completed the payment, press the button below to refresh the status instantly.' mod='ps_aneepay'}</p>

		{if $pay_now}
			<p class="aneepay-pending-amount">{l s='Amount' mod='ps_aneepay'}: <strong>{$pay_now}</strong></p>
		{/if}

		{if $network}
			<p class="aneepay-pending-meta">{l s='Network' mod='ps_aneepay'}: {$network}{if $sandbox} &middot; {l s='testnet' mod='ps_aneepay'}{/if}</p>
		{/if}

		<p class="aneepay-pending-info">{l s='Payments are non-custodial: the funds go directly from your wallet to the merchant, minus a fixed 0.5% fee. No account or KYC is required.' mod='ps_aneepay'}</p>

		<div class="aneepay-pending-actions">
			<a href="{$checkout_url}" target="_blank" rel="noopener" class="btn btn-primary btn-lg">{l s='Pay now' mod='ps_aneepay'}</a>
			<button type="button" class="btn btn-default" id="aneepay-i-paid">{l s='I already paid' mod='ps_aneepay'}</button>
		</div>

		<p><a href="{$shop_url}">{l s='Back to shop' mod='ps_aneepay'}</a></p>
	</div>
</div>

<script>
(function () {
	'use strict';
	var checkUrl = '{$check_url}';
	var successUrl = '{$success_url}';
	var failUrl = '{$fail_url}';
	var orderId = {$order_id};
	var attempts = 0;
	var btn = document.getElementById('aneepay-i-paid');
	var interval;

	function redirect(status) {
		if (status === 'success') {
			window.location.href = successUrl;
		} else if (status === 'failed' || status === 'cancelled') {
			window.location.href = failUrl;
		}
	}

	function poll() {
		if (attempts >= 40) {
			clearInterval(interval);
			return;
		}
		attempts++;
		var b = new URLSearchParams();
		b.append('id_order', orderId);
		fetch(checkUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: b.toString()
		}).then(function (r) { return r.json(); }).then(function (d) {
			if (d && (d.status === 'success' || d.status === 'failed' || d.status === 'cancelled')) {
				clearInterval(interval);
				redirect(d.status);
			} else if (d && d.status === 'not_found') {
				clearInterval(interval);
			}
		}).catch(function () {});
	}

	if (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			btn.textContent = '{l s='Checking…' mod='ps_aneepay'}';
			poll();
		});
	}

	interval = setInterval(poll, 5000);
	poll();
})();
</script>