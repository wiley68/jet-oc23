'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname,
    '../upload/catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_checkout.js'), 'utf8');
const informationSource = fs.readFileSync(path.join(__dirname,
    '../upload/catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_information.js'), 'utf8');

// A small DOM/jQuery fixture exercises the real Checkout event handlers and controlled AJAX callbacks.
const pending = [];
const timers = new Map();
const hooks = new WeakMap();
const values = new WeakMap();
let observer;
let root;
let picked;
let count = 0;
let timerId = 0;
let focus;

function element(tag) {
    return {
        tag, children: [], attributes: {}, events: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        appendChild(child) { this.children.push(child); child.parent = this; },
        contains(child) { return child === this || this.children.some(node => node.contains(child)); },
        addEventListener(event, fn) { this.events[event] = fn; },
        focus() { focus = this; },
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
    };
}

function fragment(channel = 'jet', order = '42') {
    const template = path.join(__dirname, '../upload/catalog/view/theme/default/template/extension/payment', channel + '.tpl');
    const markup = fs.readFileSync(template, 'utf8')
        .replace('<?php echo $jet_order; ?>', order)
        .replace(/<\?php[\s\S]*?\?>/g, '')
        .replace(/<style\b[^>]*>[\s\S]*?<\/style>/g, '');
    // Only div ancestors determine the scope in these templates. Keep selectors backed by
    // actual elements within the marked div so a sibling confirm button cannot pass.
    const nodes = new Map();
    const ancestors = [];
    let rootCount = 0;
    let confirmCount = 0;
    let checkout;
    for (const [, closing, tag, raw] of markup.matchAll(/<(\/?)([\w-]+)\b([^>]*)>/g)) {
        if (closing) {
            if (tag === 'div') ancestors.pop();
            continue;
        }
        const attributes = new Map([...raw.matchAll(/([^\s=\/]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s/]+)))?/g)]
            .map(([, name, quoted, single, bare]) => [name, quoted ?? single ?? bare ?? '']));
        if (tag === 'div') {
            ancestors.push(attributes);
            if (attributes.has('data-jet-checkout')) {
                rootCount++;
                checkout = attributes;
            }
        }
        const id = attributes.get('id');
        if (id === 'button-confirm') confirmCount++;
        if (!checkout || !ancestors.includes(checkout)) continue;
        const selectors = [];
        if (id) selectors.push('#' + id);
        if (attributes.has('data-jet-information')) selectors.push('[data-jet-information]');
        if (attributes.get('class')?.split(/\s+/).includes('jet_button_preizcisli')) selectors.push('.jet_button_preizcisli');
        const node = Object.assign(element(tag), {
            id, text: '', value: attributes.get('value') || '',
            disabled: attributes.has('disabled'), hidden: attributes.has('hidden'), checked: attributes.has('checked')
        });
        for (const selector of selectors) nodes.set(selector, node);
    }
    check(channel + ' renders one Checkout root and one confirm button', [1, 1], [rootCount, confirmCount]);
    check(channel + ' renders the actual confirm button disabled inside its Checkout root',
        true, nodes.get('#button-confirm')?.disabled);
    return {
        getAttribute(name) { return checkout.get(name); },
        querySelector(selector) { return nodes.get(selector); },
        contains(node) {
            return [...nodes.values()].includes(node);
        }
    };
}

