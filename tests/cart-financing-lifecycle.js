'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname,
    '../upload/catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_cart.js'), 'utf8');
const informationSource = fs.readFileSync(path.join(__dirname,
    '../upload/catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_information.js'), 'utf8');

// A small DOM/jQuery fixture exercises the real Cart event handlers and controlled AJAX callbacks.
const pending = [];
const timers = new Map();
const hooks = new WeakMap();
const values = new WeakMap();
let observer;
let root;
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

function fragment() {
    const nodes = new Map();
    return {
        querySelector(selector) {
            if (!nodes.has(selector)) {
                nodes.set(selector, Object.assign(element(selector), {
                    id: selector.slice(1), value: '', text: '', checked: false
                }));
            }
            return nodes.get(selector);
        },
        contains(node) {
            return [...nodes.values()].includes(node);
        }
    };
}

const listeners = {};
const document = {
    body: element('body'),
    createElement: element,
    documentElement: { contains: node => node === root || root.contains(node) },
    querySelector: () => root,
    addEventListener(event, fn) { (listeners[event] ||= []).push(fn); }
};
const window = {};
function wrap(nodes) {
    return {
        find: selector => wrap(selector.split(', ').map(key => nodes[0].querySelector(key))),
        on(event, selector, handler) {
            const target = nodes[0];
            if (!hooks.has(target)) hooks.set(target, []);
            hooks.get(target).push({ event: event.split('.')[0], selector: handler ? selector : '', fn: handler || selector });
            return this;
        },
        off() { hooks.delete(nodes[0]); return this; },
        prop(key, value) { nodes.forEach(node => node[key] = value); return this; },
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
    jQuery, document, window,
    MutationObserver: class { constructor(callback) { observer = callback; } observe() {} },
    setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
    clearTimeout(id) { timers.delete(id); }
});
function check(message, expected, actual) {
    assert.deepEqual(actual, expected, message);
    count++;
}
function trigger(target, event, selector, ...args) {
    for (const hook of hooks.get(target) || []) {
        if (hook.event === event && (!hook.selector || hook.selector.split(', ').includes(selector))) {
            hook.fn.call(selector ? target.querySelector(selector) : target, { type: event }, ...args);
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
function offer(channel, term, eligible = true, reason = null, monthly = '45.00') {
    return {
        channel, term, eligible, reason, rate: '0',
        financial: eligible ? { jet_priceall: '450.00', jet_vnoska: monthly, jet_total_credit_price: '450.00',
            jet_gpr: '0.00', jet_glp: '0.00', jet_obshto: '450.00' } : undefined
    };
}
function initial(call, context, jet = offer('jet', 3), card = offer('jet_card', 6)) {
    call.success({ mode: 'initial', context, channels: { jet, jet_card: card }, quotes: { jet: 'quote-jet', jet_card: 'quote-card' }, products: { jet_products: '43_40' } });
}
function explicit(call, context, result) {
    call.success({ mode: 'explicit', context, offer: result, quote: 'explicit-quote', products: { jet_products: '43_40' } });
}
const incompatibility = 'За избраните условия продуктите в количката не могат да бъдат закупени заедно на изплащане. Моля, изберете друг срок или условия.\n\nАко желаете, можете да закупите на изплащане всеки продукт поотделно.';
const inlineStatus = () => node('[data-jet-information]');
function checkDialogOnly(label, expectedMessage = incompatibility) {
    const overlays = document.body.children;
    check(label + ' creates one visible overlay', 1, overlays.length);
    const [title, message, button] = overlays[0].children[0].children;
    check(label + ' uses the shared title and expected message', ['Информация', expectedMessage],
        [title.textContent, message.textContent]);
    check(label + ' leaves the upper popup status hidden and empty', [true, ''],
        [inlineStatus().hidden, inlineStatus().text]);
    check(label + ' focuses the dialog close button', button, focus);
    return button;
}

root = fragment();
vm.runInContext(informationSource, env);
vm.runInContext(source, env);
const first = take('initial');
vm.runInContext(informationSource, env);
vm.runInContext(source, env);
check('Repeated script initialization does not request twice', 0, pending.length);
check('Repeated script initialization installs one dialog key handler', 1, listeners.keydown.length);
observer();
check('Repeated mount of same root sends no duplicate request', 0, pending.length);
initial(first, 'cart-a', offer('jet', 10));
check('JET preview uses the preferred term', 10, node('#jet_vnoski_text').text);
check('JET preview shows the zero-rate installment', '45.00', node('#jet_vnoska').text);
check('Card preview uses its own term', 6, node('#jet_vnoski_text_card').text);

trigger(root, 'click', '#btn_jet');
const open = take('initial');
check('Opening revalidates instead of enabling from preview', true, node('#buy_jetcredit').disabled);
initial(open, 'cart-a', offer('jet', 10), offer('jet_card', null, false, 'channel_disabled'));
check('Unavailable Card becomes hidden', true, node('[data-jet-channel="jet_card"]').hidden);
check('Unavailable Card numbers cleared', '', node('#jet_vnoska_card').text);
check('Popup initializes to the preview term', '10', node('#jet_vnoski').value);
check('Popup initializes to the preview installment', node('#jet_vnoska').text, node('#jet_vnoska_popup').text);
node('#uslovia').checked = node('#uslovia1').checked = node('#uslovia2').checked = true;
trigger(root, 'change', '#uslovia');
check('Valid calculation and consents allow continuation', false, node('#buy_jetcredit').disabled);

node('#jet_vnoski').value = '12';
trigger(root, 'change', '#jet_vnoski');
const old = take('explicit');
check('Term change immediately clears old calculation', '', node('#jet_vnoska_popup').text);
check('Term change immediately disables both actions', true, node('#buy2_jetcredit').disabled);
node('#jet_vnoski').value = '9';
trigger(root, 'change', '#jet_vnoski');
explicit(take('explicit'), 'cart-a', offer('jet', '9', true, null, '50.00'));
explicit(old, 'cart-a', offer('jet', '12', true, null, '99.00'));
check('Late old response cannot overwrite newest installment', '50.00', node('#jet_vnoska_popup').text);
check('Newest term retained', '9', node('#jet_vnoski').value);

node('#jet_parva').value = '49.25';
node('#jet_vnoski').value = '10';
trigger(root, 'change', '#jet_vnoski');
explicit(take('explicit'), 'cart-a', offer('jet', '10', false, 'rate_mismatch'));
check('Explicit refusal keeps invalid term', '10', node('#jet_vnoski').value);
check('Explicit refusal keeps down payment', '49.25', node('#jet_parva').value);
check('Explicit refusal clears all calculated fields', Array(6).fill(''),
    ['#jet_priceall', '#jet_total_credit_price', '#jet_vnoska_popup', '#jet_gpr', '#jet_glp', '#jet_obshto']
        .map(selector => node(selector).text));
const dismiss = checkDialogOnly('JET rejection');
const escape = { key: 'Escape', target: dismiss, preventDefault() { this.prevented = true; }, stopImmediatePropagation() {} };
listeners.keydown[0](escape);
check('Escape dismisses the shared dialog and restores focus', [true, 0, node('#jet_vnoski')],
    [escape.prevented, document.body.children.length, focus]);
check('Dismissal leaves no duplicate to reveal', [true, ''], [inlineStatus().hidden, inlineStatus().text]);
check('Refusal keeps popup open', 'block', node('#jet-product-popup-container').display);
check('Refusal cannot apply', true, node('#buy2_jetcredit').disabled);
trigger(root, 'click', '#buy2_jetcredit');
check('Dismissing the dialog does not unlock submission', 0, pending.length);
trigger(root, 'click', '#btn_preizcisli');
explicit(take('explicit'), 'cart-a', offer('jet', '10', false, 'rate_mismatch'));
checkDialogOnly('Repeated JET rejection').events.click();
node('#jet_parva').value = '50.25';
trigger(root, 'input', '#jet_parva');
check('Down payment change disables before debounce', true, node('#buy_jetcredit').disabled);
for (const callback of [...timers.values()]) callback();
const failure = take('explicit');
check('Down payment gets transmitted exactly', '50.25', failure.data.down_payment);
failure.error();
check('Network failure leaves application disabled', true, node('#buy2_jetcredit').disabled);
check('Network failure preserves entered amount', '50.25', node('#jet_parva').value);
check('Network failure stays inline without opening a dialog',
    [false, 'Калкулацията не е достъпна в момента. Моля, преизчислете.', 0],
    [inlineStatus().hidden, inlineStatus().text, document.body.children.length]);

node('#jet_vnoski').value = '9';
trigger(root, 'change', '#jet_vnoski');
explicit(take('explicit'), 'cart-a', offer('jet_card', '9'));
check('Wrong channel response cannot activate JET', true, node('#buy_jetcredit').disabled);
trigger(root, 'click', '#btn_preizcisli');
explicit(take('explicit'), 'cart-a', offer('jet', '9', true, null, '50.00'));
check('Recovery with exact valid selection', false, node('#buy_jetcredit').disabled);
check('Recovery restores financial values and clears the inline status', ['50.00', true, ''],
    [node('#jet_vnoska_popup').text, inlineStatus().hidden, inlineStatus().text]);

const mutation = {};
trigger(document, 'ajaxSend', '', mutation, { url: 'index.php?route=extension/total/coupon/coupon' });
check('Cart mutation immediately disables an existing offer', true, node('#buy2_jetcredit').disabled);
trigger(document, 'ajaxComplete', '', mutation);
initial(take('initial'), 'cart-b');
const refreshed = take('explicit');
check('Cart refresh uses new server context', 'cart-b', refreshed.data.context);
check('Cart refresh preserves explicit term', '9', refreshed.data.term);
check('Cart refresh preserves down payment', '50.25', refreshed.data.down_payment);
explicit(refreshed, 'cart-c', offer('jet', '9', false, 'stale_context'));
check('Stale server response leaves old offer cleared', '', node('#jet_vnoska_popup').text);
trigger(root, 'click', '#btn_preizcisli');
initial(take('initial'), 'cart-c');
explicit(take('explicit'), 'cart-c', offer('jet', '9'));
check('Explicit retry after stale context revalidates', false, node('#buy_jetcredit').disabled);

trigger(root, 'click', '#close_jetcredit');
initial(take('initial'), 'cart-c');
trigger(root, 'click', '#btn_jet_card');
initial(take('initial'), 'cart-c');
check('Reopening with Card starts without a previous warning', [true, '', 0, 0],
    [inlineStatus().hidden, inlineStatus().text, document.body.children.length, pending.length]);
node('#jet_vnoski').value = '10';
trigger(root, 'change', '#jet_vnoski');
const card = take('explicit');
check('Reopened popup requests the Card channel exactly once', ['jet_card', 0], [card.data.channel, pending.length]);
explicit(card, 'cart-c', offer('jet_card', '10', false, 'rate_mismatch'));
checkDialogOnly('Card rejection');
check('Card remains on the invalid term with submission disabled', ['10', true],
    [node('#jet_vnoski').value, node('#buy2_jetcredit').disabled]);

trigger(document, 'ajaxSend', '', mutation, { url: 'index.php?route=extension/total/coupon/coupon' });
trigger(document, 'ajaxComplete', '', mutation);
initial(take('initial'), 'cart-d');
const cardRefresh = take('explicit');
check('Cart refresh retains the invalid Card selection', ['jet_card', '10'], [cardRefresh.data.channel, cardRefresh.data.term]);
explicit(cardRefresh, 'cart-d', offer('jet_card', '10', false, 'rate_mismatch'));
checkDialogOnly('Card rejection after refresh');

trigger(root, 'click', '#btn_preizcisli');
const detached = take('explicit');
const previous = root;
root = fragment();
explicit(detached, 'cart-d', offer('jet_card', '10', false, 'rate_mismatch'));
check('Detached fragment response cannot write old DOM',
    '', previous.querySelector('#jet_vnoska_popup').text);
observer();
const fresh = take('initial');
check('Old delegated handlers removed', false, hooks.has(previous));
check('Fragment replacement removes the old dialog', 0, document.body.children.length);
observer();
check('Repeated observer callback does not duplicate new fragment handlers', 0, pending.length);
initial(fresh, 'cart-e');
trigger(root, 'click', '#btn_jet');
initial(take('initial'), 'cart-e');
check('Fragment replacement opens without an old inline notice', [true, '', 0],
    [inlineStatus().hidden, inlineStatus().text, document.body.children.length]);
node('#jet_vnoski').value = '10';
trigger(root, 'change', '#jet_vnoski');
const dialogAsset = window.JetInformation;
delete window.JetInformation;
explicit(take('explicit'), 'cart-e', offer('jet', '10', false, 'rate_mismatch'));
check('Without the shared dialog asset the inline fallback remains available', [false, incompatibility, 0],
    [inlineStatus().hidden, inlineStatus().text, document.body.children.length]);
window.JetInformation = dialogAsset;
trigger(root, 'click', '#btn_preizcisli');
explicit(take('explicit'), 'cart-e', offer('jet', '10', false, 'rate_mismatch'));
checkDialogOnly('Rejection after the inline fallback').events.click();
node('#jet_parva').value = '500.25';
trigger(root, 'click', '#btn_preizcisli');
explicit(take('explicit'), 'cart-e', offer('jet', '10', false, 'invalid_down_payment'));
checkDialogOnly('Invalid down payment', 'Въведете първоначална вноска, по-малка от цената на стоките.').events.click();
check('Down-payment rejection restores focus to the preserved value', [node('#jet_parva'), '500.25'],
    [focus, node('#jet_parva').value]);
trigger(root, 'click', '#close_jetcredit');
take('initial').error();
check('Initial failure leaves actions disabled', true, node('#buy2_jetcredit').disabled);
console.log(count + ' Cart frontend lifecycle checks passed.');
