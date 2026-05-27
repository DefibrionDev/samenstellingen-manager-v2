import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { PrijslijstWhitelist } from './PrijslijstWhitelist';
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
          prijslijstId: '010',
          omschrijving: 'Farys',
          reden: 'kleine klant-specifieke catalogus',
          aangemaaktOp: '2026-05-27 12:00:00',
        },
        {
          prijslijstId: '999',
          omschrijving: null,
          reden: 'test zonder snapshot',
          aangemaaktOp: '2026-05-27 12:00:00',
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont prijslijst-whitelist met omschrijving + onbekend-fallback', async () => {
  renderWithProviders(<PrijslijstWhitelist />);

  await waitFor(() => expect(screen.getByText('010')).toBeInTheDocument());
  expect(screen.getByText('Farys')).toBeInTheDocument();
  expect(screen.getByText('(onbekend)')).toBeInTheDocument();
  expect(screen.getByText(/Beheren via/)).toBeInTheDocument();
});
