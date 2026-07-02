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
        { name: 'Reanibex 100 Semi-Auto', familyHead: '52112', baseCount: 3, baseItemCount: 12, familyHeadIsBase: true, noMatchCounts: {}, parentMismatchCount: 0, modelNameNl: 'Reanibex 100', modelNameFr: null, modelNameEn: null },
        { name: 'Lifepak CR2', familyHead: '11161', baseCount: 5, baseItemCount: 20, familyHeadIsBase: false, noMatchCounts: { aanmaakbaar: 42 }, parentMismatchCount: 0, modelNameNl: null, modelNameFr: null, modelNameEn: null },
        { name: 'Mindray C1 semi', familyHead: '21018', baseCount: 7, baseItemCount: 21, familyHeadIsBase: true, noMatchCounts: { base_niet_gematcht: 4 }, parentMismatchCount: 88, modelNameNl: null, modelNameFr: null, modelNameEn: null },
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

test('toont no-match aantallen als chips wanneer > 0', async () => {
  renderWithProviders(<GroupsList />);

  await waitFor(() => expect(screen.getByText('Lifepak CR2')).toBeInTheDocument());
  // 42 aanmaakbaar voor Lifepak CR2, 4 base-niet-gematcht voor Mindray (uniek in fixture).
  expect(screen.getByText('42')).toBeInTheDocument();
  expect(screen.getByText('4')).toBeInTheDocument();
});

test('toont parent-drift chip op groep met parentMismatchCount > 0', async () => {
  renderWithProviders(<GroupsList />);

  await waitFor(() => expect(screen.getByText('Mindray C1 semi')).toBeInTheDocument());
  // 88 is parentMismatchCount voor Mindray C1 semi (uniek in fixture).
  expect(screen.getByText('88')).toBeInTheDocument();
});
