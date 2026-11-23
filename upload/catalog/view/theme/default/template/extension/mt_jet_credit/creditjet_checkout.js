jQuery(function($) {
	if ($.data(document, 'jetCheckoutMounted')) return;
	$.data(document, 'jetCheckoutMounted', true);

	const financialFields = ['priceall', 'total_credit_price', 'vnoska', 'gpr', 'glp', 'obshto'];
	const messages = {
		rate_mismatch: 'За избраните условия продуктите в количката не могат да бъдат закупени заедно на изплащане. Моля, изберете друг срок или условия.\n\nАко желаете, можете да закупите на изплащане всеки продукт поотделно.',
		term_prohibited: 'За избрания срок има продукт, който не може да бъде финансиран.',
		term_not_allowed: 'Избраният срок не е достъпен за тази сума.',
		invalid_down_payment: 'Въведете валидна първоначална вноска, по-малка от цената на стоките.',
		insufficient_installment: 'Месечната вноска трябва да бъде поне 20 евро.',
		stale_order: 'Поръчката е променена. Потвърдете отново начина на плащане.',
		payment_method_changed: 'Избраният начин на плащане е променен. Потвърдете отново избора си.',
		stale_context: 'Данните за финансиране са променени. Преизчислете.',
		invalid_applicant_email: 'Проверете адреса за електронна поща.',
		stale_quote: 'Офертата е изтекла или условията са променени. Преизчислете.',
		quote_mismatch: 'Условията на заявката се различават от офертата. Преизчислете.',
		submission_in_progress: 'Заявката се обработва.',
		already_processed: 'Тази заявка вече е обработена.',
		submission_uncertain: 'Резултатът от изпращането е неясен. Свържете се с магазина, преди да опитате отново.',
		transport_failed: 'Заявката не беше приета за изпращане. Преизчислете и опитайте отново.',
		submission_unavailable: 'Изпращането в момента е недостъпно.',
		network: 'Калкулацията не е достъпна в момента. Моля, преизчислете.',
		submission: 'Заявката за лизинг не може да бъде изпратена в момента. Моля, опитайте по-късно.'
	};
	const checkoutRoutes = /[?&]route=(?:checkout\/(?:confirm|(?:payment_address|shipping_address|shipping_method|payment_method|guest|guest_shipping|register|login)\/save|cart\/(?:add|edit|remove))|extension\/total\/(?:coupon\/coupon|voucher\/voucher|shipping\/shipping|reward\/reward))(?=&|$)/;
	const pending = new Set();
	let instance;

	function mount() {
		const root = document.querySelector('[data-jet-checkout]');
		if (instance && instance.root === root) return;
		if (instance) instance.destroy();
		instance = root ? createInstance(root) : null;
		if (instance && !pending.size) instance.start();
	}

	$(document).on('ajaxSend.jetCheckout', function(event, xhr, settings) {
		if (!checkoutRoutes.test(settings.url.split('#')[0].replace(/%2f/gi, '/'))) return;
		pending.add(xhr);
		if (instance) instance.invalidate('stale_context');
	}).on('ajaxComplete.jetCheckout', function(event, xhr) {
		if (pending.delete(xhr) && !pending.size) {
			mount();
			if (instance) instance.start();
		}
	}).on('change.jetCheckout', '#collapse-payment-method input[name="payment_method"]', function() {
		if (instance && instance.channel !== this.value) instance.invalidate('payment_method_changed');
	});
	$(window).on('focus.jetCheckout pageshow.jetCheckout', function(event) {
		if (event.type === 'pageshow' && !(event.originalEvent && event.originalEvent.persisted)) return;
		if (instance && !pending.size) instance.recheck();
	});

	new MutationObserver(mount).observe(document.body, { childList: true, subtree: true });
	mount();

	function createInstance(root) {
		const $root = $(root);
		const channel = root.getAttribute('data-jet-checkout');
		const order = root.getAttribute('data-jet-order');
		const prefix = channel + '_';
		const field = name => $root.find('#' + prefix + name);
		const confirm = $root.find('#button-confirm');
		const confirmLabel = confirm.val();
		const term = () => field('vnoski').val();
		const payment = () => field('parva').val();
		let revision = 0;
		let scheduled;
		let context;
		let offer;
		let started = false;
		let sending = false;

		const alive = () => document.documentElement.contains(root);
		const methodMatches = () => {
			const selected = document.querySelector('#collapse-payment-method input[name="payment_method"]:checked');
			return !selected || selected.value === channel;
		};
		const current = () => offer && offer.term === term() && offer.payment === payment() && methodMatches();
		const updateButton = () => {
			confirm.prop('disabled', !current() || sending || pending.size > 0
				|| !field('uslovia').is(':checked') || !field('uslovia1').is(':checked') || !field('egn').val());
		};
		const information = (reason, dialog = false) => {
			const message = reason && (messages[reason] || 'Финансирането не е достъпно за текущия избор.');
			const informationDialog = dialog && window.JetInformation;
			const inlineMessage = informationDialog ? '' : (message || '');
			$root.find('[data-jet-information]').prop('hidden', !inlineMessage).text(inlineMessage);
			if (message && informationDialog) {
				informationDialog.show(root, message, root.querySelector('#' + prefix +
					(reason === 'invalid_down_payment' ? 'parva' : 'vnoski')));
			}
		};
		const invalidate = reason => {
			revision++;
			clearTimeout(scheduled);
			offer = null;
			financialFields.forEach(name => {
				field(name).text('');
				field(name + '_input').val('');
			});
			information(reason);
			updateButton();
		};
		const calculate = (mode, preserve = false) => {
			invalidate();
			if (pending.size || sending) return;
			if (!methodMatches()) {
				information('payment_method_changed');
				return;
			}

			const selectedTerm = term();
			const down = payment();
			if (mode === 'explicit' && !context) {
				calculate('initial', true);
				return;
			}
			const revisionAtSend = revision;
			const data = { mode, channel, order };
			if (mode === 'explicit') Object.assign(data, { term: selectedTerm, down_payment: down, context });
			const stillCurrent = () => alive() && revisionAtSend === revision && methodMatches()
				&& term() === selectedTerm && payment() === down;
			$.ajax({
				url: 'index.php?route=extension/mt_jet_credit/jetcredit/jet_checkout_offer',
				method: 'POST',
				dataType: 'json',
				data,
				success: json => {
					if (!stillCurrent()) return;
					const result = json && json.offer;
					if (!result || json.mode !== mode || result.channel !== channel
						|| (mode === 'explicit' && String(result.term) !== selectedTerm)) {
						information('network');
						return;
					}
					if (mode === 'initial') {
						context = json.context;
						if (preserve && context) {
							calculate('explicit');
							return;
						}
					} else if (json.context !== context || result.reason === 'stale_context') {
						context = null;
						information(result.reason || 'stale_context', true);
						return;
					}
					if (!result.eligible || !result.financial || !json.products || !context) {
						information(result.reason || 'network', true);
						return;
					}
					if (mode === 'initial') field('vnoski').val(String(result.term));
					if (!json.quote) {
						information('submission_unavailable', true);
						return;
					}
					offer = { reference: json.quote, term: String(result.term), payment: down, financial: result.financial, products: json.products };
					financialFields.forEach(name => {
						const value = offer.financial['jet_' + name];
						field(name).text(value);
						field(name + '_input').val(value);
					});
					updateButton();
				},
				error: () => {
					if (stillCurrent()) information('network');
				}
			});
		};
		const submit = () => {
			updateButton();
			if (confirm.prop('disabled') || (window.JetInformation && window.JetInformation.active(root))) return;
			sending = true;
			confirm.val(confirm.attr('data-loading-text') || confirmLabel);
			updateButton();
			const data = new FormData();
			data.append('quote', offer.reference);
			financialFields.forEach(name => data.append(prefix + name, offer.financial['jet_' + name]));
			Object.keys(offer.products).forEach(name => data.append(prefix + name, offer.products[name]));
			data.append(prefix + 'vnoski', term());
			data.append(prefix + 'parva', payment());
			data.append(prefix + 'egn', field('egn').val());
			const failed = reason => {
				if (!alive()) return;
				sending = false;
				confirm.val(confirmLabel);
				invalidate();
				information(reason || 'submission_uncertain', true);
			};
			$.ajax({
				url: 'index.php?route=extension/payment/' + channel + '/confirm',
				method: 'POST',
				dataType: 'json',
				data,
				processData: false,
				contentType: false,
				success: json => {
					if (!alive()) return;
					if (json && json.success === 'success' && json.redirect) {
						window.location.href = json.redirect;
					} else {
						failed(json && json.reason);
					}
				},
				error: () => failed('submission_uncertain')
			});
		};

		$root.on('change.jetCheckout', '#' + prefix + 'vnoski', () => calculate('explicit'))
			.on('click.jetCheckout', '.jet_button_preizcisli', () => calculate('explicit'))
			.on('input.jetCheckout', '#' + prefix + 'parva', () => {
				invalidate();
				scheduled = setTimeout(() => calculate('explicit'), 250);
			})
			.on('change.jetCheckout', '#' + prefix + 'uslovia, #' + prefix + 'uslovia1', updateButton)
			.on('input.jetCheckout', '#' + prefix + 'egn', function() {
				this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
				updateButton();
			})
			.on('click.jetCheckout', '#button-confirm', submit);
		updateButton();

		return {
			root,
			channel,
			invalidate,
			start: () => {
				if (started) return;
				started = true;
				calculate('initial');
			},
			recheck: () => {
				if (!started || sending) return;
				calculate('explicit');
			},
			destroy: () => {
				if (window.JetInformation) window.JetInformation.close(root, false);
				invalidate();
				$root.off('.jetCheckout');
			}
		};
	}
});
