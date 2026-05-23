import { useQuery } from '@tanstack/react-query';
import { Alert, Box, Button, Skeleton, Stack, Typography } from '@mui/material';
import DownloadIcon from '@mui/icons-material/Download';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, NameDriftRow } from '../api';

interface Row extends NameDriftRow {
  id: string;
}

const columns: GridColDef<Row>[] = [
  { field: 'afasItemcode', headerName: 'SKU', width: 130 },
  { field: 'languageCode', headerName: 'Taal', width: 90 },
  { field: 'groupName', headerName: 'Groep', flex: 1.1, minWidth: 200 },
  {
    field: 'accessoire',
    headerName: 'Accessoire',
    flex: 1.2,
    minWidth: 200,
    valueGetter: (_, row) =>
      row.accessoireItemcode ? `${row.accessoireItemcode} — ${row.accessoireLabel ?? ''}` : '—',
  },
  { field: 'expected', headerName: 'Verwacht', flex: 2.2, minWidth: 320 },
  { field: 'actual', headerName: 'Werkelijk in AFAS', flex: 2.2, minWidth: 320 },
];

function toCsv(rows: NameDriftRow[]): string {
  const headers = ['afasItemcode', 'groupName', 'languageCode', 'accessoireItemcode', 'expected', 'actual'];
  const escape = (v: string) => (/[",\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v);
  const lines = [headers.join(',')];
  for (const row of rows) {
    lines.push(
      [
        row.afasItemcode,
        row.groupName,
        row.languageCode,
        row.accessoireItemcode ?? '',
        row.expected,
        row.actual,
      ]
        .map(escape)
        .join(','),
    );
  }
  return lines.join('\n');
}

function downloadCsv(rows: NameDriftRow[]) {
  const blob = new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `name-drift-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export function NameDrift() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['name-drift'],
    queryFn: api.listNameDrift,
  });

  if (isError) {
    return <Alert severity="error">Kon naam-drift niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, i) => ({ ...row, id: `${row.afasItemcode}-${i}` }));

  return (
    <Stack spacing={2}>
      <Box display="flex" justifyContent="space-between" alignItems="center">
        <Stack>
          <Typography variant="h5" component="h1">
            Naam-drift in AFAS
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Gematchte AFAS-samenstellingen waarvan de naam afwijkt van de canonieke template
            (PLAN.md §9.1).
            {rows.length > 0 && ` (${rows.length} rijen)`}
          </Typography>
        </Stack>
        <Button
          variant="outlined"
          startIcon={<DownloadIcon />}
          disabled={rows.length === 0}
          onClick={() => downloadCsv(data ?? [])}
        >
          Exporteer CSV
        </Button>
      </Box>
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
