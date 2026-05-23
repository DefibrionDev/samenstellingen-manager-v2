import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { NameDrift } from './NameDrift';
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
          afasItemcode: '52112',
          groupName: 'Reanibex 100 Semi-Auto',
          familyHead: '52112',
          baseName: 'AED pakket NL',
          languageCode: 'NL',
          accessoireItemcode: null,
          accessoireLabel: null,
          expected: 'AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
          actual: 'AED Pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont naam-drift met expected/actual', async () => {
  renderWithProviders(<NameDrift />);

  await waitFor(() =>
    expect(
      screen.getByText('AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset'),
    ).toBeInTheDocument(),
  );
  expect(
    screen.getByText('AED Pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset'),
  ).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Exporteer CSV/i })).toBeEnabled();
});
