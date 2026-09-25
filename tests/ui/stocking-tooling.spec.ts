import { expect, test } from '@playwright/test';

test('Stocking Playwright harness is executable', () => {
  expect(typeof test).toBe('function');
  expect(typeof expect).toBe('function');
});
