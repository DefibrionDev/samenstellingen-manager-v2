import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { Woocommerce } from './Woocommerce';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';

function renderWithProviders(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>{ui}</QueryClientProvider>
    </MemoryRouter>,
  );
}

const indexResponse = {
  stores: [
    { id: 1, name: 'defibrion.nl' },
    { id: 2, name: 'defibrion.fr' },
  ],
  rows: [
    {
      afasItemcode: '11111',
      cells: [
        { storeId: 1, storeName: 'defibrion.nl', cell: { wcProductId: 101, wcType: 'simple', sku: 'sku-101', name: 'PAD 350P NL', status: 'publish', permalink: null } },
        { storeId: 2, storeName: 'defibrion.fr', cell: null },
      ],
    },
  ],
};

const orphansResponse = [
  {
    storeId: 1,
    storeName: 'defibrion.nl',
    wcProductId: 9999,
    wcType: 'simple',
    sku: 'PP-EXTRA',
    name: 'PRESTAN Instructor Kit',
    status: 'draft',
    afasItemcode: null,
    permalink: 'https://defibrion.nl/p/9999',
  },
];

const storesResponse = [
  { id: 1, name: 'defibrion.nl', baseUrl: 'https://defibrion.nl', metaKey: '_afas_artikelnummer', itemCount: 2577 },
];

beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string) => {
      let body: unknown = null;
      if (url === '/api/wc/index') body = indexResponse;
      else if (url === '/api/wc/orphans') body = orphansResponse;
      else if (url === '/api/wc/stores') body = storesResponse;
      return {
        ok: true,
        status: 200,
        statusText: 'OK',
        json: async () => body,
      };
    }),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont de index-tab met itemcode en store-kolommen', async () => {
  renderWithProviders(<Woocommerce />);

  await waitFor(() => expect(screen.getByText('11111')).toBeInTheDocument());
  expect(screen.getByText('defibrion.nl')).toBeInTheDocument();
  expect(screen.getByText('defibrion.fr')).toBeInTheDocument();
});

test('Orphans-tab toont WC-producten zonder match', async () => {
  renderWithProviders(<Woocommerce />);

  fireEvent.click(screen.getByRole('tab', { name: 'Orphans' }));
  await waitFor(() => expect(screen.getByText('PRESTAN Instructor Kit')).toBeInTheDocument());
  expect(screen.getByText('geen meta')).toBeInTheDocument();
});

test('Stores-tab toont snapshot-aantal per shop', async () => {
  renderWithProviders(<Woocommerce />);

  fireEvent.click(screen.getByRole('tab', { name: 'Stores' }));
  await waitFor(() => expect(screen.getByText('2577')).toBeInTheDocument());
  expect(screen.getByText('_afas_artikelnummer')).toBeInTheDocument();
});
