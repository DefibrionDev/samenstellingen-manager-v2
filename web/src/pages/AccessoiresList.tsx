import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, Accessoire } from '../api';

const columns: GridColDef<Accessoire>[] = [
  { field: 'itemcode', headerName: 'Itemcode', width: 110 },
  { field: 'label', headerName: 'Label (intern)', flex: 1.4, minWidth: 240 },
  {
    field: 'deltaEur',
    headerName: 'Toeslag',
    width: 110,
    align: 'right',
    headerAlign: 'right',
    valueGetter: (_, row) => row.deltaEur ?? '€ 0,00',
  },
  {
    field: 'naamKortNl',
    headerName: 'Kort NL',
    flex: 1,
    minWidth: 160,
    valueGetter: (_, row) => row.naamKortNl ?? '—',
  },
  {
    field: 'naamKortFr',
    headerName: 'Kort FR',
    flex: 1,
    minWidth: 160,
    valueGetter: (_, row) => row.naamKortFr ?? '—',
  },
  {
    field: 'naamKortEn',
    headerName: 'Kort EN',
    flex: 1,
    minWidth: 160,
    valueGetter: (_, row) => row.naamKortEn ?? '—',
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
        Beheren via <code>accessoire:create</code>, <code>accessoire:set-delta</code>,
        {' '}<code>accessoire:set-naam-kort &lt;itemcode&gt; &lt;nl|fr|en&gt; '&lt;naam&gt;'</code> of
        {' '}<code>accessoire:delete</code>. <em>Label</em> is de interne beschrijving;
        de korte namen per taal worden in canonical variant-namen gebruikt.
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