const listeners = {};
const document = {
    body: element('body'),
    createElement: element,
    documentElement: { contains: node => node === root || Boolean(root && root.contains(node)) },
    querySelector: selector => selector === '[data-jet-checkout]' ? root : picked,
    addEventListener(event, fn) { (listeners[event] ||= []).push(fn); }
};
const window = {};
function wrap(nodes) {
    return {
        find: selector => wrap(selector.split(', ').map(key => nodes[0].querySelector(key)).filter(Boolean)),
        on(event, selector, handler) {
            const target = nodes[0];
            if (!hooks.has(target)) hooks.set(target, []);
            hooks.get(target).push({ event: event.split('.')[0], selector: handler ? selector : '', fn: handler || selector });
            return this;
        },
        off() { hooks.delete(nodes[0]); return this; },
        prop(key, value) { if (value === undefined) return nodes[0][key]; nodes.forEach(node => node[key] = value); return this; },
        attr() { return 'Loading'; },
        css(key, value) { return this.prop(key, value); },
        hide() { return this.prop('display', 'none'); },
        toggle(visible) { return this.prop('display', visible ? 'block' : 'none'); },
        val(value) { return value === undefined ? nodes[0].value : this.prop('value', value); },
        text(value) { return this.prop('text', value); },
        is(selector) { return selector === ':checked' && nodes[0].checked; }
    };
}
function jQuery(value) {
    if (typeof value === 'function') return value(jQuery);
    return wrap([value]);
}
jQuery.data = (node, key, data) => {
    if (!values.has(node)) values.set(node, {});
    if (data !== undefined) values.get(node)[key] = data;
    return values.get(node)[key];
};
jQuery.ajax = options => pending.push(options);

const env = vm.createContext({
    jQuery, document, window, FormData: class { constructor() { this.fields = {}; } append(k, v) { this.fields[k] = v; } },
    MutationObserver: class { constructor(callback) { observer = callback; } observe() {} },
    setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
    clearTimeout(id) { timers.delete(id); }
});
function check(message, expected, actual) {
    assert.deepEqual(actual, expected, message);
    count++;
}
function trigger(target, event, selector, ...args) {
    const source = selector ? target.querySelector(selector) : target;
    if (!source) return;
    for (const hook of hooks.get(target) || []) {
        if (hook.event === event && (!hook.selector || hook.selector.split(', ').includes(selector))) {
            hook.fn.call(source, { type: event }, ...args);
        }
    }
}
function node(selector) { return root.querySelector(selector); }
function take(mode) {
    const call = pending.shift();
    assert.ok(call, 'Expected AJAX ' + mode);
    check('Requested evaluation mode', mode, call.data.mode);
    return call;
}
function response(call, channel, term, options = {}) {
    const context = options.context === undefined ? 'order-revision' : options.context;
    call.success({ mode: call.data.mode, context, quote: options.quote || 'verified-quote', products: { products: '43_40', products_pr: '225.00_225.00' },
        offer: { channel, term, eligible: !options.reason, reason: options.reason,
            financial: options.reason ? undefined : { jet_priceall: '450.00', jet_vnoska: options.payment || '45.00',
                jet_total_credit_price: '450.00', jet_gpr: '0.00', jet_glp: '0.00', jet_obshto: '450.00' } } });
}
function checkConsents(channel) {
    node('#' + channel + '_uslovia').checked = true;
    node('#' + channel + '_uslovia1').checked = true;
    node('#' + channel + '_egn').value = '0123456789';
    trigger(root, 'change', '#' + channel + '_uslovia');
}
function choose(channel, term) {
    node('#' + channel + '_vnoski').value = term;
    trigger(root, 'change', '#' + channel + '_vnoski');
}
const incompatibility = 'За избраните условия продуктите в количката не могат да бъдат закупени заедно на изплащане. Моля, изберете друг срок или условия.\n\nАко желаете, можете да закупите на изплащане всеки продукт поотделно.';
const inlineStatus = () => node('[data-jet-information]');
function checkDialogOnly(label) {
    const overlays = document.body.children;
    check(label + ' opens exactly one shared dialog', 1, overlays.length);
    const [title, message, button] = overlays[0].children[0].children;
    check(label + ' shows the exact title and OC3 text', ['Информация', incompatibility],
        [title.textContent, message.textContent]);
    check(label + ' leaves the inline status hidden and empty', [true, ''],
        [inlineStatus().hidden, inlineStatus().text]);
    check(label + ' focuses the dialog close button', button, focus);
    return button;
}

