'use client';

import { useLanguage, Lang } from '@/lib/LanguageContext';
import en from './en.json';
import gu from './gu.json';

type TranslationKeys = typeof en;

const translations: Record<Lang, TranslationKeys> = { en, gu };

/**
 * Get a nested translation value by dot-separated key path.
 * Usage: translate('step1.fullName', lang) → "Full Name" | "નામ"
 */
export function translate(keyPath: string, lang: Lang): string {
  const keys = keyPath.split('.');
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let current: any = translations[lang];
  for (const key of keys) {
    if (current && typeof current === 'object' && key in current) {
      current = current[key];
    } else {
      return keyPath; // fallback: return key path
    }
  }
  return typeof current === 'string' ? current : keyPath;
}

/**
 * React hook for accessing translations.
 * Returns a `tr` function for JSON key paths
 * and the existing `t(en, gu)` inline helper.
 */
export function useTranslation() {
  const { lang, setLang, t } = useLanguage();

  const tr = (keyPath: string): string => translate(keyPath, lang);

  return { lang, setLang, t, tr };
}
