import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { DuplicateBoms } from './DuplicateBoms';
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
          fingerprint: '10042,70112,81111',
          memberCount: 2,
          members: [
            { itemcode: '10042', name: 'Defibtech base' },
            { itemcode: '10042-60112', name: 'Defibtech + witte binnenkast' },
          ],
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont duplicate-BOM-groep en klapt members uit', async () => {
  renderWithProviders(<DuplicateBoms />);

  await waitFor(() => expect(screen.getByText('10042,70112,81111')).toBeInTheDocument());

  // Default ingeklapt — members niet zichtbaar
  expect(screen.queryByText('10042')).not.toBeInTheDocument();

  // Klap uit
  fireEvent.click(screen.getByText('10042,70112,81111'));

  expect(screen.getByText('10042')).toBeInTheDocument();
  expect(screen.getByText('10042-60112')).toBeInTheDocument();
});
