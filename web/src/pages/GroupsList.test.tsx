import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { GroupsList } from './GroupsList';
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
        { name: 'Reanibex 100 Semi-Auto', familyHead: '52112', baseCount: 3, baseItemCount: 12, familyHeadIsBase: true },
        { name: 'Lifepak CR2', familyHead: '11161', baseCount: 5, baseItemCount: 20, familyHeadIsBase: false },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont de geladen groepen in een tabel', async () => {
  renderWithProviders(<GroupsList />);

  await waitFor(() => {
    expect(screen.getByText('Reanibex 100 Semi-Auto')).toBeInTheDocument();
  });
  expect(screen.getByText('Lifepak CR2')).toBeInTheDocument();
  expect(screen.getByText('52112')).toBeInTheDocument();
});
