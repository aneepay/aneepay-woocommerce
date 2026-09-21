{*
 * AneePay checkout conversion breakdown, shown under the payment method radio
 * on the payment step (hookPaymentOptions -> setAdditionalInformation).
 *}
{literal}<style>
.aneepay-checkout-breakdown{margin:8px 0 0;padding:8px 10px;background:#f7f8f9;border:1px solid #e0e1e2;border-radius:4px;font-size:12px;line-height:1.5;}
.aneepay-checkout-breakdown .aneepay-breakdown-title{margin:0 0 4px;font-weight:600;}
.aneepay-checkout-breakdown ul{margin:0;padding:0 0 0 14px;}
.aneepay-checkout-breakdown .aneepay-breakdown-source{color:#8a8a8a;}
</style>{/literal}
{if isset($aneepay_breakdown)}
<div class="aneepay-checkout-breakdown">
	<p class="aneepay-breakdown-title">{l s='You will pay %1$s %2$s' sprintf=[$aneepay_breakdown.token_amount, $aneepay_breakdown.token] mod='ps_aneepay'}</p>
	<ul>
		<li>{l s='Order: %1$s (%2$s)' sprintf=[$aneepay_breakdown.order_label, $aneepay_breakdown.currency] mod='ps_aneepay'}</li>
		<li>{l s='USD equivalent: %s' sprintf=[$aneepay_breakdown.usd_label] mod='ps_aneepay'}</li>
		{if $aneepay_breakdown.show_rate}
			<li>{l s='1 %1$s = %2$s USD' sprintf=[$aneepay_breakdown.currency, $aneepay_breakdown.usd_per_fiat] mod='ps_aneepay'}</li>
		{/if}
		<li class="aneepay-breakdown-source">{$aneepay_breakdown.rate_source}</li>
	</ul>
</div>
{/if}