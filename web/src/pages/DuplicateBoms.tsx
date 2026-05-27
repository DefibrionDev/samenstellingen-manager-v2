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
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import DownloadIcon from '@mui/icons-material/Download';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import { api, DuplicateBomGroup } from '../api';

function toCsv(groups: DuplicateBomGroup[]): string {
  const headers = ['fingerprint', 'memberCount', 'itemcode', 'name'];
  const escape = (v: string) => (/[",\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v);
  const lines = [headers.join(',')];
  for (const g of groups) {
    for (const m of g.members) {
      lines.push([g.fingerprint, String(g.memberCount), m.itemcode, m.name].map(escape).join(','));
    }
  }
  return lines.join('\n');
}

function downloadCsv(groups: DuplicateBomGroup[]) {
  const blob = new Blob([toCsv(groups)], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `duplicate-boms-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export function DuplicateBoms() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['duplicate-boms'],
    queryFn: api.listDuplicateBoms,
  });
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  const groups = useMemo(() => data ?? [], [data]);
  const totalMembers = useMemo(() => groups.reduce((sum, g) => sum + g.memberCount, 0), [groups]);

  if (isError) {
    return <Alert severity="error">Kon duplicate-BOMs niet laden: {(error as Error).message}</Alert>;
  }

  const toggle = (fp: string) =>
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(fp)) next.delete(fp);
      else next.add(fp);
      return next;
    });

  const allExpanded = groups.length > 0 && groups.every((g) => expanded.has(g.fingerprint));
  const toggleAll = () =>
    setExpanded(allExpanded ? new Set() : new Set(groups.map((g) => g.fingerprint)));

  return (
    <Stack spacing={2}>
      <Box display="flex" justifyContent="space-between" alignItems="center">
        <Stack>
          <Typography variant="h5" component="h1">
            Duplicate BOMs
          </Typography>
          <Typography variant="body2" color="text.secondary">
            AFAS-samenstellingen met identieke BOM. Vaak varianten (bv. <code>11042-60112</code>)
            waar het accessoire-itemcode in AFAS niet aan de BOM is toegevoegd — daardoor
            heeft de variant precies dezelfde BOM als de pure base. Read-only — fix in AFAS.
            {groups.length > 0 && ` (${groups.length} groepen, ${totalMembers} samenstellingen)`}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={1}>
          <Button variant="text" size="small" disabled={groups.length === 0} onClick={toggleAll}>
            {allExpanded ? 'Alles inklappen' : 'Alles uitklappen'}
          </Button>
          <Button
            variant="outlined"
            startIcon={<DownloadIcon />}
            disabled={groups.length === 0}
            onClick={() => downloadCsv(groups)}
          >
            Exporteer CSV
          </Button>
        </Stack>
      </Box>
      {isLoading ? (
        <Skeleton variant="rectangular" height={400} />
      ) : groups.length === 0 ? (
        <Alert severity="success">Geen duplicate BOMs — alle samenstellingen hebben een unieke BOM.</Alert>
      ) : (
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ width: 48 }} />
                <TableCell>BOM-fingerprint</TableCell>
                <TableCell sx={{ width: 110 }} align="right">Aantal</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {groups.map((g) => {
                const isOpen = expanded.has(g.fingerprint);
                return (
                  <DuplicateRow
                    key={g.fingerprint}
                    group={g}
                    isOpen={isOpen}
                    onToggle={() => toggle(g.fingerprint)}
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

function DuplicateRow({
  group,
  isOpen,
  onToggle,
}: {
  group: DuplicateBomGroup;
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
        <TableCell sx={{ fontFamily: 'monospace' }}>{group.fingerprint}</TableCell>
        <TableCell align="right">
          <Chip label={group.memberCount} size="small" color="warning" variant="outlined" />
        </TableCell>
      </TableRow>
      {isOpen &&
        group.members.map((m) => (
          <TableRow key={`${group.fingerprint}-${m.itemcode}`} sx={{ backgroundColor: 'action.hover' }}>
            <TableCell />
            <TableCell colSpan={2}>
              <Typography variant="caption" sx={{ fontFamily: 'monospace', mr: 2 }}>
                {m.itemcode}
              </Typography>
              <Typography variant="caption" color="text.secondary">
                {m.name}
              </Typography>
            </TableCell>
          </TableRow>
        ))}
    </>
  );
}
