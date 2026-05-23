import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { MissingVariants } from './MissingVariants';
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
          baseName: 'AED pakket NL',
          baseAfasSku: '52112',
          accessoireItemcode: '60110',
          accessoireLabel: 'EHBO-Rugzak',
          expectedBom: ['50013', '60110'],
          suggestedSku: '52112-60110',
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont missing-variants en rapporteert aantallen', async () => {
  renderWithProviders(<MissingVariants />);

  await waitFor(() => expect(screen.getByText('Reanibex')).toBeInTheDocument());
  expect(screen.getByText('AED pakket NL')).toBeInTheDocument();
  expect(screen.getByText('52112-60110')).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Exporteer CSV/i })).toBeEnabled();
});
