import { useQuery } from '@tanstack/react-query';
import { Alert, Chip, Skeleton, Stack, Typography } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { api, NoMatchVariantRow } from '../api';

/** Weergave + kleur per actie-categorie. */
export const ACTIE_META: Record<string, { label: string; color: 'success' | 'warning' | 'info' | 'error' }> = {
  aanmaakbaar: { label: 'Aanmaakbaar', color: 'success' },
  bestaat_al_afwijkende_bom: { label: 'Bestaat al (BOM wijkt af)', color: 'warning' },
  bom_bestaat_elders: { label: 'BOM bestaat elders', color: 'info' },
  base_niet_gematcht: { label: 'Base niet gematcht', color: 'error' },
};

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
  {
    field: 'actie',
    headerName: 'Actie',
    width: 200,
    renderCell: (params) => {
      const meta = ACTIE_META[params.row.actie] ?? { label: params.row.actie, color: 'warning' as const };
      return <Chip size="small" color={meta.color} variant="outlined" label={meta.label} />;
    },
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

  const rows: Row[] = (data?.rows ?? []).map((row, index) => ({ ...row, id: index }));
  const counts = data?.counts ?? {};

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
          De kolom <strong>Actie</strong> zegt wat er moet gebeuren; alleen <em>aanmaakbaar</em> wordt door{' '}
          <code>variants:fix-missing</code> aangemaakt. Export: <code>audit:no-match --csv=&lt;pad&gt;</code>.
          {rows.length > 0 && ` (${rows.length} rijen)`}
        </Typography>
        {Object.keys(counts).length > 0 && (
          <Stack direction="row" spacing={1} sx={{ mt: 1 }}>
            {Object.entries(counts).map(([actie, n]) => {
              const meta = ACTIE_META[actie] ?? { label: actie, color: 'warning' as const };
              return <Chip key={actie} size="small" color={meta.color} label={`${n} × ${meta.label}`} />;
            })}
          </Stack>
        )}
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
