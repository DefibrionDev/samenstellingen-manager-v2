import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { WebsiteSettings } from './WebsiteSettings';
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
        { id: 1, name: 'Reseller NL', ffSyncUuid: 'U4E3E32DEFB374A1BA9F8680B8C405907', ffTonenUuid: 'UD77EC755E2F1404EB184A956685A7C0C' },
        { id: 2, name: 'Reseller FR', ffSyncUuid: 'UAAAAAAA22222222', ffTonenUuid: 'UBBBBBBB33333333' },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont websites read-only met gemaskeerde uuids', async () => {
  renderWithProviders(<WebsiteSettings />);

  await waitFor(() => expect(screen.getByText('Reseller NL')).toBeInTheDocument());
  expect(screen.getByText('Reseller FR')).toBeInTheDocument();
  // UUID's worden gemaskeerd weergegeven (eerste 8 + ellipsis + laatste 4).
  expect(screen.getByText('U4E3E32D…5907')).toBeInTheDocument();
});
