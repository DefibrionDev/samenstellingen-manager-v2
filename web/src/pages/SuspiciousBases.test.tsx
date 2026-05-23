import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { SuspiciousBases } from './SuspiciousBases';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';

function renderWithProviders(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>{ui}</QueryClientProvider>
    </MemoryRouter>,
  );
}

beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => ({
      ok: true,
      status: 200,
      statusText: 'OK',
      json: async () => [
        {
          afasItemcode: '11683-60110',
          name: 'Zoll AED Plus + ARKY Backpack',
          expectedAccessoireItemcode: '60110',
          expectedAccessoireLabel: 'EHBO-Rugzak YELLOW LARGE RED',
          bom: ['10683', '70112', '81511'],
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont verdachte bases met verwachte accessoire en BOM', async () => {
  renderWithProviders(<SuspiciousBases />);

  await waitFor(() => expect(screen.getByText('11683-60110')).toBeInTheDocument());
  expect(screen.getByText('60110 — EHBO-Rugzak YELLOW LARGE RED')).toBeInTheDocument();
  expect(screen.getByText('10683, 70112, 81511')).toBeInTheDocument();
});
