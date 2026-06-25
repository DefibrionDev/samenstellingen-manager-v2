import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { BasePriceGaps } from './BasePriceGaps';
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
          prijslijstId: '003',
          prijslijstOmschrijving: 'Dealers FR',
          baseAfasItemcode: '11142',
          groupName: 'Reanibex',
          baseName: 'AED pakket NL',
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont bases die ontbreken in een whitelist-prijslijst', async () => {
  renderWithProviders(<BasePriceGaps />);

  await waitFor(() => expect(screen.getByText('11142')).toBeInTheDocument());
  expect(screen.getByText('003 — Dealers FR')).toBeInTheDocument();
  expect(screen.getByText('AED pakket NL')).toBeInTheDocument();
});
