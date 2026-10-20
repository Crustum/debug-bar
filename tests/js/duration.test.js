import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { formatDuration } from '../../webroot/js/duration.js';

describe('formatDuration', () => {
  it('renders an em dash for missing or non-numeric values', () => {
    assert.equal(formatDuration(null), '—');
    assert.equal(formatDuration(''), '—');
    assert.equal(formatDuration('abc'), '—');
    assert.equal(formatDuration(Number.NaN), '—');
    assert.equal(formatDuration(undefined), '—');
    assert.equal(formatDuration(Number.POSITIVE_INFINITY), '—');
  });

  it('renders seconds with up to two decimals', () => {
    assert.equal(formatDuration(1000), '1 s');
    assert.equal(formatDuration(1500), '1.5 s');
    assert.equal(formatDuration(2000), '2 s');
    assert.equal(formatDuration(1234), '1.23 s');
    assert.equal(formatDuration('1500'), '1.5 s');
  });

  it('renders milliseconds below one second', () => {
    assert.equal(formatDuration(1), '1 ms');
    assert.equal(formatDuration(250), '250 ms');
    assert.equal(formatDuration(12.5), '12.5 ms');
  });

  it('renders microseconds below one millisecond', () => {
    assert.equal(formatDuration(0), '0 µs');
    assert.equal(formatDuration(0.5), '500 µs');
    assert.equal(formatDuration(0.001), '1.0 µs');
    assert.equal(formatDuration(0.0009), '0.9 µs');
    assert.equal(formatDuration(0.00021), '0.2 µs');
    assert.equal(formatDuration(0.00004), '0.0 µs');
  });

  it('clamps negative values to zero', () => {
    assert.equal(formatDuration(-5), '0 µs');
  });

  it('renders hours above one hour', () => {
    assert.equal(formatDuration(3600000), '1 h');
    assert.equal(formatDuration(5400000), '1 h 30 min');
    assert.equal(formatDuration(7200000), '2 h');
  });

  it('renders minutes above one minute', () => {
    assert.equal(formatDuration(60000), '1 min');
    assert.equal(formatDuration(90000), '1 min 30 s');
    assert.equal(formatDuration(120000), '2 min');
  });
});
