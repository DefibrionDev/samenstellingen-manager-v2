import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { ProductTypeIssues } from './ProductTypeIssues';
import { afterEach, expect, test, vi } from 'vitest';

function renderWithProviders(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>{ui}</QueryClientProvider>
    </MemoryRouter>,
  );
}

function stubFetch(payload: unknown) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => ({
      ok: true,
      status: 200,
      statusText: 'OK',
      json: async () => payload,
    })),
  );
}

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont producttype-issues met verwachte waarde en CLI-actie', async () => {
  stubFetch([
    {
      afasItemcode: '21012-60110',
      issueType: 'variant-fixbaar',
      baseItemcode: '21012',
      current01: null,
      current02: null,
      expected01: 'AED pakket',
      expected02: 'C1A',
      groupName: 'Mindray Beneheart C1',
      cliHint: 'Draai `producttype:fix-variants --apply`.',
    },
  ]);

  renderWithProviders(<ProductTypeIssues />);

  await waitFor(() => expect(screen.getByText('21012-60110')).toBeInTheDocument());
  expect(screen.getByText('variant-fixbaar')).toBeInTheDocument();
  expect(screen.getByText('AED pakket / C1A')).toBeInTheDocument();
  expect(screen.getByText('Draai `producttype:fix-variants --apply`.')).toBeInTheDocument();
});

test('toont success-melding als er geen issues zijn', async () => {
  stubFetch([]);

  renderWithProviders(<ProductTypeIssues />);

  await waitFor(() =>
    expect(screen.getByText(/Geen producttype-issues/)).toBeInTheDocument(),
  );
});
