/*jshint esversion: 11, node: true */
'use strict';

/**
 * Loads the real js/crm.payment.js in a sandboxed vm context (with
 * minimal document/jQuery/CRM stubs - just enough for the file's
 * top-level registration code to run without throwing) and returns the
 * resulting CRM.payment object, so tests exercise the exact file that
 * ships to the browser rather than a copy/reimplementation of it.
 *
 * @returns {object} CRM.payment
 */
function loadCrmPayment() {
  const vm = require('node:vm');
  const fs = require('node:fs');
  const path = require('node:path');

  const src = fs.readFileSync(path.join(__dirname, '..', '..', 'js', 'crm.payment.js'), 'utf8');

  function $(selector) {
    return { ajaxComplete: function() {} };
  }
  $.extend = Object.assign;

  const sandbox = {
    document: { addEventListener: function() {} },
    CRM: {
      $: $,
      ts: function() { return function(msg) { return msg; }; },
    },
  };
  vm.createContext(sandbox);
  vm.runInContext(src, sandbox, { filename: 'crm.payment.js' });

  return sandbox.CRM.payment;
}

const test = require('node:test');
const assert = require('node:assert/strict');

test('CRM.payment.roundMoney', (t) => {
  const payment = loadCrmPayment();

  const cases = [
    // Reported case: https://lab.civicrm.org/extensions/stripe/-/work_items/510
    // $20 + 7.625% tax = 21.525 exactly, but the nearest double is very
    // slightly below that, so naive toFixed(2) wrongly gives "21.52".
    [20 + (7.625 / 100) * 20, '21.53'],
    [21.525, '21.53'],
    [-21.525, '-21.53'],
    // Classic JS toFixed() rounding quirks caused by binary float
    // representation of an exact decimal boundary.
    [1.005, '1.01'],
    [2.675, '2.68'],
    [9.995, '10.00'],
    [999.995, '1000.00'],
    // Values that are not on a rounding boundary should be unaffected.
    [0, '0.00'],
    [0.1 + 0.2, '0.30'],
    [1.1, '1.10'],
    [19.99, '19.99'],
    [100, '100.00'],
  ];

  for (const [input, expected] of cases) {
    assert.equal(payment.roundMoney(input), expected, `roundMoney(${input}) should be ${expected}`);
  }
});

test('CRM.payment.roundMoney respects a custom decimals argument', () => {
  const payment = loadCrmPayment();
  assert.equal(payment.roundMoney(1.2345, 3), '1.235');
  assert.equal(payment.roundMoney(4.6, 0), '5.');
});
