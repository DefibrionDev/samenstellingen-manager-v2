import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, SuspiciousBaseRow } from '../api';

interface Row extends SuspiciousBaseRow {
  id: string;
  bomJoined: string;
  expectedAccessoireDisplay: string;
}

const columns: GridColDef<Row>[] = [
  { field: 'afasItemcode', headerName: 'SKU', width: 140 },
  { field: 'name', headerName: 'AFAS-naam', flex: 1.8, minWidth: 280 },
  { field: 'expectedAccessoireDisplay', headerName: 'Verwachte accessoire', flex: 1.3, minWidth: 220 },
  { field: 'bomJoined', headerName: 'BOM in AFAS', flex: 1.5, minWidth: 260 },
];

export function SuspiciousBases() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['suspicious-bases'],
    queryFn: api.listSuspiciousBases,
  });

  if (isError) {
    return <Alert severity="error">Kon verdachte bases niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, i) => ({
    ...row,
    id: `${row.afasItemcode}-${i}`,
    bomJoined: row.bom.join(', '),
    expectedAccessoireDisplay: `${row.expectedAccessoireItemcode} — ${row.expectedAccessoireLabel}`,
  }));

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Verdachte bases
      </Typography>
      <Typography variant="body2" color="text.secondary">
        AFAS-samenstellingen waarvan de SKU eindigt op een geregistreerde accessoire-itemcode,
        terwijl die accessoire niet in de BOM staat. Lijkt semantisch een variant maar zit
        geregistreerd als base. Fix in AFAS door de accessoire-itemcode aan de BOM toe te voegen.
        {rows.length > 0 && ` (${rows.length} rijen)`}
      </Typography>
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
