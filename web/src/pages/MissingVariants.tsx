import { useQuery } from '@tanstack/react-query';
import { Alert, Box, Button, Skeleton, Stack, Typography } from '@mui/material';
import DownloadIcon from '@mui/icons-material/Download';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, MissingVariantRow } from '../api';

interface Row extends MissingVariantRow {
  id: number;
}

const columns: GridColDef<Row>[] = [
  { field: 'groupName', headerName: 'Groep', flex: 1.4, minWidth: 200 },
  { field: 'baseName', headerName: 'Base', flex: 2, minWidth: 260 },
  { field: 'baseAfasSku', headerName: 'Base SKU', width: 110 },
  { field: 'accessoireItemcode', headerName: 'Accessoire', width: 110 },
  { field: 'accessoireLabel', headerName: 'Accessoire-naam', flex: 1.4, minWidth: 220 },
  { field: 'suggestedSku', headerName: 'Voorgestelde SKU', width: 170 },
];

function toCsv(rows: MissingVariantRow[]): string {
  const headers = ['groep', 'base', 'baseAfasSku', 'accessoireItemcode', 'accessoireLabel', 'expectedBom', 'suggestedSku'];
  const escape = (value: string) =>
    /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
  const lines = [headers.join(',')];
  for (const row of rows) {
    lines.push(
      [
        row.groupName,
        row.baseName,
        row.baseAfasSku,
        row.accessoireItemcode,
        row.accessoireLabel,
        row.expectedBom.join(' '),
        row.suggestedSku,
      ]
        .map(escape)
        .join(','),
    );
  }
  return lines.join('\n');
}

function downloadCsv(rows: MissingVariantRow[]) {
  const blob = new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `missing-variants-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export function MissingVariants() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['missing-variants'],
    queryFn: api.listMissingVariants,
  });

  if (isError) {
    return <Alert severity="error">Kon missing-variants niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, index) => ({ ...row, id: index }));

  return (
    <Stack spacing={2}>
      <Box display="flex" justifyContent="space-between" alignItems="center">
        <Stack>
          <Typography variant="h5" component="h1">
            Missing AFAS-samenstellingen
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Variant-rijen met status <code>no_match</code> — actie-lijst voor het AFAS-team.
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
