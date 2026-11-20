/*jshint esversion: 11, node: true */
'use strict';

/**
 * Loads the real js/crm.payment.js in a sandboxed vm context (with
 * minimal document/jQuery/CRM stubs - just enough for the file's
 * top-level registration code to run without throwing) and returns the
 * resulting CRM.payment object, so tests exercise the exact file that
 * ships to the browser rather than a copy/reimplementation of it.
 *
 * @param {object} crm Extra properties for the CRM global, eg. formatMoney.
 * @returns {object} CRM.payment
 */
module.exports = function loadCrmPayment(crm = {}) {
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
      ...crm,
    },
  };
  vm.createContext(sandbox);
  vm.runInContext(src, sandbox, { filename: 'crm.payment.js' });

  return sandbox.CRM.payment;
};
