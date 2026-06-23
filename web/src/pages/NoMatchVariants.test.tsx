import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { NoMatchVariants } from './NoMatchVariants';
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
          groupName: 'Mindray C1A vol',
          familyHead: '21019-UK',
          baseName: 'AED Package FR-EN-NL',
          baseAfasSku: '21012-FR',
          accessoireItemcode: '60110',
          accessoireLabel: 'ARKY backpack',
          expectedBom: ['20012-FR', '60110', '70112', '81211'],
          verwachteItemcode: '21012-FR-60110',
          bestaandeAfasItemcode: '21012-FR-60110',
          exacteBomMatchItemcode: null,
          ontbrekendeItemcodes: ['81211'],
          extraItemcodes: [],
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont no-match varianten met bestaande compositie en ontbrekende itemcode', async () => {
  renderWithProviders(<NoMatchVariants />);

  await waitFor(() => expect(screen.getByText('Mindray C1A vol')).toBeInTheDocument());
  expect(screen.getByText('AED Package FR-EN-NL')).toBeInTheDocument();
  // De compositie bestaat al in AFAS (bestaat-in-afas-kolom).
  expect(screen.getByText('21012-FR-60110')).toBeInTheDocument();
  // 81211 ontbreekt in die compositie (mist-kolom).
  expect(screen.getByText('81211')).toBeInTheDocument();
});
