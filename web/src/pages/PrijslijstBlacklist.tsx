import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, PrijslijstBlacklistEntry } from '../api';

const columns: GridColDef<PrijslijstBlacklistEntry>[] = [
  { field: 'prijslijstId', headerName: 'ID', width: 100 },
  {
    field: 'omschrijving',
    headerName: 'Omschrijving',
    width: 240,
    valueGetter: (_, row) => row.omschrijving ?? '(onbekend)',
  },
  { field: 'reden', headerName: 'Reden', flex: 1, minWidth: 320 },
  { field: 'aangemaaktOp', headerName: 'Aangemaakt-op', width: 180 },
];

export function PrijslijstBlacklist() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['prijslijst-blacklist'],
    queryFn: api.listPrijslijstBlacklist,
  });

  if (isError) {
    return <Alert severity="error">Kon prijslijst-blacklist niet laden: {(error as Error).message}</Alert>;
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Prijslijst-blacklist
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Prijslijst-IDs die volledig worden overgeslagen in <code>audit:prices</code> — zowel
        toeslag-drift als missing-rijen verdwijnen voor deze lijsten. Bedoeld voor kleine
        klantspecifieke catalogi die niet alle AEDs hoeven te bevatten. Beheren via{' '}
        <code>bin/samenstellingen pricelist:blacklist &lt;id&gt; '&lt;reden&gt;'</code> /{' '}
        <code>pricelist:unblacklist &lt;id&gt;</code>.
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={300} />
      ) : (
        <DataGrid<PrijslijstBlacklistEntry>
          rows={data ?? []}
          columns={columns}
          getRowId={(row) => row.prijslijstId}
          autoHeight
          disableRowSelectionOnClick
        />
      )}
    </Stack>
  );
}