root = fragment();
node('#jet_parva').value = '0';
node('#jet_vnoski').value = '3';
vm.runInContext(informationSource, env);
vm.runInContext(source, env);
const first = take('initial');
check('Only the current fragment order hint is sent', '42', first.data.order);
vm.runInContext(informationSource, env);
vm.runInContext(source, env);
observer();
check('Repeated initialization does not issue another quote', 0, pending.length);
check('Repeated initialization leaves one shared dialog key handler', 1, listeners.keydown.length);
checkConsents('jet');
check('Pending quote cannot submit even with consents', true, node('#button-confirm').disabled);
trigger(root, 'click', '#button-confirm');
check('Pending confirmation sent nothing', 0, pending.length);
response(first, 'jet', 10);
check('Initial term follows server result', '10', node('#jet_vnoski').value);
check('Valid JET quote enables confirm', false, node('#button-confirm').disabled);
check('Panel shows new amount', '45.00', node('#jet_vnoska').text);
choose('jet', '12');
const stale = take('explicit');
check('Term change immediately disables confirm', true, node('#button-confirm').disabled);
check('Term change clears old display', '', node('#jet_vnoska').text);
check('Term change clears hidden figure too', '', node('#jet_vnoska_input').value);
choose('jet', '9');
response(take('explicit'), 'jet', '9', { payment: '50.00' });
response(stale, 'jet', '12', { payment: '99.00' });
check('Late old quote is ignored', '50.00', node('#jet_vnoska').text);
node('#jet_parva').value = '12.50';
choose('jet', '10');
response(take('explicit'), 'jet', '10', { reason: 'rate_mismatch' });
check('Invalid term stays selected', '10', node('#jet_vnoski').value);
check('The rejected down payment is preserved', '12.50', node('#jet_parva').value);
check('Rejected quote clears displayed and hidden financial values', ['', ''],
    [node('#jet_vnoska').text, node('#jet_vnoska_input').value]);
const dismiss = checkDialogOnly('JET rejection');
const escape = { key: 'Escape', target: dismiss, preventDefault() { this.prevented = true; }, stopImmediatePropagation() {} };
listeners.keydown[0](escape);
check('Escape closes the dialog and restores term focus', [true, 0, node('#jet_vnoski')],
    [escape.prevented, document.body.children.length, focus]);
check('Closing the dialog does not reveal an inline copy', [true, ''], [inlineStatus().hidden, inlineStatus().text]);
check('Invalid offer cannot submit', true, node('#button-confirm').disabled);
trigger(root, 'click', '#button-confirm');
check('Rejected JET quote cannot issue a submission request', 0, pending.length);
choose('jet', '10');
response(take('explicit'), 'jet', '10', { reason: 'rate_mismatch' });
checkDialogOnly('Repeated JET rejection').events.click();

node('#jet_parva').value = '23.75';
trigger(root, 'input', '#jet_parva');
check('Payment edit invalidates immediately', true, node('#button-confirm').disabled);
for (const callback of [...timers.values()]) callback();
const failed = take('explicit');
check('Exact down payment sent', '23.75', failed.data.down_payment);
failed.error();
check('Network failure keeps exact down payment', '23.75', node('#jet_parva').value);
check('Network failure keeps action disabled', true, node('#button-confirm').disabled);
check('Network failure remains inline without a dialog',
    [false, 'Калкулацията не е достъпна в момента. Моля, преизчислете.', 0],
    [inlineStatus().hidden, inlineStatus().text, document.body.children.length]);
choose('jet', '9');
response(take('explicit'), 'jet_card', '9');
check('Wrong-channel reply is never accepted', true, node('#button-confirm').disabled);
choose('jet', '9');
response(take('explicit'), 'jet', '9', { payment: '50.00' });
check('Valid explicit choice recovers', false, node('#button-confirm').disabled);
check('Recovery writes valid financial values and clears the notice', ['50.00', '50.00', true, ''],
    [node('#jet_vnoska').text, node('#jet_vnoska_input').value, inlineStatus().hidden, inlineStatus().text]);

trigger(root, 'click', '#button-confirm');
const submit = pending.shift();
check('Legacy submit route remains the same', 'index.php?route=extension/payment/jet/confirm', submit.url);
check('Legacy submit contains exact term', '9', submit.data.fields.jet_vnoski);
check('Quote context is not sent as authorization', false, 'context' in submit.data.fields);
trigger(root, 'click', '#button-confirm');
check('Click during submit never duplicates handler or request', 0, pending.length);
submit.error();
check('Uncertain submission keeps confirmation disabled on the existing form', true, node('#button-confirm').disabled);
window.JetInformation.close(root);

