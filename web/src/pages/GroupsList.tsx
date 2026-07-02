import { useQuery } from '@tanstack/react-query';
import { Alert, Chip, Skeleton, Stack, Tooltip, Typography } from '@mui/material';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import { DataGrid, GridColDef, GridRowParams } from '@mui/x-data-grid';
import { useNavigate } from 'react-router-dom';
import { api, GroupSummary } from '../api';
import { ACTIE_META } from './NoMatchVariants';

const columns: GridColDef<GroupSummary>[] = [
  { field: 'name', headerName: 'Naam', flex: 2, minWidth: 240 },
  {
    field: 'familyHead',
    headerName: 'Family-head',
    flex: 1,
    minWidth: 180,
    renderCell: (params) => (
      <Stack direction="row" spacing={1} alignItems="center">
        <span>{params.value}</span>
        {!params.row.familyHeadIsBase && (
          <Tooltip title="Family-head-itemcode matcht geen base in deze groep — voeg de bijbehorende base toe of pas de family-head aan.">
            <WarningAmberIcon fontSize="small" color="warning" />
          </Tooltip>
        )}
      </Stack>
    ),
  },
  { field: 'baseCount', headerName: 'Bases', type: 'number', width: 100 },
  { field: 'baseItemCount', headerName: 'BOM-items', type: 'number', width: 120 },
  {
    field: 'noMatchCounts',
    headerName: 'Mis (no-match)',
    width: 170,
    sortable: false,
    renderCell: (params) => {
      const counts = (params.row.noMatchCounts ?? {}) as Record<string, number>;
      const entries = Object.entries(counts);
      if (entries.length === 0) {
        return <span style={{ color: 'rgba(0,0,0,0.4)' }}>0</span>;
      }
      return (
        <Stack direction="row" spacing={0.5}>
          {entries.map(([actie, n]) => {
            const meta = ACTIE_META[actie] ?? { label: actie, color: 'warning' as const };
            return (
              <Tooltip key={actie} title={`${n} × ${meta.label} — details op de No-match-pagina`}>
                <Chip label={n} size="small" color={meta.color} />
              </Tooltip>
            );
          })}
        </Stack>
      );
    },
  },
  {
    field: 'parentMismatchCount',
    headerName: 'Parent-drift',
    type: 'number',
    width: 130,
    renderCell: (params) => {
      const count = (params.value as number) ?? 0;
      if (count === 0) {
        return <span style={{ color: 'rgba(0,0,0,0.4)' }}>0</span>;
      }
      return (
        <Tooltip title={`${count} base(s) met afwijkende parent in AFAS`}>
          <Chip label={count} size="small" color="warning" variant="outlined" />
        </Tooltip>
      );
    },
  },
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
