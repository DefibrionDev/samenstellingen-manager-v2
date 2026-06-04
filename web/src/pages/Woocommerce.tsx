import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Chip,
  Link,
  Paper,
  Skeleton,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  Tooltip,
  Typography,
} from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutline';
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmpty';
import { api, WooIndexCell } from '../api';

type TabKey = 'index' | 'orphans' | 'stores';

function StatusChip({ cell }: { cell: WooIndexCell | null }) {
  if (cell === null) {
    return (
      <Tooltip title="Niet aanwezig op deze shop">
        <RemoveCircleOutlineIcon fontSize="small" color="disabled" />
      </Tooltip>
    );
  }
  if (cell.status === 'publish') {
    return (
      <Tooltip title={`#${cell.wcProductId} • publish • ${cell.name}`}>
        <CheckCircleIcon fontSize="small" color="success" />
      </Tooltip>
    );
  }
  return (
    <Tooltip title={`#${cell.wcProductId} • ${cell.status} • ${cell.name}`}>
      <HourglassEmptyIcon fontSize="small" color="warning" />
    </Tooltip>
  );
}

function IndexTab() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['wc', 'index'],
    queryFn: api.listWooIndex,
  });

  if (isError) {
    return <Alert severity="error">Kon WC-index niet laden: {(error as Error).message}</Alert>;
  }
  if (isLoading || !data) {
    return <Skeleton variant="rectangular" height={400} />;
  }
  if (data.stores.length === 0) {
    return (
      <Alert severity="info">
        Geen WooCommerce-shops geregistreerd. Voeg toe via{' '}
        <code>bin/samenstellingen wc:store:add</code>.
      </Alert>
    );
  }

  return (
    <Paper>
      <TableContainer>
        <Table size="small" stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>AFAS-itemcode</TableCell>
              {data.stores.map((store) => (
                <TableCell key={store.id} align="center">
                  {store.name}
                </TableCell>
              ))}
            </TableRow>
          </TableHead>
          <TableBody>
            {data.rows.map((row) => (
              <TableRow key={row.afasItemcode}>
                <TableCell>
                  <code>{row.afasItemcode}</code>
                </TableCell>
                {row.cells.map((entry) => (
                  <TableCell key={entry.storeId} align="center">
                    <StatusChip cell={entry.cell} />
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

function OrphansTab() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['wc', 'orphans'],
    queryFn: api.listWooOrphans,
  });

  if (isError) {
    return <Alert severity="error">Kon orphans niet laden: {(error as Error).message}</Alert>;
  }
  if (isLoading || !data) {
    return <Skeleton variant="rectangular" height={400} />;
  }
  if (data.length === 0) {
    return <Alert severity="success">Geen orphans — alle WC-producten matchen op onze AFAS-managed-set.</Alert>;
  }

  return (
    <Paper>
      <TableContainer>
        <Table size="small" stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>Store</TableCell>
              <TableCell>WC-id</TableCell>
              <TableCell>Type</TableCell>
              <TableCell>SKU</TableCell>
              <TableCell>Naam</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>AFAS-meta</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {data.map((orphan) => (
              <TableRow key={`${orphan.storeId}-${orphan.wcProductId}`}>
                <TableCell>{orphan.storeName}</TableCell>
                <TableCell>
                  {orphan.permalink ? (
                    <Link href={orphan.permalink} target="_blank" rel="noopener">
                      {orphan.wcProductId}
                    </Link>
                  ) : (
                    orphan.wcProductId
                  )}
                </TableCell>
                <TableCell>{orphan.wcType}</TableCell>
                <TableCell>{orphan.sku ?? '—'}</TableCell>
                <TableCell>{orphan.name}</TableCell>
                <TableCell>{orphan.status}</TableCell>
                <TableCell>
                  {orphan.afasItemcode === null ? (
                    <Chip label="geen meta" size="small" color="default" />
                  ) : (
                    <code>{orphan.afasItemcode}</code>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

function StoresTab() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['wc', 'stores'],
    queryFn: api.listWooStores,
  });

  if (isError) {
    return <Alert severity="error">Kon stores niet laden: {(error as Error).message}</Alert>;
  }
  if (isLoading || !data) {
    return <Skeleton variant="rectangular" height={200} />;
  }
  if (data.length === 0) {
    return (
      <Alert severity="info">
        Nog geen shops geregistreerd. Voeg toe met{' '}
        <code>bin/samenstellingen wc:store:add &lt;name&gt; &lt;base-url&gt; &lt;ck&gt; &lt;cs&gt;</code>.
      </Alert>
    );
  }

  return (
    <Paper>
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Naam</TableCell>
              <TableCell>Base-URL</TableCell>
              <TableCell>Meta-key</TableCell>
              <TableCell align="right">Items in snapshot</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {data.map((store) => (
              <TableRow key={store.id}>
                <TableCell>{store.name}</TableCell>
                <TableCell>
                  <Link href={store.baseUrl} target="_blank" rel="noopener">
                    {store.baseUrl}
                  </Link>
                </TableCell>
                <TableCell>
                  <code>{store.metaKey}</code>
                </TableCell>
                <TableCell align="right">{store.itemCount}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

export function Woocommerce() {
  const [tab, setTab] = useState<TabKey>('index');

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        WooCommerce
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Index van onze AFAS-managed itemcodes en hun aanwezigheid in de geregistreerde shops.
        Pull verversen via <code>bin/samenstellingen wc:pull</code>.
      </Typography>
      <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
        <Tabs value={tab} onChange={(_, v: TabKey) => setTab(v)}>
          <Tab value="index" label="Index" />
          <Tab value="orphans" label="Orphans" />
          <Tab value="stores" label="Stores" />
        </Tabs>
      </Box>
      {tab === 'index' && <IndexTab />}
      {tab === 'orphans' && <OrphansTab />}
      {tab === 'stores' && <StoresTab />}
    </Stack>
  );
}
