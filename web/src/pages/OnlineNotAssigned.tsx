import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, OnlineNotAssignedRow } from '../api';

interface Row extends OnlineNotAssignedRow {
  id: number;
}

const columns: GridColDef<Row>[] = [
  { field: 'afasItemcode', headerName: 'Itemcode', width: 200 },
  { field: 'baseAfasItemcode', headerName: 'Base', width: 200 },
  { field: 'websiteName', headerName: 'Online op (niet toegekend)', flex: 1, minWidth: 240 },
];

export function OnlineNotAssigned() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['online-not-assigned'],
    queryFn: api.listOnlineNotAssigned,
  });

  if (isError) {
    return <Alert severity="error">Kon online-niet-toegekend niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, index) => ({ ...row, id: index }));

  return (
    <Stack spacing={2}>
      <Stack>
        <Typography variant="h5" component="h1">
          Online maar niet toegekend
        </Typography>
        <Typography variant="body2" color="text.secondary">
          AFAS-itemcodes die op een website online staan (<code>Sync</code>/<code>Tonen</code>) terwijl die website
          in de tool niet is toegekend. De publicatie-sync raakt deze <strong>niet</strong> aan — toekennen via{' '}
          <code>base:publish</code> of in AFAS uitzetten. Gelezen uit de lokale snapshot (ververst bij <code>afas:pull</code>).
          {rows.length > 0 && ` (${rows.length} rijen)`}
        </Typography>
      </Stack>
      {isLoading ? (
        <Skeleton variant="rectangular" height={400} />
      ) : (
        <DataGrid<Row>
          rows={rows}
          columns={columns}
          autoHeight
          disableRowSelectionOnClick
          initialState={{ pagination: { paginationModel: { pageSize: 50 } } }}
          pageSizeOptions={[25, 50, 100]}
        />
      )}
    </Stack>
  );
}
