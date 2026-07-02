import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import { App } from './App';
import { AccessoiresList } from './pages/AccessoiresList';
import { BlacklistList } from './pages/BlacklistList';
import { GroupsList } from './pages/GroupsList';
import { GroupDetail } from './pages/GroupDetail';
import { NoMatchVariants } from './pages/NoMatchVariants';
import { OnlineNotAssigned } from './pages/OnlineNotAssigned';
import { NameDrift } from './pages/NameDrift';
import { NotFound } from './pages/NotFound';
import { DuplicateBoms } from './pages/DuplicateBoms';
import { PriceDrift } from './pages/PriceDrift';
import { BasePriceGaps } from './pages/BasePriceGaps';
import { PrijslijstWhitelist } from './pages/PrijslijstWhitelist';
import { StickerDrift } from './pages/StickerDrift';
import { ProductTypeIssues } from './pages/ProductTypeIssues';
import { SuspiciousBases } from './pages/SuspiciousBases';
import { WebsiteSettings } from './pages/WebsiteSettings';
import { Woocommerce } from './pages/Woocommerce';

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 30_000, refetchOnWindowFocus: false } },
});

const theme = createTheme({ palette: { mode: 'light' } });

const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      { index: true, element: <GroupsList /> },
      { path: 'groups/:familyHead', element: <GroupDetail /> },
      { path: 'groups/:familyHead/:tab', element: <GroupDetail /> },
      { path: 'accessoires', element: <AccessoiresList /> },
      { path: 'blacklist', element: <BlacklistList /> },
      { path: 'no-match', element: <NoMatchVariants /> },
      { path: 'online-not-assigned', element: <OnlineNotAssigned /> },
      { path: 'name-drift', element: <NameDrift /> },
      { path: 'suspicious-bases', element: <SuspiciousBases /> },
      { path: 'price-drift', element: <PriceDrift /> },
      { path: 'base-price-gaps', element: <BasePriceGaps /> },
      { path: 'prijslijst-whitelist', element: <PrijslijstWhitelist /> },
      { path: 'duplicate-boms', element: <DuplicateBoms /> },
      { path: 'sticker-drift', element: <StickerDrift /> },
      { path: 'product-type-issues', element: <ProductTypeIssues /> },
      { path: 'settings/websites', element: <WebsiteSettings /> },
      { path: 'woocommerce', element: <Woocommerce /> },
      { path: '*', element: <NotFound /> },
    ],
  },
]);

const root = document.getElementById('root');
if (!root) throw new Error('Root element ontbreekt');

ReactDOM.createRoot(root).render(
  <React.StrictMode>
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    </ThemeProvider>
  </React.StrictMode>,
);
