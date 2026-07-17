export function contrastColor(hex: string): '#ffffff' | '#000000' {
  const normalized = hex.trim().toLowerCase();
  const rgb = normalized.startsWith('#') ? normalized.slice(1) : normalized;

  if (rgb.length !== 6) {
    return '#000000';
  }

  const r = parseInt(rgb.slice(0, 2), 16);
  const g = parseInt(rgb.slice(2, 4), 16);
  const b = parseInt(rgb.slice(4, 6), 16);

  if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
    return '#000000';
  }

  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.5 ? '#000000' : '#ffffff';
}
