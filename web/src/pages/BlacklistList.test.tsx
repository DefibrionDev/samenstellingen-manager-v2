import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { BlacklistList } from './BlacklistList';
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
      json: async () => [{ itemcode: '81311', reason: 'Waalse stickerset' }],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont blacklist-entries', async () => {
  renderWithProviders(<BlacklistList />);

  await waitFor(() => expect(screen.getByText('Waalse stickerset')).toBeInTheDocument());
  expect(screen.getByText('81311')).toBeInTheDocument();
});
