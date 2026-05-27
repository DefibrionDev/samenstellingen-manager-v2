import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, PrijslijstWhitelistEntry } from '../api';

const columns: GridColDef<PrijslijstWhitelistEntry>[] = [
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

export function PrijslijstWhitelist() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['prijslijst-whitelist'],
    queryFn: api.listPrijslijstWhitelist,
  });

  if (isError) {
    return <Alert severity="error">Kon prijslijst-whitelist niet laden: {(error as Error).message}</Alert>;
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Prijslijst-whitelist
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Prijslijst-IDs die actief worden meegenomen in <code>afas:pull</code> en{' '}
        <code>audit:prices</code>. Alleen deze lijsten worden uit AFAS gehaald én gecheckt
        op toeslag-drift/missing. Lijsten die niet op de whitelist staan worden volledig
        overgeslagen (strict). Lege whitelist = geen prijzen-pull, geen audit-output.
        Beheren via <code>bin/samenstellingen pricelist:whitelist &lt;id&gt; '&lt;reden&gt;'</code> /{' '}
        <code>pricelist:unwhitelist &lt;id&gt;</code>.
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={300} />
      ) : (
        <DataGrid<PrijslijstWhitelistEntry>
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
