import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { OnlineNotAssigned } from './OnlineNotAssigned';
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
        { afasItemcode: '11111-60110', baseAfasItemcode: '11111', websiteName: 'ARKY' },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont online-maar-niet-toegekend itemcodes met de website', async () => {
  renderWithProviders(<OnlineNotAssigned />);

  await waitFor(() => expect(screen.getByText('11111-60110')).toBeInTheDocument());
  expect(screen.getByText('ARKY')).toBeInTheDocument();
});
