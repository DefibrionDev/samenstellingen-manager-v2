import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { PriceDrift } from './PriceDrift';
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
        // 3 drift-rijen voor 11142-60110 met dezelfde delta — moeten samenvatten als "overal op €0"
        {
          groupName: 'Reanibex',
          baseAfasItemcode: '11142',
          baseName: 'AED NL',
          variantAfasItemcode: '11142-60110',
          accessoireItemcode: '60110',
          accessoireLabel: 'EHBO-Rugzak',
          expectedDeltaCents: 2500,
          expectedDeltaEur: '€ 25,00',
          staffelAantal: null,
          prijslijstId: '003',
          prijslijstOmschrijving: 'Dealers FR',
          basePrijsEur: '€ 1.289,00',
          variantPrijsCents: 128900,
          variantPrijsEur: '€ 1.289,00',
          actualDeltaCents: 0,
          actualDeltaEur: '€ 0,00',
          status: 'toeslag-drift',
        },
        {
          groupName: 'Reanibex',
          baseAfasItemcode: '11142',
          baseName: 'AED NL',
          variantAfasItemcode: '11142-60110',
          accessoireItemcode: '60110',
          accessoireLabel: 'EHBO-Rugzak',
          expectedDeltaCents: 2500,
          expectedDeltaEur: '€ 25,00',
          staffelAantal: null,
          prijslijstId: '027',
          prijslijstOmschrijving: 'Dealers Benelux',
          basePrijsEur: '€ 1.289,00',
          variantPrijsCents: 128900,
          variantPrijsEur: '€ 1.289,00',
          actualDeltaCents: 0,
          actualDeltaEur: '€ 0,00',
          status: 'toeslag-drift',
        },
        // 1 missing voor 11142-60213 — moet samenvatten als "ontbreekt in 1 prijslijst"
        {
          groupName: 'Reanibex',
          baseAfasItemcode: '11142',
          baseName: 'AED NL',
          variantAfasItemcode: '11142-60213',
          accessoireItemcode: '60213',
          accessoireLabel: 'Outdoor kast',
          expectedDeltaCents: 25000,
          expectedDeltaEur: '€ 250,00',
          staffelAantal: null,
          prijslijstId: '027',
          prijslijstOmschrijving: 'Dealers Benelux',
          basePrijsEur: '€ 1.289,00',
          variantPrijsCents: null,
          variantPrijsEur: null,
          actualDeltaCents: null,
          actualDeltaEur: null,
          status: 'missing',
        },
      ],
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont default de toeslag-drift-tab met samenvatting per variant', async () => {
  renderWithProviders(<PriceDrift />);

  await waitFor(() => expect(screen.getByText('11142-60110')).toBeInTheDocument());

  // Drift-tab actief: 11142-60110 zichtbaar (drift), 11142-60213 niet (alleen missing)
  expect(screen.getByText('Toeslag staat overal op € 0,00 i.p.v. € 25,00')).toBeInTheDocument();
  expect(screen.queryByText('11142-60213')).not.toBeInTheDocument();
  expect(screen.getByText('2 drift')).toBeInTheDocument();
  // Tab-counters
  expect(screen.getByRole('tab', { name: /Toeslag-drift \(2\)/ })).toBeInTheDocument();
  expect(screen.getByRole('tab', { name: /Missing \(1\)/ })).toBeInTheDocument();
});

test('switchen naar missing-tab toont alleen missing-groepen', async () => {
  renderWithProviders(<PriceDrift />);

  await waitFor(() => expect(screen.getByText('11142-60110')).toBeInTheDocument());

  fireEvent.click(screen.getByRole('tab', { name: /Missing \(1\)/ }));

  expect(screen.getByText('11142-60213')).toBeInTheDocument();
  expect(screen.queryByText('11142-60110')).not.toBeInTheDocument();
  expect(screen.getByText('Variant ontbreekt in 1 (lijst, staffel)-combinatie')).toBeInTheDocument();
});

test('uitklappen toont per-prijslijst-detail in basis+accessoire-vorm', async () => {
  renderWithProviders(<PriceDrift />);

  await waitFor(() => expect(screen.getByText('11142-60110')).toBeInTheDocument());

  fireEvent.click(screen.getByText('11142-60110'));

  expect(screen.getByText('003 — Dealers FR')).toBeInTheDocument();
  expect(screen.getByText('027 — Dealers Benelux')).toBeInTheDocument();
  // Detail-regel bevat 'Basis ... + accessoire ... Waarde in AFAS' — exact format
  // (duizend-separator) hangt af van locale en toLocaleString-implementatie.
  const detailRows = screen.getAllByText((_, node) => {
    const t = node?.textContent ?? '';
    return t.includes('Basis') && t.includes('+ accessoire') && t.includes('Waarde in AFAS');
  });
  expect(detailRows.length).toBeGreaterThan(0);
});
