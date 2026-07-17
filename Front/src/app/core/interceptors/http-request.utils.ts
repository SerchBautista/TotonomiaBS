/** Static translation files must bypass global HTTP error handling to avoid DI cycles with TranslateService. */
export function isTranslationAssetRequest(url: string): boolean {
  return url.includes('/i18n/');
}
