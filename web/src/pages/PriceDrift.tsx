import { useQuery } from '@tanstack/react-query';
import { Alert, Box, Button, Chip, Skeleton, Stack, Typography } from '@mui/material';
import DownloadIcon from '@mui/icons-material/Download';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, PriceDriftRow } from '../api';

interface Row extends PriceDriftRow {
  id: string;
}

const STATUS_COLOR: Record<string, 'warning' | 'default' | 'error'> = {
  'toeslag-drift': 'warning',
  missing: 'default',
};

const columns: GridColDef<Row>[] = [
  { field: 'variantAfasItemcode', headerName: 'Variant', width: 150 },
  { field: 'accessoireItemcode', headerName: 'Acc.', width: 90 },
  {
    field: 'prijslijstId',
    headerName: 'Prijslijst',
    width: 220,
    valueGetter: (_, row) =>
      row.prijslijstOmschrijving !== null
        ? `${row.prijslijstId} — ${row.prijslijstOmschrijving}`
        : row.prijslijstId,
  },
  {
    field: 'status',
    headerName: 'Status',
    width: 140,
    renderCell: (params) => (
      <Chip
        label={params.value}
        size="small"
        color={STATUS_COLOR[params.value as string] ?? 'default'}
        variant="outlined"
      />
    ),
  },
  { field: 'groupName', headerName: 'Groep', flex: 1.2, minWidth: 180 },
  { field: 'basePrijsEur', headerName: 'Base', width: 110, align: 'right', headerAlign: 'right' },
  {
    field: 'variantPrijsEur',
    headerName: 'Variant',
    width: 110,
    align: 'right',
    headerAlign: 'right',
    valueGetter: (_, row) => row.variantPrijsEur ?? '—',
  },
  { field: 'expectedDeltaEur', headerName: 'Verwacht', width: 110, align: 'right', headerAlign: 'right' },
  {
    field: 'actualDeltaEur',
    headerName: 'Werkelijk',
    width: 110,
    align: 'right',
    headerAlign: 'right',
    valueGetter: (_, row) => row.actualDeltaEur ?? '—',
  },
];

function toCsv(rows: PriceDriftRow[]): string {
  const headers = [
    'status', 'groep', 'baseSku', 'variantSku', 'accessoire', 'prijslijstId', 'prijslijstOmschrijving',
    'basePrijsCents', 'variantPrijsCents', 'expectedDeltaCents', 'actualDeltaCents',
  ];
  const escape = (v: string) => (/[",\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v);
  const lines = [headers.join(',')];
  for (const row of rows) {
    lines.push(
      [
        row.status,
        row.groupName,
        row.baseAfasItemcode,
        row.variantAfasItemcode,
        row.accessoireItemcode,
        row.prijslijstId,
        row.prijslijstOmschrijving ?? '',
        String(row.basePrijsCents),
        row.variantPrijsCents !== null ? String(row.variantPrijsCents) : '',
        String(row.expectedDeltaCents),
        row.actualDeltaCents !== null ? String(row.actualDeltaCents) : '',
      ]
        .map(escape)
        .join(','),
    );
  }
  return lines.join('\n');
}

function downloadCsv(rows: PriceDriftRow[]) {
  const blob = new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `price-drift-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export function PriceDrift() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['price-drift'],
    queryFn: api.listPriceDrift,
  });

  if (isError) {
    return <Alert severity="error">Kon prijs-drift niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, i) => ({ ...row, id: `${row.variantAfasItemcode}-${row.prijslijstId}-${i}` }));
  const driftCount = rows.filter((r) => r.status === 'toeslag-drift').length;
  const missingCount = rows.filter((r) => r.status === 'missing').length;

  return (
    <Stack spacing={2}>
      <Box display="flex" justifyContent="space-between" alignItems="center">
        <Stack>
          <Typography variant="h5" component="h1">
            Prijs-drift
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Variant-prijzen waarvan de toeslag afwijkt van <code>accessoires.delta_eur</code>, of die ontbreken
            in een prijslijst waar de base wel in staat.
            {rows.length > 0 && ` (${driftCount} toeslag-drift, ${missingCount} missing)`}
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
