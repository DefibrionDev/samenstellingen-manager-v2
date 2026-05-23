import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, BlacklistEntry } from '../api';

const columns: GridColDef<BlacklistEntry>[] = [
  { field: 'itemcode', headerName: 'Itemcode', width: 140 },
  { field: 'reason', headerName: 'Reden', flex: 1, minWidth: 320 },
];

export function BlacklistList() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['bom-blacklist'],
    queryFn: api.listBlacklist,
  });

  if (isError) {
    return <Alert severity="error">Kon blacklist niet laden: {(error as Error).message}</Alert>;
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        BOM-blacklist
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Itemcodes die — als ze in een AFAS-samenstelling's BOM staan — die samenstelling
        diskwalificeren als base-kandidaat. Beheren via{' '}
        <code>samenstelling:blacklist-bom &lt;itemcode&gt; '&lt;reden&gt;'</code>.
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={300} />
      ) : (
        <DataGrid<BlacklistEntry>
          rows={data ?? []}
          columns={columns}
          getRowId={(row) => row.itemcode}
          autoHeight
          disableRowSelectionOnClick
        />
      )}
    </Stack>
  );
}
