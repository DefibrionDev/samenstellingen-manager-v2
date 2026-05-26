import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { AccessoiresList } from './AccessoiresList';
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
        { itemcode: '60110', label: 'EHBO-Rugzak', deltaCents: 7900, deltaEur: '€ 79,00' },
        { itemcode: '60112', label: 'ARKY witte binnenkast', deltaCents: 29500, deltaEur: '€ 295,00' },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont accessoires uit de catalogus met toeslag', async () => {
  renderWithProviders(<AccessoiresList />);

  await waitFor(() => expect(screen.getByText('EHBO-Rugzak')).toBeInTheDocument());
  expect(screen.getByText('ARKY witte binnenkast')).toBeInTheDocument();
  expect(screen.getByText('€ 79,00')).toBeInTheDocument();
  expect(screen.getByText('€ 295,00')).toBeInTheDocument();
});
