import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { GroupDetail } from './GroupDetail';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <MemoryRouter initialEntries={[path]}>
      <QueryClientProvider client={client}>
        <Routes>
          <Route path="/groups/:familyHead" element={<GroupDetail />} />
        </Routes>
      </QueryClientProvider>
    </MemoryRouter>,
  );
}

beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string) => {
      // Route op URL: detail vs prijzen (ArticlePricesTable triggert eigen call).
      if (url.includes('/prices')) {
        return {
          ok: true,
          status: 200,
          statusText: 'OK',
          json: async () => [],
        };
      }
      return {
        ok: true,
        status: 200,
        statusText: 'OK',
        json: async () => ({
          familyHead: '52112',
          name: 'Reanibex 100 Semi-Auto',
          modelNameNl: null,
          modelNameFr: null,
          modelNameEn: null,
          bases: [
            {
              id: 1,
              name: 'AED pakket NL',
              languageCode: 'NL',
              afasItemcode: '11142',
              variantLabel: null,
              publishedOn: ['Reseller NL'],
              items: [
                { itemcode: '50013', label: 'AED NL' },
                { itemcode: '70112', label: 'Reanimatiekit' },
              ],
            },
            {
              id: 2,
              name: 'AED pakket DE 4G',
              languageCode: 'DE',
              afasItemcode: '21018-DE',
              variantLabel: '4G',
              publishedOn: [],
              items: [{ itemcode: '50014', label: 'AED DE' }],
            },
          ],
        }),
      };
    }),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont base als accordion met afas-itemcode en klapt items uit', async () => {
  renderAt('/groups/52112');

  await waitFor(() => expect(screen.getByRole('heading', { name: 'Reanibex 100 Semi-Auto' })).toBeInTheDocument());
  const baseHeader = screen.getByText('AED pakket NL');
  expect(baseHeader).toBeInTheDocument();
  // AFAS-SKU staat in de accordion-summary, naast de naam.
  expect(screen.getByText('11142')).toBeInTheDocument();

  fireEvent.click(baseHeader);
  await waitFor(() => expect(screen.getByText('50013')).toBeInTheDocument());
  expect(screen.getByText('AED NL')).toBeInTheDocument();
  expect(screen.getByText('70112')).toBeInTheDocument();
});

test('toont variantLabel als outlined chip naast de base met label', async () => {
  renderAt('/groups/52112');

  await waitFor(() => expect(screen.getByText('AED pakket DE 4G')).toBeInTheDocument());
  // Label-chip "4G" hoort alleen bij de DE-base te staan, niet bij de NL-base.
  expect(screen.getByText('4G')).toBeInTheDocument();
});

test('toont parent-mismatch banner als een base een afwijkende afasItemcodeParent heeft', async () => {
  vi.unstubAllGlobals();
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string) => {
      if (url.includes('/prices')) {
        return { ok: true, status: 200, statusText: 'OK', json: async () => [] };
      }
      return {
        ok: true,
        status: 200,
        statusText: 'OK',
        json: async () => ({
          familyHead: '21018',
          name: 'Mindray C1 semi',
          modelNameNl: null,
          modelNameFr: null,
          modelNameEn: null,
          bases: [
            {
              id: 1,
              name: 'NL base',
              languageCode: 'NL',
              afasItemcode: '21018',
              afasItemcodeParent: '21018',
              variantLabel: null,
              publishedOn: [],
              items: [],
            },
            {
              id: 2,
              name: '3-talig',
              languageCode: 'NL/EN/FR',
              afasItemcode: '21011',
              afasItemcodeParent: '21017',
              variantLabel: null,
              publishedOn: [],
              items: [],
            },
          ],
        }),
      };
    }),
  );

  renderAt('/groups/21018');

  await waitFor(() => expect(screen.getByText(/1 base\(s\) hebben een AFAS-parent/i)).toBeInTheDocument());
  // De afwijkende base toont z'n itemcode + parent in de banner-tabel.
  // 21017 staat alleen in de banner, dus uniek vindbaar.
  expect(screen.getByText('21017')).toBeInTheDocument();
});

test('geen parent-mismatch banner als alle bases dezelfde parent hebben als familyHead', async () => {
  // Standaard fixture: NL+DE bases zonder afasItemcodeParent — banner moet weg blijven.
  renderAt('/groups/52112');
  await waitFor(() => expect(screen.getByText('AED pakket NL')).toBeInTheDocument());
  expect(screen.queryByText(/afwijkt van de family-head/i)).not.toBeInTheDocument();
});
