import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Button,
  Chip,
  IconButton,
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
import DownloadIcon from '@mui/icons-material/Download';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import { api, PriceDriftRow } from '../api';

interface DriftGroup {
  key: string;
  variantSku: string;
  accessoireItemcode: string;
  accessoireLabel: string;
  groupName: string;
  baseSku: string;
  rows: PriceDriftRow[];
  driftCount: number;
  missingCount: number;
  inconsistentCount: number;
  summary: string;
  totalAffected: number;
}

function formatCents(cents: number): string {
  const negative = cents < 0;
  const abs = Math.abs(cents);
  const euros = Math.floor(abs / 100);
  const remainder = abs % 100;
  const eurosFormatted = euros.toLocaleString('nl-NL');
  const sign = negative ? '-' : '';
  return `${sign}€ ${eurosFormatted},${String(remainder).padStart(2, '0')}`;
}

function formatSigned(cents: number): string {
  if (cents === 0) return '€ 0,00';
  const formatted = formatCents(Math.abs(cents));
  return cents > 0 ? `+${formatted}` : `-${formatted}`;
}

function summarizeGroup(rows: PriceDriftRow[]): string {
  const drifts = rows.filter((r) => r.status === 'toeslag-drift');
  const missings = rows.filter((r) => r.status === 'missing');
  const inconsistents = rows.filter((r) => r.status === 'inconsistent-staffel');

  if (drifts.length > 0 && missings.length === 0 && inconsistents.length === 0) {
    const uniqueActual = new Set(drifts.map((d) => d.actualDeltaCents));
    const expected = drifts[0].expectedDeltaCents;
    if (uniqueActual.size === 1 && drifts[0].actualDeltaCents !== null) {
      return `Toeslag staat overal op ${formatCents(drifts[0].actualDeltaCents)} i.p.v. ${formatCents(expected)}`;
    }
    return `Toeslag wijkt af in ${drifts.length} (lijst, staffel)-combinaties`;
  }
  if (missings.length > 0 && drifts.length === 0 && inconsistents.length === 0) {
    return `Variant ontbreekt in ${missings.length} (lijst, staffel)-combinatie${missings.length === 1 ? '' : 's'}`;
  }
  if (inconsistents.length > 0 && drifts.length === 0 && missings.length === 0) {
    return `Variant heeft ${inconsistents.length} staffel-rij(en) die base mist`;
  }
  const parts: string[] = [];
  if (drifts.length > 0) parts.push(`${drifts.length} drift`);
  if (missings.length > 0) parts.push(`${missings.length} missing`);
  if (inconsistents.length > 0) parts.push(`${inconsistents.length} inconsistent`);
  return parts.join(', ');
}

function groupRows(rows: PriceDriftRow[]): DriftGroup[] {
  const groups = new Map<string, DriftGroup>();
  for (const row of rows) {
    const key = `${row.variantAfasItemcode}|${row.accessoireItemcode}`;
    let group = groups.get(key);
    if (!group) {
      group = {
        key,
        variantSku: row.variantAfasItemcode,
        accessoireItemcode: row.accessoireItemcode,
        accessoireLabel: row.accessoireLabel,
        groupName: row.groupName,
        baseSku: row.baseAfasItemcode,
        rows: [],
        driftCount: 0,
        missingCount: 0,
        inconsistentCount: 0,
        summary: '',
        totalAffected: 0,
      };
      groups.set(key, group);
    }
    group.rows.push(row);
    if (row.status === 'toeslag-drift') group.driftCount++;
    else if (row.status === 'missing') group.missingCount++;
    else group.inconsistentCount++;
  }

  const result = [...groups.values()];
  for (const g of result) {
    g.summary = summarizeGroup(g.rows);
    g.totalAffected = g.rows.length;
  }
  result.sort((a, b) => b.totalAffected - a.totalAffected || a.variantSku.localeCompare(b.variantSku));
  return result;
}

