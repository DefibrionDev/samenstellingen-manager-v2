import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, NoMatchVariantRow } from '../api';

interface Row extends NoMatchVariantRow {
  id: number;
}

const dash = (value: string | null) => (value && value !== '' ? value : '—');
const joinOrDash = (values: string[]) => (values.length > 0 ? values.join(', ') : '—');

const columns: GridColDef<Row>[] = [
  { field: 'groupName', headerName: 'Groep', flex: 1.3, minWidth: 190 },
  { field: 'baseName', headerName: 'Base', flex: 1.8, minWidth: 240 },
  { field: 'accessoireItemcode', headerName: 'Accessoire', width: 110 },
  {
    field: 'expectedBom',
    headerName: 'Verwachte BOM',
    flex: 1.6,
    minWidth: 220,
    sortable: false,
    renderCell: (params) => params.row.expectedBom.join(', '),
  },
  {
    field: 'bestaandeAfasItemcode',
    headerName: 'Bestaat in AFAS',
    width: 160,
    renderCell: (params) => dash(params.row.bestaandeAfasItemcode),
  },
  {
    field: 'ontbrekendeItemcodes',
    headerName: 'Mist',
    flex: 1,
    minWidth: 130,
    sortable: false,
    renderCell: (params) => joinOrDash(params.row.ontbrekendeItemcodes),
  },
  {
    field: 'extraItemcodes',
    headerName: 'Teveel',
    flex: 1,
    minWidth: 130,
    sortable: false,
    renderCell: (params) => joinOrDash(params.row.extraItemcodes),
  },
];

export function NoMatchVariants() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['no-match-variants'],
    queryFn: api.listNoMatchVariants,
  });

  if (isError) {
    return <Alert severity="error">Kon no-match-varianten niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, index) => ({ ...row, id: index }));

  return (
    <Stack spacing={2}>
      <Stack>
        <Typography variant="h5" component="h1">
          No-match varianten
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Variant-rijen met status <code>no_match</code> — de matcher vond geen AFAS-compositie met de verwachte BOM.
          <strong> Bestaat in AFAS</strong> toont of de compositie er tóch is (alleen niet matchte);{' '}
          <strong>Mist</strong>/<strong>Teveel</strong> zijn de itemcodes die in die compositie ontbreken of teveel staan.
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
