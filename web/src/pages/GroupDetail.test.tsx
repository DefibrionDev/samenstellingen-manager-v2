import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { GroupDetail } from './GroupDetail';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <MemoryRouter initialEntries={[path]}>
      <QueryClientProvider client={client}>
        <Routes>
          <Route path="/groups/:familyHead" element={<GroupDetail />} />
        </Routes>
      </QueryClientProvider>
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
      json: async () => ({
        familyHead: '52112',
        name: 'Reanibex 100 Semi-Auto',
        bases: [
          {
            id: 1,
            name: 'AED pakket NL',
            languageCode: 'NL',
            items: [
              { itemcode: '50013', label: 'AED NL' },
              { itemcode: '70112', label: 'Reanimatiekit' },
            ],
          },
        ],
      }),
    })),
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

test('toont base als accordion en klapt items uit', async () => {
  renderAt('/groups/52112');

  await waitFor(() => expect(screen.getByText('Reanibex 100 Semi-Auto')).toBeInTheDocument());
  const baseHeader = screen.getByText('AED pakket NL');
  expect(baseHeader).toBeInTheDocument();

  fireEvent.click(baseHeader);
  await waitFor(() => expect(screen.getByText('50013')).toBeInTheDocument());
  expect(screen.getByText('AED NL')).toBeInTheDocument();
  expect(screen.getByText('70112')).toBeInTheDocument();
});
