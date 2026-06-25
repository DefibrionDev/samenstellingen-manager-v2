import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, BasePriceGapRow } from '../api';

interface Row extends BasePriceGapRow {
  id: number;
  prijslijstLabel: string;
}

const columns: GridColDef<Row>[] = [
  { field: 'prijslijstLabel', headerName: 'Prijslijst', width: 280 },
  { field: 'baseAfasItemcode', headerName: 'Base-itemcode', width: 180 },
  { field: 'groupName', headerName: 'Groep', width: 280 },
  { field: 'baseName', headerName: 'Base-naam', flex: 1, minWidth: 280 },
];

export function BasePriceGaps() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['base-price-gaps'],
    queryFn: api.listBasePriceGaps,
  });

  if (isError) {
    return <Alert severity="error">Kon base-prijs-gaten niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((row, index) => ({
    ...row,
    id: index,
    prijslijstLabel: row.prijslijstOmschrijving
      ? `${row.prijslijstId} — ${row.prijslijstOmschrijving}`
      : row.prijslijstId,
  }));

  return (
    <Stack spacing={2}>
      <Stack>
        <Typography variant="h5" component="h1">
          Base-prijs-gaten
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Managed base-samenstellingen die ontbreken in een whitelist-prijslijst — geen prijslijst-prijs aanwezig.
          Dit is het gat dat <code>price-drift</code> niet ziet (die vergelijkt alleen een variant tegen een base die
          al in een lijst staat). Read-only signaal: de prijs moet AFAS-zijdig aangemaakt worden. Gelezen uit de lokale
          snapshot (ververst bij <code>afas:pull</code>).
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
