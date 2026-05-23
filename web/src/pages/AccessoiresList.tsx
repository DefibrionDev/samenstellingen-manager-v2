import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, Accessoire } from '../api';

const columns: GridColDef<Accessoire>[] = [
  { field: 'itemcode', headerName: 'Itemcode', width: 140 },
  { field: 'label', headerName: 'Label', flex: 1, minWidth: 320 },
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
