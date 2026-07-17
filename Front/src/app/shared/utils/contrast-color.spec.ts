import { contrastColor } from './contrast-color';

describe('contrastColor', () => {
  it('returns white text for dark backgrounds', () => {
    expect(contrastColor('#000000')).toBe('#ffffff');
    expect(contrastColor('1a1a1a')).toBe('#ffffff');
  });

  it('returns black text for light backgrounds', () => {
    expect(contrastColor('#ffffff')).toBe('#000000');
    expect(contrastColor('#f0f0f0')).toBe('#000000');
  });

  it('returns black for invalid hex values', () => {
    expect(contrastColor('not-a-color')).toBe('#000000');
    expect(contrastColor('#abc')).toBe('#000000');
  });
});