const inFlight = {};
trigger(document, 'ajaxSend', '', inFlight, { url: 'index.php?route=checkout/shipping_method/save' });
check('Shipping updates invalidate immediately', true, node('#button-confirm').disabled);
choose('jet', '12');
check('No quote is issued during mutation', 0, pending.length);
trigger(document, 'ajaxComplete', '', inFlight);
check('Old fragment stays invalid after mutation', true, node('#button-confirm').disabled);
choose('jet', '12');
response(take('explicit'), 'jet', '12', { reason: 'stale_order', context: null });
check('Stale order leaves offer disabled', true, node('#button-confirm').disabled);
window.JetInformation.close(root);
choose('jet', '9');
response(take('initial'), 'jet', 3, { context: 'replacement-context' });
const retry = take('explicit');
check('Context rediscovery preserves term', '9', retry.data.term);
check('Context rediscovery preserves down payment', '23.75', retry.data.down_payment);

const previous = root;
root = fragment('jet_card', '43');
node('#jet_card_vnoski').value = '3';
node('#jet_card_parva').value = '0';
picked = { value: 'jet_card' };
response(retry, 'jet', '9', { context: 'replacement-context' });
check('Detached old fragment cannot accept reply', '', previous.querySelector('#jet_vnoska').text);
observer();
observer();
const cardOffer = take('initial');
check('New fragment quotes current channel and order', ['jet_card', '43'], [cardOffer.data.channel, cardOffer.data.order]);
check('Previous fragment handlers removed', false, hooks.has(previous));
check('Second observer notification duplicates no requests', 0, pending.length);
checkConsents('jet_card');
response(cardOffer, 'jet_card', 10, { payment: '43.60' });
check('Card shows its independent installment', '43.60', node('#jet_card_vnoska').text);
check('Card may confirm with its own quote', false, node('#button-confirm').disabled);
node('#jet_card_parva').value = '20.25';
choose('jet_card', '10');
const cardRejection = take('explicit');
check('Card term change sends one request', 0, pending.length);
response(cardRejection, 'jet_card', '10', { reason: 'rate_mismatch' });
checkDialogOnly('Card rejection').events.click();
check('Card rejection preserves inputs with confirmation blocked', ['10', '20.25', true],
    [node('#jet_card_vnoski').value, node('#jet_card_parva').value, node('#button-confirm').disabled]);

trigger(document, 'ajaxSend', '', inFlight, { url: 'index.php?route=checkout/confirm' });
check('Checkout refresh still presents its inline status', false, inlineStatus().hidden);
trigger(document, 'ajaxComplete', '', inFlight);
trigger(root, 'click', '.jet_button_preizcisli');
const refreshed = take('explicit');
check('Recalculation after refresh uses the Card selection and down payment', ['jet_card', '10', '20.25'],
    [refreshed.data.channel, refreshed.data.term, refreshed.data.down_payment]);
response(refreshed, 'jet_card', '10', { reason: 'rate_mismatch' });
checkDialogOnly('Card rejection after Checkout refresh').events.click();
choose('jet_card', '9');
response(take('explicit'), 'jet_card', '9', { payment: '47.75' });
check('Card recovery restores financial values and confirmation', ['47.75', false],
    [node('#jet_card_vnoska').text, node('#button-confirm').disabled]);
picked = { value: 'other' };
trigger(document, 'change', '#collapse-payment-method input[name="payment_method"]');
check('Switching away safely blocks Card', true, node('#button-confirm').disabled);
check('Payment method change retains its inline notice', [false, 0],
    [inlineStatus().hidden, document.body.children.length]);

const closedCard = root;
root = null;
observer();
check('Closing the payment block removes its handlers', false, hooks.has(closedCard));
root = fragment('jet_card', '43');
picked = { value: 'jet_card' };
node('#jet_card_parva').value = '0';
observer();
vm.runInContext(source, env);
observer();
response(take('initial'), 'jet_card', 10);
check('Reopening the payment block creates one clean initial offer', [0, true, '', 0],
    [pending.length, inlineStatus().hidden, inlineStatus().text, document.body.children.length]);