function toCsv(rows: PriceDriftRow[]): string {
  const headers = [
    'status', 'groep', 'baseSku', 'variantSku', 'accessoire', 'prijslijstId', 'prijslijstOmschrijving',
    'staffelAantal', 'basePrijsCents', 'variantPrijsCents', 'expectedDeltaCents', 'actualDeltaCents',
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
        row.staffelAantal !== null ? String(row.staffelAantal) : '',
        row.basePrijsCents !== null ? String(row.basePrijsCents) : '',
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

function prijslijstLabel(row: PriceDriftRow): string {
  const base = row.prijslijstOmschrijving !== null
    ? `${row.prijslijstId} — ${row.prijslijstOmschrijving}`
    : row.prijslijstId;
  const staffel = row.staffelAantal !== null ? ` · staffel ${row.staffelAantal}` : '';
  return base + staffel;
}

function rowDetail(row: PriceDriftRow): { label: string; delta: string | null; color: 'warning' | 'default' } {
  if (row.status === 'inconsistent-staffel') {
    const variantEur = row.variantPrijsEur ?? '—';
    return {
      label: `Variant heeft prijs ${variantEur} op staffel ${row.staffelAantal}, maar base heeft die staffel niet. Auto-fix onveilig.`,
      delta: null,
      color: 'default',
    };
  }
  const baseEur = row.basePrijsEur ?? '—';
  const expectedTotal = row.basePrijsCents !== null
    ? formatCents(row.basePrijsCents + row.expectedDeltaCents)
    : '—';
  if (row.status === 'missing') {
    return {
      label: `Basis ${baseEur} + accessoire ${row.expectedDeltaEur} = ${expectedTotal}. Ontbreekt in AFAS.`,
      delta: null,
      color: 'default',
    };
  }
  const variantEur = row.variantPrijsEur ?? '—';
  return {
    label: `Basis ${baseEur} + accessoire ${row.expectedDeltaEur} = ${expectedTotal}. Waarde in AFAS is ${variantEur}.`,
    delta: row.actualDeltaCents !== null ? formatSigned(row.actualDeltaCents - row.expectedDeltaCents) : null,
    color: 'warning',
  };
}

type StatusTab = 'toeslag-drift' | 'missing' | 'inconsistent-staffel';

export function PriceDrift() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['price-drift'],
    queryFn: api.listPriceDrift,
  });
  const [tab, setTab] = useState<StatusTab>('toeslag-drift');
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  const totalDrift = (data ?? []).filter((r) => r.status === 'toeslag-drift').length;
  const totalMissing = (data ?? []).filter((r) => r.status === 'missing').length;
  const totalInconsistent = (data ?? []).filter((r) => r.status === 'inconsistent-staffel').length;

  const filtered = useMemo(() => (data ?? []).filter((r) => r.status === tab), [data, tab]);
  const groups = useMemo(() => groupRows(filtered), [filtered]);

  if (isError) {
    return <Alert severity="error">Kon prijs-drift niet laden: {(error as Error).message}</Alert>;
  }

  const toggle = (key: string) =>
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });

  const allExpanded = groups.length > 0 && groups.every((g) => expanded.has(g.key));
  const toggleAll = () =>
    setExpanded(allExpanded ? new Set() : new Set(groups.map((g) => g.key)));

  return (
    <Stack spacing={2}>
      <Box display="flex" justifyContent="space-between" alignItems="center">
        <Stack>
          <Typography variant="h5" component="h1">
            Prijs-drift
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Variant-prijzen waarvan de toeslag afwijkt van <code>accessoires.delta_eur</code> (drift), of die
            ontbreken in een prijslijst waar de base wel in staat (missing).
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Button variant="text" size="small" disabled={groups.length === 0} onClick={toggleAll}>
            {allExpanded ? 'Alles inklappen' : 'Alles uitklappen'}
          </Button>
          <Button
            variant="outlined"
            startIcon={<DownloadIcon />}
            disabled={(data ?? []).length === 0}
            onClick={() => downloadCsv(data ?? [])}
          >
            Exporteer CSV
          </Button>
        </Stack>
      </Box>
      <Tabs value={tab} onChange={(_, v: StatusTab) => setTab(v)}>
        <Tab value="toeslag-drift" label={`Toeslag-drift (${totalDrift})`} />
        <Tab value="missing" label={`Missing (${totalMissing})`} />
        <Tab value="inconsistent-staffel" label={`Inconsistent staffel (${totalInconsistent})`} />
      </Tabs>
      {isLoading ? (
        <Skeleton variant="rectangular" height={400} />
      ) : groups.length === 0 ? (
        <Alert severity="success">
          {tab === 'toeslag-drift'
            ? 'Geen toeslag-drift gevonden — alle varianten kloppen met hun base + toeslag.'
            : tab === 'missing'
              ? 'Geen missing varianten — alle varianten staan in dezelfde prijslijsten als hun base.'
              : 'Geen inconsistente staffels — variant-staffels matchen met base-staffels.'}
        </Alert>
      ) : (
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ width: 48 }} />
                <TableCell sx={{ width: 150 }}>Variant</TableCell>
                <TableCell sx={{ width: 120 }}>Accessoire</TableCell>
                <TableCell>Probleem</TableCell>
                <TableCell sx={{ width: 200 }}>Groep</TableCell>
                <TableCell sx={{ width: 140 }} align="right">Lijsten</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {groups.map((group) => {
                const isOpen = expanded.has(group.key);
                return (
                  <DriftGroupRow
                    key={group.key}
                    group={group}
                    isOpen={isOpen}
                    onToggle={() => toggle(group.key)}
                  />
                );
              })}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </Stack>
  );
}

