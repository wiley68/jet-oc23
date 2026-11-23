'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const asset = path.join(__dirname, '../upload/catalog/view/theme/default/template/extension/mt_jet_credit/');
const source = fs.readFileSync(asset + 'creditjet_information.js', 'utf8');

const listeners = {};
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
const html = element('html');
const document = {
    body: element('body'), documentElement: html, createElement: element,
    addEventListener(name, callback) { (listeners[name] ||= []).push(callback); }
};
html.appendChild(document.body);
const window = {};
const environment = vm.createContext({ window, document });
vm.runInContext(source, environment);
vm.runInContext(source, environment);

let count = 0;
function check(label, actual, expected) {
    assert.equal(actual, expected, label);
    count++;
}
function key(key, target) {
    const event = { key, target, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; } };
    listeners.keydown[0](event);
    return event;
}
check('Repeated script execution installs one key handler', listeners.keydown.length, 1);
for (const flow of ['Cart JET', 'Cart Card', 'Checkout JET', 'Checkout Card']) {
    const panel = element('section');
    const term = Object.assign(element('select'), { value: '10' });
    const down = Object.assign(element('input'), { value: '49.25' });
    const personal = Object.assign(element('input'), { value: 'Customer' });
    document.body.appendChild(panel);
    panel.appendChild(term);
    panel.appendChild(down);
    panel.appendChild(personal);
    for (const reason of ['Различни ставки за 10 месеца', 'Забранен срок']) {
        window.JetInformation.show(panel, reason, term);
        const overlay = document.body.children[1];
        const dialog = overlay.children[0];
        const [title, message, button] = dialog.children;
        check(flow + ' title', title.textContent, 'Информация');
        check(flow + ' message', message.textContent, reason);
        check(flow + ' aria-modal', dialog.attributes['aria-modal'], 'true');
        check(flow + ' close takes focus', focus, button);
        check(flow + ' tab stays trapped', key('Tab', button).prevented, true);
        const outside = { target: term, preventDefault() { this.prevented = true; }, stopImmediatePropagation() {} };
        listeners.click[0](outside);
        check(flow + ' outside click blocked', outside.prevented, true);
        if (reason.startsWith('Различни')) key('Escape', button);
        else button.events.click();
        check(flow + ' closes only dialog', document.body.contains(panel) && !document.body.contains(overlay), true);
        check(flow + ' restores focus', focus, term);
        check(flow + ' preserves selection and personal fields', [term.value, down.value, personal.value].join('|'), '10|49.25|Customer');
    }
    panel.remove();
}
const css = fs.readFileSync(asset + 'creditjet_information.css', 'utf8');
check('Mobile layout bounds the dialog to the viewport', /max-height:\s*90vh/.test(css) && /width:\s*100%/.test(css), true);
check('Mobile overflow remains scrollable', /overflow:\s*auto/.test(css), true);
console.log(count + ' shared Information dialog checks passed.');
