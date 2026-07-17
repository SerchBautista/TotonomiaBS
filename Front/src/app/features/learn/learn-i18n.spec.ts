import { describe, expect, it } from 'vitest';
import es from '../../../../public/i18n/es.json';
import en from '../../../../public/i18n/en.json';

const REQUIRED_LEARN_UI_KEYS = [
  'learn.nav.learn',
  'learn.read_more',
  'learn.back_to_hub',
  'learn.overview_title',
  'learn.showcase_zoom',
  'learn.showcase_zoom_close',
  'learn.showcase_zoom_in',
  'learn.showcase_zoom_out',
  'learn.topic_not_found',
  'learn.loading',
  'learn.load_error',
];

function getNestedValue(source: Record<string, unknown>, path: string): unknown {
  return path.split('.').reduce<unknown>((current, key) => {
    if (current && typeof current === 'object' && key in (current as Record<string, unknown>)) {
      return (current as Record<string, unknown>)[key];
    }
    return undefined;
  }, source);
}

describe('learn i18n', () => {
  it('includes shared learn UI keys in Spanish and English', () => {
    for (const key of REQUIRED_LEARN_UI_KEYS) {
      expect(getNestedValue(es, key), `${key} missing in es`).toBeTruthy();
      expect(getNestedValue(en, key), `${key} missing in en`).toBeTruthy();
    }
  });
});
