import { useQuery } from '@tanstack/react-query';
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Alert,
  Breadcrumbs,
  Chip,
  Link as MuiLink,
  Paper,
  Skeleton,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom';
import { api, Accessoire, GroupBase, GroupDetail as GroupDetailType, GroupVariantRow } from '../api';
import { ArticlePricesTable } from '../components/ArticlePricesTable';

type TabKey = 'bases' | 'accessoires' | 'variants';
const TAB_KEYS: TabKey[] = ['bases', 'accessoires', 'variants'];

function isTabKey(value: string | undefined): value is TabKey {
  return value !== undefined && (TAB_KEYS as string[]).includes(value);
}

export function GroupDetail() {
  const { familyHead, tab } = useParams<{ familyHead: string; tab?: string }>();
  const navigate = useNavigate();
  const activeTab: TabKey = isTabKey(tab) ? tab : 'bases';

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['group', familyHead],
    queryFn: () => api.showGroup(familyHead ?? ''),
    enabled: Boolean(familyHead),
  });

  if (isError) {
    const status = (error as Error).message.startsWith('404') ? 'Groep niet gevonden.' : (error as Error).message;
    return <Alert severity="error">{status}</Alert>;
  }

  return (
    <Stack spacing={3}>
      <Breadcrumbs>
        <MuiLink component={RouterLink} to="/" underline="hover" color="inherit">
          Groepen
        </MuiLink>
        <Typography color="text.primary">{data?.name ?? familyHead}</Typography>
      </Breadcrumbs>

      {isLoading || !data ? (
        <Skeleton variant="rectangular" height={400} />
      ) : (
        <>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h5" component="h1">
              {data.name}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              family-head <code>{data.familyHead}</code> · {data.bases.length} bases
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
              <strong>Model NL:</strong> {data.modelNameNl ?? '—'}
              {' · '}
              <strong>FR:</strong> {data.modelNameFr ?? '—'}
              {' · '}
              <strong>EN:</strong> {data.modelNameEn ?? '—'}
            </Typography>
          </Paper>

          <Paper>
            <Tabs
              value={activeTab}
              onChange={(_, value: TabKey) =>
                navigate(
                  value === 'bases'
                    ? `/groups/${encodeURIComponent(data.familyHead)}`
                    : `/groups/${encodeURIComponent(data.familyHead)}/${value}`,
                )
              }
            >
              <Tab value="bases" label="Bases" />
              <Tab value="accessoires" label="Accessoires" />
              <Tab value="variants" label="Varianten" />
            </Tabs>
          </Paper>

          {activeTab === 'bases' && <BasesTab bases={data.bases} />}
          {activeTab === 'accessoires' && <AccessoiresTab familyHead={data.familyHead} />}
          {activeTab === 'variants' && <VariantsTab familyHead={data.familyHead} />}
        </>
      )}
    </Stack>
  );
}

function BasesTab({ bases }: { bases: GroupDetailType['bases'] }) {
  return (
    <Stack spacing={1}>
      {bases.map((base: GroupBase) => (
        <Accordion key={base.id} disableGutters>
          <AccordionSummary expandIcon={<ExpandMoreIcon />}>
            <Stack direction="row" spacing={2} alignItems="center" sx={{ width: '100%' }}>
              {base.afasItemcode && (
                <Typography
                  component="code"
                  sx={{ fontFamily: 'monospace', color: 'text.secondary', minWidth: '6ch' }}
                >
                  {base.afasItemcode}
                </Typography>
              )}
              <Typography sx={{ flexGrow: 1 }}>{base.name}</Typography>
              {base.variantLabel && (
                <Chip label={base.variantLabel} size="small" variant="outlined" />
              )}
              <Chip label={base.languageCode} size="small" />
              <Typography variant="caption" color="text.secondary">
                {base.items.length} items
              </Typography>
            </Stack>
          </AccordionSummary>
          <AccordionDetails sx={{ p: 0 }}>
            <Stack spacing={2} sx={{ p: 2 }}>
              <Stack>
                <Typography variant="overline" color="text.secondary">
                  BOM-items
                </Typography>
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Itemcode</TableCell>
                        <TableCell>Label</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {base.items.map((item) => (
                        <TableRow key={item.itemcode}>
                          <TableCell>
                            <code>{item.itemcode}</code>
                          </TableCell>
                          <TableCell>{item.label}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </Stack>
              <Stack>
                <Typography variant="overline" color="text.secondary">
                  Actieve prijzen ({base.afasItemcode ?? 'geen SKU'})
                </Typography>
                <ArticlePricesTable itemcode={base.afasItemcode} />
              </Stack>
            </Stack>
          </AccordionDetails>
        </Accordion>
      ))}
    </Stack>
  );
}

function AccessoiresTab({ familyHead }: { familyHead: string }) {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['group-accessoires', familyHead],
    queryFn: () => api.listGroupAccessoires(familyHead),
  });

  if (isError) {
    return <Alert severity="error">Kon accessoires niet laden: {(error as Error).message}</Alert>;
  }
  if (isLoading || !data) {
    return <Skeleton variant="rectangular" height={200} />;
  }
  if (data.length === 0) {
    return <Alert severity="info">Geen accessoires aan deze groep gekoppeld.</Alert>;
  }

  return (
    <Paper>
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Itemcode</TableCell>
              <TableCell>Label</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {data.map((row: Accessoire) => (
              <TableRow key={row.itemcode}>
                <TableCell>
                  <code>{row.itemcode}</code>
                </TableCell>
                <TableCell>{row.label}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

const STATUS_COLOR: Record<string, 'success' | 'warning' | 'default' | 'error'> = {
  matched: 'success',
  no_match: 'warning',
  no_local: 'default',
};

const variantColumns: GridColDef<GroupVariantRow & { id: string }>[] = [
  { field: 'baseName', headerName: 'Base', flex: 2, minWidth: 260 },
  { field: 'languageCode', headerName: 'Taal', width: 90 },
  {
    field: 'accessoire',
    headerName: 'Accessoire',
    flex: 1.4,
    minWidth: 200,
    valueGetter: (_, row) =>
      row.accessoireItemcode ? `${row.accessoireItemcode} — ${row.accessoireLabel ?? ''}` : '—',
  },
  { field: 'afasSamenstellingItemcode', headerName: 'AFAS-SKU', width: 130 },
  {
    field: 'afasStatus',
    headerName: 'Status',
    width: 130,
    renderCell: (params) =>
      params.value ? (
        <Chip
          label={params.value}
          size="small"
          color={STATUS_COLOR[params.value as string] ?? 'default'}
          variant={params.value === 'matched' ? 'filled' : 'outlined'}
        />
      ) : (
        '—'
      ),
  },
];

function VariantsTab({ familyHead }: { familyHead: string }) {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['group-variants', familyHead],
    queryFn: () => api.listGroupVariants(familyHead),
  });

  if (isError) {
    return <Alert severity="error">Kon varianten niet laden: {(error as Error).message}</Alert>;
  }
  if (isLoading || !data) {
    return <Skeleton variant="rectangular" height={300} />;
  }

  const rows = data.map((row, i) => ({ ...row, id: `${row.baseId}-${row.accessoireItemcode ?? 'base'}-${i}` }));

  return (
    <DataGrid<GroupVariantRow & { id: string }>
      rows={rows}
      columns={variantColumns}
      autoHeight
      disableRowSelectionOnClick
      initialState={{ pagination: { paginationModel: { pageSize: 25 } } }}
      pageSizeOptions={[25, 50, 100]}
    />
  );
}
