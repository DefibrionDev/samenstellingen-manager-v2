import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { PriceDrift } from './PriceDrift';
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
          groupName: 'Reanibex',
          baseAfasItemcode: '11142',
          baseName: 'AED pakket NL',
          variantAfasItemcode: '11142-60110',
          accessoireItemcode: '60110',
          accessoireLabel: 'EHBO-Rugzak',
          expectedDeltaCents: 2500,
          expectedDeltaEur: '€ 25,00',
          prijslijstId: '*****',
          prijslijstOmschrijving: 'Basisprijslijst (excl BTW)',
          basePrijsCents: 189900,
          basePrijsEur: '€ 1.899,00',
          variantPrijsCents: 193000,
          variantPrijsEur: '€ 1.930,00',
          actualDeltaCents: 3100,
          actualDeltaEur: '€ 31,00',
          status: 'toeslag-drift',
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont prijs-drift met expected/actual', async () => {
  renderWithProviders(<PriceDrift />);

  await waitFor(() => expect(screen.getByText('11142-60110')).toBeInTheDocument());
  expect(screen.getByText('€ 25,00')).toBeInTheDocument();
  expect(screen.getByText('€ 31,00')).toBeInTheDocument();
  expect(screen.getByText(/Basisprijslijst \(excl BTW\)/)).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Exporteer CSV/i })).toBeEnabled();
});
