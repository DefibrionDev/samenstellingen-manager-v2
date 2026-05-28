import { useQuery } from '@tanstack/react-query';
import { Alert, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, StickerDriftRow } from '../api';

interface Row extends StickerDriftRow {
  id: string;
}

const columns: GridColDef<Row>[] = [
  { field: 'baseAfasItemcode', headerName: 'Itemcode', width: 130 },
  { field: 'languageCode', headerName: 'Taal', width: 110 },
  { field: 'expectedSticker', headerName: 'Verwacht', width: 110 },
  {
    field: 'actualStickers',
    headerName: 'Werkelijk',
    width: 160,
    valueGetter: (_, row) => (row.actualStickers.length === 0 ? '(geen)' : row.actualStickers.join(', ')),
  },
  { field: 'groupName', headerName: 'Groep', flex: 1, minWidth: 220 },
  { field: 'baseName', headerName: 'Base-naam', flex: 1.5, minWidth: 280 },
];

export function StickerDrift() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['sticker-drift'],
    queryFn: api.listStickerDrift,
  });

  if (isError) {
    return <Alert severity="error">Kon sticker-drift niet laden: {(error as Error).message}</Alert>;
  }

  const rows: Row[] = (data ?? []).map((r, i) => ({ ...r, id: `${r.baseAfasItemcode}-${i}` }));

  return (
    <Stack spacing={2}>
      <Typography variant="h5" component="h1">
        Sticker-drift
      </Typography>
      <Typography variant="body2" color="text.secondary">
        Bases waar de stickerset (81xxx) in de AFAS-BOM niet matcht met de taal-code. Mapping:
        NL → 81111, FR → 81211, DK → 81411, DE → 81511, anders (EN/UK/WAL) → 81611 (internationaal).
        Bij compound talen (NL/FR/EN) telt het eerste taal-token.
      </Typography>
      {isLoading ? (
        <Skeleton variant="rectangular" height={300} />
      ) : rows.length === 0 ? (
        <Alert severity="success">Geen sticker-drift — alle bases hebben de juiste stickerset voor hun taal.</Alert>
      ) : (
        <DataGrid<Row> rows={rows} columns={columns} autoHeight disableRowSelectionOnClick />
      )}
    </Stack>
  );
}
