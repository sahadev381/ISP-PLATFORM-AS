/*
 * Delegated event handling, so markup does not have to carry script.
 *
 * WHY
 *
 * Every onclick="..." in the HTML is a reason script-src must keep
 * 'unsafe-inline', and 'unsafe-inline' is the reason the Content
 * Security Policy is not an XSS defence. An attacker who gets markup
 * into a page can run script, whatever the policy says.
 *
 * HOW
 *
 *     <button onclick="toggleSidebar()">            becomes
 *     <button data-action="toggleSidebar">
 *
 *     <button onclick="setMode('add_node', this)">  becomes
 *     <button data-action="setMode" data-args='["add_node"]'>
 *
 * One listener on document handles every element, including ones
 * added to the page later - which the old attributes could not do
 * without the code that created them remembering to attach a handler.
 *
 * NO eval(), EVER
 *
 * The obvious implementation of this is eval(el.dataset.onclick),
 * which would require 'unsafe-eval' and leave the page no safer than
 * it started. The function is looked up by name on window and the
 * arguments are parsed as JSON, so nothing here can execute a string.
 */

(function () {
    'use strict';

    var EVENTS = ['click', 'change', 'submit'];

    function parseArgs(el) {
        var raw = el.getAttribute('data-args');
        if (!raw) {
            return [];
        }
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [parsed];
        } catch (e) {
            console.error('actions.js: data-args is not valid JSON on', el, raw);
            return [];
        }
    }

    function run(el, event) {
        var name = el.getAttribute('data-action');
        if (!name) {
            return;
        }

        var fn = window[name];
        if (typeof fn !== 'function') {
            /* Loud on purpose. The silent version of this bug is a
               button that does nothing, which gets reported months
               later as "the panel is broken sometimes". */
            console.error('actions.js: no function named "' + name + '" for', el);
            return;
        }

        /* `this` is the element, matching what an inline handler got.
           The event is passed as the last argument for the handlers
           that want it. */
        var result = fn.apply(el, parseArgs(el).concat([event]));

        /* An inline handler returning false cancels the default -
           `onsubmit="return confirm(...)"` depends on it. */
        if (result === false) {
            event.preventDefault();
        }
    }

    EVENTS.forEach(function (type) {
        document.addEventListener(type, function (event) {
            var el = event.target;
            /* closest() so a click on an icon inside the button still
               finds the button. */
            if (el && el.closest) {
                el = el.closest('[data-action-on-' + type + '], [data-action]');
            }
            if (!el) {
                return;
            }
            /* An element opts into one event type. data-action alone
               means click, which is 164 of the 189 cases. */
            var wants = el.getAttribute('data-action-on') || 'click';
            if (wants !== type) {
                return;
            }
            run(el, event);
        }, false);
    });
})();
