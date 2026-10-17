import { describe, expect, it } from 'vitest';
import { translate } from './i18n';

describe('translate', () => {
    const dictionary = { Dashboard: 'Tableau de bord', 'by :worker': 'par :worker' };

    it('translates known texts and keeps the English text otherwise', () => {
        expect(translate(dictionary, 'Dashboard')).toBe('Tableau de bord');
        expect(translate(dictionary, 'Unknown text')).toBe('Unknown text');
    });

    it('replaces the placeholders', () => {
        expect(translate(dictionary, 'by :worker', { worker: 'host:42' })).toBe('par host:42');
        expect(translate({}, ':count job(s)', { count: 3 })).toBe('3 job(s)');
        expect(translate({}, ':from–:to of :total', { from: 1, to: 5, total: 12 })).toBe('1–5 of 12');
    });
});
