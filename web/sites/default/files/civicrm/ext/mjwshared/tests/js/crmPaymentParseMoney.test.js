/*jshint esversion: 11, node: true */
'use strict';

const loadCrmPayment = require('./loadCrmPayment');
const test = require('node:test');
const assert = require('node:assert/strict');

/**
 * @param {string} example What CRM.formatMoney(1234.56, true) returns for the site's separators.
 * @returns {object} CRM.payment
 */
const withFormat = (example) => loadCrmPayment({ formatMoney: () => example });

test('CRM.payment.parseMoney with "," thousands and "." decimal', () => {
  const payment = withFormat('1,234.56');
  // https://lab.civicrm.org/extensions/stripe/-/work_items/524
  assert.equal(payment.parseMoney('1,000.00'), 1000);
  assert.equal(payment.parseMoney('1,234,567.89'), 1234567.89);
  assert.equal(payment.parseMoney('999.99'), 999.99);
  assert.equal(payment.parseMoney('1000'), 1000);
  assert.equal(payment.parseMoney(' 1,000.50 '), 1000.5);
});

test('CRM.payment.parseMoney with "." thousands and "," decimal', () => {
  const payment = withFormat('1.234,56');
  assert.equal(payment.parseMoney('1.000,00'), 1000);
  assert.equal(payment.parseMoney('1.234.567,89'), 1234567.89);
  assert.equal(payment.parseMoney('12,5'), 12.5);
  // A "." decimal part is kept, as cleanMoney() does.
  assert.equal(payment.parseMoney('150.50'), 150.5);
  assert.equal(payment.parseMoney('150.5'), 150.5);
});

test('CRM.payment.parseMoney with a space thousands separator', () => {
  const payment = withFormat('1 234,56');
  assert.equal(payment.parseMoney('1 000,00'), 1000);
  assert.equal(payment.parseMoney('1 000,00'), 1000);
});

test('CRM.payment.parseMoney with a zero-decimal currency format', () => {
  assert.equal(withFormat('1,235').parseMoney('12,345'), 12345);
});

test('CRM.payment.parseMoney defaults to "," and "." without CRM.formatMoney', () => {
  assert.equal(loadCrmPayment().parseMoney('1,000.00'), 1000);
});

test('CRM.payment.parseMoney passes numbers through and gives NaN for non-numeric input', () => {
  const payment = withFormat('1,234.56');
  assert.equal(payment.parseMoney(1000.5), 1000.5);
  assert.ok(Number.isNaN(payment.parseMoney('')));
  assert.ok(Number.isNaN(payment.parseMoney('abc')));
});