choose('jet_card', '10');
response(take('explicit'), 'jet_card', '10', { reason: 'rate_mismatch' });
checkDialogOnly('Card rejection after reopening');
const rejectedCard = root;
root = fragment('jet', '44');
picked = { value: 'jet' };
node('#jet_parva').value = '0';
observer();
response(take('initial'), 'jet', 10);
check('Switching back to JET removes the old dialog and handlers', [false, 0, true, '', 0],
    [hooks.has(rejectedCard), document.body.children.length, inlineStatus().hidden, inlineStatus().text, pending.length]);

for (const channel of ['jet', 'jet_card']) {
    const previous = root;
    root = fragment(channel, '45');
    picked = { value: channel };
    observer();
    observer();
    const confirm = node('#button-confirm');
    const initial = take('initial');
    check(channel + ' replacement removes old handlers and requests one initial offer', [false, 0],
        [hooks.has(previous), pending.length]);
    response(initial, channel, 6);
    check(channel + ' eligible offer alone leaves confirm disabled', true, confirm.disabled);
    const consent = name => {
        const selector = '#' + channel + '_' + name;
        node(selector).checked = true;
        trigger(root, 'change', selector);
    };
    const egn = '#' + channel + '_egn';
    consent('uslovia');
    node(egn).value = '0123456789';
    trigger(root, 'input', egn);
    check(channel + ' EGN and first consent are insufficient', true, confirm.disabled);
    node(egn).value = '';
    trigger(root, 'input', egn);
    consent('uslovia1');
    check(channel + ' both consents without EGN are insufficient', true, confirm.disabled);
    node(egn).value = '0123456789';
    trigger(root, 'input', egn);
    check(channel + ' both consents and EGN enable the valid quote', false, confirm.disabled);

    choose(channel, '10');
    const outdated = take('explicit');
    choose(channel, '9');
    const recalculation = take('explicit');
    response(outdated, channel, '10');
    check(channel + ' stale response cannot enable a pending quote', true, confirm.disabled);
    response(recalculation, channel, '9', { reason: 'rate_mismatch' });
    check(channel + ' invalid quote keeps confirm disabled', true, confirm.disabled);
    checkDialogOnly(channel + ' invalid offer').events.click();
    choose(channel, '6');
    response(take('explicit'), channel, '6', { payment: '88.51', quote: 'current-' + channel });
    check(channel + ' valid recalculation restores the offer', [false, '88.51'],
        [confirm.disabled, node('#' + channel + '_vnoska').text]);

    window.JetInformation.show(root, incompatibility, node('#' + channel + '_vnoski'));
    trigger(root, 'click', '#button-confirm');
    check(channel + ' active dialog blocks even a current quote', 0, pending.length);
    window.JetInformation.close(root);
    trigger(root, 'click', '#button-confirm');
    check(channel + ' one delegated click issues one confirmation request', 1, pending.length);
    const confirmation = pending.shift();
    check(channel + ' confirmation carries the current quote and customer fields',
        ['index.php?route=extension/payment/' + channel + '/confirm', 'current-' + channel, '6', '0', '0123456789'],
        [confirmation.url, confirmation.data.fields.quote, confirmation.data.fields[channel + '_vnoski'],
            confirmation.data.fields[channel + '_parva'], confirmation.data.fields[channel + '_egn']]);
    confirmation.success({ success: 'unsuccess', reason: 'stale_quote' });
    check(channel + ' confirmation error clears the offer and opens Information', [true, '', true],
        [confirm.disabled, node('#' + channel + '_vnoska').text, window.JetInformation.active(root)]);
    window.JetInformation.close(root);
    check(channel + ' dismissal alone cannot restore an expired quote', true, confirm.disabled);
    choose(channel, '6');
    response(take('explicit'), channel, '6', { quote: 'replacement-' + channel });
    check(channel + ' fresh quote restores confirmation after an error', false, confirm.disabled);
}
console.log(count + ' Checkout frontend lifecycle checks passed.');
