import { useQuery } from '@tanstack/react-query';
import { Alert, Chip, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, ProductTypeIssueRow, ProductTypeIssueType } from '../api';

interface Row extends ProductTypeIssueRow {
  id: string;
}

const issueColor: Record<ProductTypeIssueType, 'error' | 'warning' | 'default'> = {
  'base-leeg': 'error',
  'variant-geblokkeerd': 'warning',
  'variant-fixbaar': 'default',
};

const pair = (a: string | null, b: string | null) => `${a ?? '(leeg)'} / ${b ?? '(leeg)'}`;

const columns: GridColDef<Row>[] = [
  { field: 'afasItemcode', headerName: 'Itemcode', width: 150 },
  {
    field: 'issueType',
    headerName: 'Issue',
    width: 180,
    renderCell: (params) => <Chip size="small" label={params.value} color={issueColor[params.value as ProductTypeIssueType]} />,
  },
  { field: 'baseItemcode', headerName: 'Base', width: 120 },
  {
    field: 'current',
    headerName: 'Huidig 01/02',
    width: 180,
    valueGetter: (_, row) => pair(row.current01, row.current02),
  },
  {
    field: 'expected',
    headerName: 'Verwacht 01/02',
    width: 180,
    valueGetter: (_, row) => pair(row.expected01, row.expected02),
  },
  { field: 'groupName', headerName: 'Groep', width: 220 },
  { field: 'cliHint', headerName: 'Actie (CLI)', flex: 1, minWidth: 320 },
];

export function ProductTypeIssues() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['product-type-issues'],
    queryFn: api.listProductTypeIssues,
  });

  if (isError) {
    return <Alert severity="error">Kon producttype-issues niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((r) => ({ ...r, id: r.afasItemcode }));

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Producttype-issues
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Samenstellingen waar webshop-producttype 01/02 (bv. &laquo;AED pakket&raquo; / &laquo;350P&raquo;) ontbreekt of
        afwijkt. De base-samenstelling is leidend; varianten horen die exact over te nemen. Deze weergave is read-only —
        fixes lopen via de CLI: <code>base-leeg</code> vul je handmatig in AFAS, varianten trek je gelijk met{' '}
        <code>producttype:fix-variants --apply</code>.
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={300} />
      ) : rows.length === 0 ? (
        <Alert severity="success">
          Geen producttype-issues — elke base heeft 01/02 gevuld en varianten komen overeen.
        </Alert>
      ) : (
        <DataGrid<Row> rows={rows} columns={columns} autoHeight disableRowSelectionOnClick />
      )}
    </Stack>
  );
}