function DriftGroupRow({
  group,
  isOpen,
  onToggle,
}: {
  group: DriftGroup;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <>
      <TableRow hover sx={{ cursor: 'pointer', '& > *': { borderBottom: 'unset' } }} onClick={onToggle}>
        <TableCell>
          <IconButton size="small" aria-label={isOpen ? 'inklappen' : 'uitklappen'}>
            {isOpen ? <KeyboardArrowDownIcon /> : <KeyboardArrowRightIcon />}
          </IconButton>
        </TableCell>
        <TableCell sx={{ fontFamily: 'monospace' }}>{group.variantSku}</TableCell>
        <TableCell>
          {group.accessoireItemcode}
          <Typography variant="caption" color="text.secondary" sx={{ ml: 1 }}>
            {group.accessoireLabel}
          </Typography>
        </TableCell>
        <TableCell>
          <Typography variant="body2">{group.summary}</Typography>
        </TableCell>
        <TableCell>
          <Typography variant="caption" color="text.secondary">
            {group.groupName}
          </Typography>
        </TableCell>
        <TableCell align="right">
          {group.driftCount > 0 && (
            <Chip label={`${group.driftCount} drift`} size="small" color="warning" variant="outlined" sx={{ mr: 0.5 }} />
          )}
          {group.missingCount > 0 && (
            <Chip label={`${group.missingCount} missing`} size="small" variant="outlined" sx={{ mr: 0.5 }} />
          )}
          {group.inconsistentCount > 0 && (
            <Chip label={`${group.inconsistentCount} inconsistent`} size="small" color="error" variant="outlined" />
          )}
        </TableCell>
      </TableRow>
      {isOpen &&
        group.rows.map((row, i) => {
          const detail = rowDetail(row);
          return (
            <TableRow key={`${group.key}-${row.prijslijstId}-${i}`} sx={{ backgroundColor: 'action.hover' }}>
              <TableCell />
              <TableCell colSpan={2}>
                <Typography variant="caption" sx={{ fontFamily: 'monospace' }}>
                  {prijslijstLabel(row)}
                </Typography>
              </TableCell>
              <TableCell>
                <Typography variant="body2">{detail.label}</Typography>
              </TableCell>
              <TableCell />
              <TableCell align="right">
                {detail.delta !== null ? (
                  <Chip
                    label={detail.delta}
                    size="small"
                    color={detail.color}
                    sx={{ fontFamily: 'monospace', fontWeight: 600 }}
                  />
                ) : (
                  <Chip label="missing" size="small" variant="outlined" />
                )}
              </TableCell>
            </TableRow>
          );
        })}
    </>
  );
}
