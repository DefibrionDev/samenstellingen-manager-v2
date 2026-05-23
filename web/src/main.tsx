import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import { App } from './App';
import { GroupsList } from './pages/GroupsList';
import { GroupDetail } from './pages/GroupDetail';
import { NotFound } from './pages/NotFound';

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
