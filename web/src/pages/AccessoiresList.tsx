import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, Accessoire } from '../api';

const columns: GridColDef<Accessoire>[] = [
  { field: 'itemcode', headerName: 'Itemcode', width: 140 },
  { field: 'label', headerName: 'Label', flex: 1, minWidth: 320 },
  {
    field: 'deltaEur',
    headerName: 'Toeslag',
    width: 140,
    align: 'right',
    headerAlign: 'right',
    valueGetter: (_, row) => row.deltaEur ?? '€ 0,00',
  },
];

export function AccessoiresList() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['accessoires'],
    queryFn: api.listAccessoires,
  });

  if (isError) {
    return <Alert severity="error">Kon accessoires niet laden: {(error as Error).message}</Alert>;
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Accessoires-catalogus
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Beheren via <code>accessoire:create &lt;itemcode&gt; '&lt;label&gt;' &lt;delta-eur&gt;</code>,
        {' '}<code>accessoire:set-delta &lt;itemcode&gt; &lt;eur&gt;</code> of
        {' '}<code>accessoire:delete &lt;itemcode&gt;</code>.
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={400} />
      ) : (
        <DataGrid<Accessoire>
          rows={data ?? []}
          columns={columns}
          getRowId={(row) => row.itemcode}
          autoHeight
          disableRowSelectionOnClick
          initialState={{ pagination: { paginationModel: { pageSize: 25 } } }}
          pageSizeOptions={[25, 50, 100]}
        />
      )}
    </Stack>
  );
}
