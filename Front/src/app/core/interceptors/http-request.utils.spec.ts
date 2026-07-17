import { isTranslationAssetRequest } from './http-request.utils';

describe('http-request.utils', () => {
  it('identifies translation asset requests by i18n path segment', () => {
    expect(isTranslationAssetRequest('/assets/i18n/es.json')).toBe(true);
    expect(isTranslationAssetRequest('https://cdn.example.com/i18n/en.json')).toBe(true);
    expect(isTranslationAssetRequest('/api/v1/items')).toBe(false);
  });
});
