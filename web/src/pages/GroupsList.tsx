import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef, GridRowParams } from '@mui/x-data-grid';
import { useNavigate } from 'react-router-dom';
import { api, GroupSummary } from '../api';

const columns: GridColDef<GroupSummary>[] = [
  { field: 'name', headerName: 'Naam', flex: 2, minWidth: 240 },
  { field: 'familyHead', headerName: 'Family-head', flex: 1, minWidth: 140 },
  { field: 'baseCount', headerName: 'Bases', type: 'number', width: 100 },
  { field: 'baseItemCount', headerName: 'BOM-items', type: 'number', width: 120 },
];

export function GroupsList() {
  const navigate = useNavigate();
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['groups'],
    queryFn: api.listGroups,
  });

  if (isError) {
    return <Alert severity="error">Kon groepen niet laden: {(error as Error).message}</Alert>;
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Groepen
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={400} />
      ) : (
        <DataGrid<GroupSummary>
          rows={data ?? []}
          columns={columns}
          getRowId={(row) => row.familyHead}
          onRowClick={(params: GridRowParams<GroupSummary>) =>
            navigate(`/groups/${encodeURIComponent(params.row.familyHead)}`)
          }
          autoHeight
          disableRowSelectionOnClick
          initialState={{ pagination: { paginationModel: { pageSize: 25 } } }}
          pageSizeOptions={[25, 50, 100]}
          sx={{ '& .MuiDataGrid-row': { cursor: 'pointer' } }}
        />
      )}
    </Stack>
  );
}
