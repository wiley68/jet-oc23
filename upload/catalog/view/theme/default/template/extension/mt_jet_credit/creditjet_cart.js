jQuery(function($) {
	if ($.data(document, 'jetCartMounted')) return;
	$.data(document, 'jetCartMounted', true);

	const channels = { jet: '', jet_card: '_card' };
	const fields = {
		jet_priceall: '#jet_priceall',
		jet_total_credit_price: '#jet_total_credit_price',
		jet_vnoska: '#jet_vnoska_popup',
		jet_gpr: '#jet_gpr',
		jet_glp: '#jet_glp',
		jet_obshto: '#jet_obshto'
	};
	const messages = {
		rate_mismatch: 'За избраните условия продуктите в количката не могат да бъдат закупени заедно на изплащане. Моля, изберете друг срок или условия.\n\nАко желаете, можете да закупите на изплащане всеки продукт поотделно.',
		term_prohibited: 'За избрания срок има продукт, който не може да бъде финансиран.',
		term_not_allowed: 'Избраният срок не е достъпен за тази сума.',
		invalid_down_payment: 'Въведете първоначална вноска, по-малка от цената на стоките.',
		insufficient_installment: 'Месечната вноска трябва да бъде поне 20 евро.',
		stale_context: 'Кошницата е променена. Преизчислете избрания срок и вноска.',
		invalid_applicant_email: 'Проверете адреса за електронна поща.',
		stale_quote: 'Офертата е изтекла или условията са променени. Преизчислете.',
		quote_mismatch: 'Условията на заявката се различават от офертата. Преизчислете.',
		submission_in_progress: 'Заявката се обработва.',
		already_processed: 'Тази заявка вече е обработена.',
		submission_uncertain: 'Резултатът от изпращането е неясен. Свържете се с магазина, преди да опитате отново.',
		transport_failed: 'Заявката не беше приета за изпращане. Преизчислете и опитайте отново.',
		submission_unavailable: 'Изпращането в момента е недостъпно.',
		network: 'Калкулацията не е достъпна в момента. Моля, преизчислете.'
	};
	const cartRoutes = /[?&]route=(?:checkout\/cart\/(?:add|edit|remove)|extension\/total\/(?:coupon\/coupon|voucher\/voucher|shipping\/shipping|reward\/reward))(?=&|$)/;
	let instance;
	const cartRequests = new Set();

	function mount() {
		const root = document.querySelector('[data-jet-cart]');
		const popup = root && root.querySelector('#jet-product-popup-container');
		if (instance && instance.root === root && instance.popup === popup) return;
		if (instance) instance.destroy();
		instance = popup ? createInstance(root, popup) : null;
		if (instance && !cartRequests.size) instance.refresh();
	}

	$(document).on('ajaxSend.jetCart', function(event, xhr, settings) {
		if (!cartRoutes.test(settings.url.split('#')[0].replace(/%2f/gi, '/'))) return;
		cartRequests.add(xhr);
		if (instance) instance.invalidateCart();
	}).on('ajaxComplete.jetCart', function(event, xhr) {
		if (cartRequests.delete(xhr) && !cartRequests.size && instance) instance.refresh();
	});
	$(window).on('pageshow.jetCart focus.jetCart', function(event) {
		if (event.type === 'pageshow' && !(event.originalEvent && event.originalEvent.persisted)) return;
		if (instance && !cartRequests.size) instance.refresh();
	});

	new MutationObserver(mount).observe(document.body, { childList: true, subtree: true });
	mount();

	function createInstance(node, popup) {
		const $root = $(node);
		const find = selector => $root.find(selector);
		const term = () => find('#jet_vnoski').val();
		const down = () => find('#jet_parva').val();
		let revision = 0;
		let scheduled;
		let context;
		let discovery;
		let references;
		let products;
		let channel;
		let offer;
		let sending = false;

		const alive = () => document.documentElement.contains(node) && node.contains(popup);
		const currentOffer = () => offer && offer.channel === channel && String(offer.term) === term()
			&& offer.down === down() && offer.context === context;
		const step = number => {
			find('#jet_step_1').toggle(number === 1);
			find('#jet_step_2').toggle(number === 2);
		};
		const buttons = () => {
			const valid = currentOffer() && !sending;
			const proceed = valid && find('#uslovia').is(':checked') && find('#uslovia1').is(':checked');
			const send = valid && find('#uslovia2').is(':checked');
			find('#buy_jetcredit').prop('disabled', !proceed).css('opacity', proceed ? 1 : 0.5);
			find('#buy2_jetcredit').prop('disabled', !send).css('opacity', send ? 1 : 0.5);
		};
		const information = (reason, dialog = false) => {
			const message = reason && (messages[reason] || 'Финансирането не е достъпно за текущия избор.');
			const informationDialog = dialog && window.JetInformation;
			const inlineMessage = informationDialog ? '' : (message || '');
			find('[data-jet-information]').prop('hidden', !inlineMessage).text(inlineMessage);
			if (message) {
				step(1);
				if (informationDialog) {
					const target = reason === 'invalid_down_payment' ? '#jet_parva' : '#jet_vnoski';
					informationDialog.show(node, message, node.querySelector(target));
				}
			}
		};
		const invalidate = () => {
			revision++;
			clearTimeout(scheduled);
			offer = null;
			Object.keys(fields).forEach(key => find(fields[key]).text(''));
			information(null);
			buttons();
		};
		const request = (data, success) => {
			const expected = revision;
			const current = () => alive() && expected === revision;
			$.ajax({
				url: 'index.php?route=extension/mt_jet_credit/jetcredit/jet_cart_offer',
				method: 'POST',
				dataType: 'json',
				data,
				success: json => {
					if (!current()) return;
					if (!json || json.mode !== data.mode || typeof json.context !== 'string') {
						information('network');
						return;
					}
					success(json);
				},
				error: () => {
					if (current() && channel) information('network');
				}
			});
		};
		const previews = schemes => {
			Object.keys(channels).forEach(key => {
				const candidate = schemes && schemes[key];
				const available = candidate && candidate.eligible && candidate.channel === key && candidate.financial;
				find('[data-jet-channel="' + key + '"]').prop('hidden', !available);
				find('#jet_vnoski_text' + channels[key]).text(available ? candidate.term : '');
				find('#jet_vnoska' + channels[key]).text(available ? candidate.financial.jet_vnoska : '');
			});
		};
		const accept = (candidate, selection, reference) => {
			if (!candidate || candidate.channel !== channel || String(candidate.term) !== term()
				|| selection !== down()) {
				information('network');
				return;
			}
			if (!candidate.eligible || !candidate.financial || !products) {
				information(candidate.reason || 'network', true);
				return;
			}
			if (!reference) {
				information('submission_unavailable', true);
				return;
			}
			offer = Object.assign({}, candidate, { down: selection, context, reference });
			Object.keys(fields).forEach(key => find(fields[key]).text(offer.financial[key]));
			buttons();
		};
		const explicit = () => {
			if (!channel || cartRequests.size) return;
			invalidate();
			const selected = term();
			const payment = down();
			if (!context) {
				discover(false, explicit);
				return;
			}
			request({ mode: 'explicit', channel, term: selected, down_payment: payment, context }, json => {
				const result = json.offer;
				if (json.context !== context || (result && result.reason === 'stale_context')) {
					invalidateCart();
					information(result && result.reason === 'invalid_currency' ? 'invalid_currency' : 'stale_context');
					return;
				}
				if (json.products) products = json.products;
				accept(result, payment, json.quote);
			});
		};
		const discover = (opening, done) => {
			if (cartRequests.size) return;
			invalidate();
			context = null;
			discovery = null;
			products = null;
			previews(null);
			request({ mode: 'initial' }, json => {
				context = json.context;
				discovery = json.channels;
				references = json.quotes || {};
				products = json.products;
				previews(discovery);
				if (done) {
					done();
				} else if (channel && opening) {
					const candidate = discovery && discovery[channel];
					if (candidate && candidate.eligible) {
						find('#jet_vnoski').val(String(candidate.term));
						accept(candidate, '0', references[channel]);
					} else {
						information(candidate && candidate.reason || 'network');
					}
				} else if (channel) {
					explicit();
				}
			});
		};
		const invalidateCart = () => {
			invalidate();
			context = null;
			discovery = null;
			products = null;
			previews(null);
			if (channel) information('stale_context');
		};
		const open = selected => {
			if (!discovery || !discovery[selected] || !discovery[selected].eligible) return;
			channel = selected;
			find('#jet_vnoski').val(String(discovery[selected].term));
			find('#jet_parva').val('0');
			step(1);
			find('#jet-product-popup-container').css('display', 'block');
			discover(true);
		};
		const close = () => {
			channel = null;
			invalidate();
			find('#jet-product-popup-container').hide();
			find('#uslovia, #uslovia1, #uslovia2').prop('checked', false);
			discover(false);
		};

		const jetShowCustomAlert = (message, exit) => {
			const jetAlertBox = $('<div></div>', {
				id: 'jet_alert_box',
				css: {
					position: 'fixed',
					top: '50%',
					left: '50%',
					transform: 'translate(-50%, -50%)',
					backgroundColor: '#fff',
					padding: '20px',
					borderRadius: '5px',
					boxShadow: '0 0 10px rgba(0, 0, 0, 0.1)',
					zIndex: '5000001',
					width: '300px',
					textAlign: 'center'
				}
			});
			const jetMessageText = $('<p></p>', {
				text: message,
				css: {
					fontFamily: '"Roboto Condensed", sans-serif',
					color: '#14532d'
				}
			});
			jetAlertBox.append(jetMessageText);
			const jetCloseButton = $('<button></button>', {
				text: 'Добре',
				css: {
					fontFamily: '"Roboto Condensed", sans-serif',
					fontWeight: '500',
					marginTop: '20px',
					padding: '10px 20px',
					border: 'none',
					backgroundColor: '#166534',
					color: '#fff',
					borderRadius: '3px',
					cursor: 'pointer'
				}
			});
			jetCloseButton.on('click', function() {
				jetAlertBox.remove();
				if (exit) {
					window.location.href = window.location.origin;
				}
				find('#jet_alert_overlay').removeClass('show');
			});
			jetAlertBox.append(jetCloseButton);
			$root.append(jetAlertBox);
		}

		const checkForm = () => {
			let check = true;
			const jet_name = node.querySelector('#jet_name').value.trim();
			if (jet_name === '') {
				node.querySelector('#jet_name').classList.add('error');
				check = false;
			}
			const jet_lastname = node.querySelector('#jet_lastname').value.trim();
			if (jet_lastname === '') {
				node.querySelector('#jet_lastname').classList.add('error');
				check = false;
			}
			const egnRe = /^[0-9]{2}((0[1-9]|1[0-2])|(4[1-9]|5[0-2]))(0[0-9]|1[0-9]|2[0-9]|3[0-1])[0-9]{4}$/;
			const jet_egn = node.querySelector('#jet_egn').value.trim();
			if (jet_egn === '' || !egnRe.test(jet_egn)) {
				node.querySelector('#jet_egn').classList.add('error');
				check = false;
			}
			const phoneRe = /^[+0-9]+$/;
			const jet_phone = node.querySelector('#jet_phone').value.trim();
			if (jet_phone === '' || jet_phone.length < 10 || !phoneRe.test(jet_phone)) {
				node.querySelector('#jet_phone').classList.add('error');
				check = false;
			}
			const re = /^[a-zA-Z0-9.!#$%&'*+/=?^_'{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
			const jet_email = node.querySelector('#jet_email').value.trim().toLowerCase();
			const position = jet_email.indexOf('@') + 1;
			const ostatak = jet_email.substr(position);
			if (jet_email === '' || !re.test(jet_email) || ostatak.indexOf('.') === -1) {
				node.querySelector('#jet_email').classList.add('error');
				check = false;
			}
			return check;
		}


		const send = () => {
			if ((window.JetInformation && window.JetInformation.active(node)) || sending || !currentOffer() || !find('#uslovia2').is(':checked') || !checkForm()) return;
			sending = true;
			buttons();
			find('#jet_alert_overlay').addClass('show');
			const data = new FormData();
			data.append('quote', offer.reference);
			Object.keys(fields).forEach(key => data.append(key, offer.financial[key]));
			data.append('jet_vnoski', term());
			data.append('jet_parva', down());
			data.append('jet_card', channel === 'jet_card' ? 1 : 0);
			['name', 'lastname', 'egn', 'email', 'phone', 'lname'].forEach(name => {
				data.append('jet_' + name, find('#jet_' + name).val());
			});
			Object.keys(products).forEach(key => data.append(key, products[key]));

			const failed = reason => {
				if (!alive()) return;
				sending = false;
				invalidate();
				find('#jet_alert_overlay').removeClass('show');
				information(reason || 'submission_uncertain', true);
			};
			$.ajax({
				url: 'index.php?route=extension/mt_jet_credit/jetcredit/jet_send_cart',
				method: 'POST',
				data,
				processData: false,
				contentType: false,
				success: json => {
					if (!alive()) return;
					if (json && json.success === 'success') {
						invalidateCart();
						jetShowCustomAlert('Успешно изпратихте Вашата заявка за лизинг към ПБ Лични Финанси. Очаквайте контакт за потвърждаване на направената от Вас заявка.', true);
					} else {
						failed(json && json.reason);
					}
				},
				error: () => failed('submission_uncertain')
			});
		};

		$root.on('click.jetCart', '#btn_jet, #btn_jet_card', function() {
			open(this.id === 'btn_jet' ? 'jet' : 'jet_card');
		}).on('click.jetCart', '#back_jetcredit, #close_jetcredit', close)
			.on('click.jetCart', '#back2_jetcredit', () => step(1))
			.on('click.jetCart', '#buy_jetcredit', () => {
				if (currentOffer() && find('#uslovia').is(':checked') && find('#uslovia1').is(':checked')) step(2);
			}).on('click.jetCart', '#buy2_jetcredit', send)
			.on('change.jetCart', '#uslovia, #uslovia1, #uslovia2', buttons)
			.on('change.jetCart', '#jet_vnoski', explicit)
			.on('click.jetCart', '#btn_preizcisli', explicit)
			.on('input.jetCart', '#jet_parva', () => {
				invalidate();
				scheduled = setTimeout(explicit, 250);
			}).on('input.jetCart', '#jet_egn, #jet_phone', function() {
				const pattern = this.id === 'jet_egn' ? /^[0-9]{0,10}$/ : /^[+0-9]*$/;
				if (!pattern.test(this.value)) this.value = this.value.slice(0, -1);
			}).on('click.jetCart', '.jet_input_text_active', function() {
				$(this).removeClass('error');
			});

		step(1);
		return {
			root: node,
			popup,
			invalidateCart,
			refresh: () => discover(false),
			destroy: () => {
				if (window.JetInformation) window.JetInformation.close(node, false);
				invalidate();
				$root.off('.jetCart');
			}
		};
	}
});
